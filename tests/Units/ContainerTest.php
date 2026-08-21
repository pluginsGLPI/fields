<?php

/**
 * -------------------------------------------------------------------------
 * Fields plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Fields.
 *
 * Fields is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Fields is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Fields. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2013-2023 by Fields plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/fields
 * -------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\Field\Tests\Units;

use Computer;
use DBmysql;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use Migration;
use PHPUnit\Framework\Attributes\DataProvider;
use PluginFieldsContainer;
use Ticket;

require_once __DIR__ . '/../FieldTestCase.php';

final class ContainerTest extends DbTestCase
{
    use FieldTestTrait;

    public function setUp(): void
    {
        GLPITestCase::setUp();
        $this->login();
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    public static function provideInvalidItemtypes(): iterable
    {
        yield 'missing itemtypes' => [
            'input' => [
                'name'  => 'test_container',
                'label' => 'Test Container',
                'type'  => 'tab',
            ],
        ];

        yield 'empty itemtypes array' => [
            'input' => [
                'name'      => 'test_container',
                'label'     => 'Test Container',
                'type'      => 'tab',
                'itemtypes' => [],
            ],
        ];

        yield 'empty itemtypes string' => [
            'input' => [
                'name'      => 'test_container',
                'label'     => 'Test Container',
                'type'      => 'tab',
                'itemtypes' => '',
            ],
        ];
    }

    #[DataProvider('provideInvalidItemtypes')]
    public function testAddWithoutItemtypesIsRejected(array $input): void
    {
        $container = new PluginFieldsContainer();
        $result = $container->add($input);

        $this->assertFalse($result);
    }

    public function testAddWithValidItemtypesSucceeds(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'ValidItemtypes ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $this->assertGreaterThan(0, $container->getID());
    }

    public function testAddDomtabWithIncompatibleItemtypeIsRejected(): void
    {
        $container = new PluginFieldsContainer();
        $result = $container->add([
            'label'        => 'Domtab with invalid item type',
            'type'         => 'domtab',
            'subtype'      => '',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
        $this->assertFalse($result);
    }

    public function testInstallUserDataDisablesOversizedContainerName(): void
    {
        /** @var DBmysql $DB */
        global $DB;

        // Bypass prepareInputForAdd's own length guard to simulate a container
        // whose name was corrupted/imported before this length was enforced.
        $DB->insert(PluginFieldsContainer::getTable(), [
            'name'         => str_repeat('a', 100),
            'label'        => 'Oversized ' . $this->getUniqueString(),
            'itemtypes'    => json_encode([Computer::class]),
            'type'         => 'tab',
            'entities_id'  => 0,
            'is_recursive' => 1,
            'is_active'    => 1,
        ]);
        $container_id = $DB->insertId();

        try {
            $result = PluginFieldsContainer::installUserData(new Migration('1.24.4'), '1.24.4');
            $this->assertTrue($result);

            $container = new PluginFieldsContainer();
            $this->assertTrue($container->getFromDB($container_id));
            $this->assertSame(0, (int) $container->fields['is_active']);
        } finally {
            $DB->delete(PluginFieldsContainer::getTable(), ['id' => $container_id]);
        }
    }

    public function testRenameOversizedContainerSucceeds(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'RenameMe ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 0,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        /** @var DBmysql $DB */
        global $DB;
        $DB->update(PluginFieldsContainer::getTable(), ['name' => str_repeat('a', 100)], ['id' => $container->getID()]);

        $new_name = 'Renamed' . str_replace('-', '', $this->getUniqueString());

        $result = PluginFieldsContainer::renameOversizedContainer($container->getID(), $new_name);
        $this->assertTrue($result);

        $reloaded = new PluginFieldsContainer();
        $this->assertTrue($reloaded->getFromDB($container->getID()));
        $this->assertSame($new_name, $reloaded->fields['name']);
        $this->assertSame(1, (int) $reloaded->fields['is_active']);

        $table = getTableForItemType(PluginFieldsContainer::getClassname(Computer::class, $new_name));
        $this->assertTrue($DB->tableExists($table));

        // The reactivated container must be genuinely usable, not just flagged active.
        $field = $this->createField([
            'label'                                      => 'Serial extra ' . str_replace('-', '', $this->getUniqueString()),
            'type'                                        => 'text',
            PluginFieldsContainer::getForeignKeyField()   => $container->getID(),
            'ranking'                                     => 1,
            'is_active'                                   => 1,
            'is_readonly'                                 => 0,
        ]);
        $field_name = $field->fields['name'];

        $computer = $this->createItem(Computer::class, [
            'name'        => 'Computer for rename test',
            'entities_id' => 0,
        ]);
        $computer_item = new Computer();
        $this->assertTrue($computer_item->update([
            'id'        => $computer->getID(),
            $field_name => 'real value',
        ]));

        $rows = $DB->request(['FROM' => $table, 'WHERE' => ['items_id' => $computer->getID()]]);
        $this->assertCount(1, $rows);
        $this->assertSame('real value', $rows->current()[$field_name]);
    }

    public function testRenameOversizedContainerRecoversDataFromOrphanTable(): void
    {
        // Simulate a container that had a real, working table on an older GLPI/plugin
        // version: the container row gets its name overwritten by a migration step
        // (losing the link to its own table), while the physical table survives untouched.
        $container = $this->createFieldContainer([
            'label'        => 'RealData ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                      => 'Serial extra ' . str_replace('-', '', $this->getUniqueString()),
            'type'                                        => 'text',
            PluginFieldsContainer::getForeignKeyField()   => $container->getID(),
            'ranking'                                     => 1,
            'is_active'                                   => 1,
            'is_readonly'                                 => 0,
        ]);
        $field_name = $field->fields['name'];

        $computer = $this->createItem(Computer::class, [
            'name'        => 'Computer with real data',
            'entities_id' => 0,
        ]);
        $computer_item = new Computer();
        $this->assertTrue($computer_item->update([
            'id'        => $computer->getID(),
            $field_name => 'data from an older version',
        ]));

        $old_table = getTableForItemType(PluginFieldsContainer::getClassname(Computer::class, $container->fields['name']));

        /** @var DBmysql $DB */
        global $DB;
        $DB->update(PluginFieldsContainer::getTable(), ['name' => str_repeat('a', 100)], ['id' => $container->getID()]);

        $new_name = 'Recovered' . str_replace('-', '', $this->getUniqueString());

        $result = PluginFieldsContainer::renameOversizedContainer($container->getID(), $new_name);
        $this->assertTrue($result);

        $new_table = getTableForItemType(PluginFieldsContainer::getClassname(Computer::class, $new_name));
        $this->assertFalse($DB->tableExists($old_table));
        $this->assertTrue($DB->tableExists($new_table));

        $rows = $DB->request(['FROM' => $new_table, 'WHERE' => ['items_id' => $computer->getID()]]);
        $this->assertCount(1, $rows);
        $this->assertSame('data from an older version', $rows->current()[$field_name]);
    }

    public function testRenameOversizedContainerFailsForUnknownContainer(): void
    {
        $result = PluginFieldsContainer::renameOversizedContainer(999999999, 'whatever');

        $this->assertFalse($result);
    }

    public function testRenameOversizedContainerFailsWhenNameIsStillTooLong(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'StillTooLong ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 0,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
        $original_name = $container->fields['name'];

        $result = PluginFieldsContainer::renameOversizedContainer($container->getID(), str_repeat('a', 100));

        $this->assertFalse($result);

        $reloaded = new PluginFieldsContainer();
        $this->assertTrue($reloaded->getFromDB($container->getID()));
        $this->assertSame($original_name, $reloaded->fields['name']);
        $this->assertSame(0, (int) $reloaded->fields['is_active']);
    }

    public function testRenameOversizedContainerFailsOnNameCollision(): void
    {
        $existing = $this->createFieldContainer([
            'label'        => 'Existing ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $container = $this->createFieldContainer([
            'label'        => 'Colliding ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 0,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $result = PluginFieldsContainer::renameOversizedContainer($container->getID(), $existing->fields['name']);

        $this->assertFalse($result);

        $reloaded = new PluginFieldsContainer();
        $this->assertTrue($reloaded->getFromDB($container->getID()));
        $this->assertSame(0, (int) $reloaded->fields['is_active']);
    }
}
