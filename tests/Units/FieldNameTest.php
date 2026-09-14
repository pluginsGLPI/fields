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
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PluginFieldsField;

require_once __DIR__ . '/../FieldTestCase.php';

final class FieldNameTest extends DbTestCase
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

    public static function provideFieldNames(): iterable
    {
        yield 'backticked ddl statement' => [
            'name'     => 'aone`,DROP COLUMN `id',
            'expected' => 'aoneDROPCOLUMNid',
        ];

        yield 'quotes and spaces' => [
            'name'     => "my field'",
            'expected' => 'myfield',
        ];

        yield 'already safe name' => [
            'name'     => 'safe_name',
            'expected' => 'safe_name',
        ];

        yield 'fully numeric name' => [
            'name'     => '123',
            'expected' => '123',
        ];

        yield 'name with digits' => [
            'name'     => 'test123',
            'expected' => 'test123',
        ];

        yield 'name emptied by sanitization' => [
            'name'     => '!!!',
            'expected' => 'fieldnamefield',
        ];
    }

    public static function provideDropdownFieldNames(): iterable
    {
        yield 'standard itemtype' => [
            'type'     => 'dropdown-' . Computer::class,
            'name'     => "my field'",
            'expected' => 'computers_id_myfield',
        ];

        yield 'itemtype with ddl chars' => [
            'type'     => 'dropdown-Mon`itor',
            'name'     => 'value',
            'expected' => 'monitors_id_value',
        ];
    }

    #[DataProvider('provideFieldNames')]
    public function testPrepareNameKeepsOnlyIdentifierSafeChars(string $name, string $expected): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'Field name ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $prepared = (new PluginFieldsField())->prepareName([
            'name'                        => $name,
            'label'                       => 'Field name',
            'type'                        => 'text',
            'plugin_fields_containers_id' => $container->getID(),
        ]);

        $this->assertSame($expected, $prepared);
    }

    #[DataProvider('provideDropdownFieldNames')]
    public function testPrepareNameSanitizesDropdownForeignKeyPrefix(string $type, string $name, string $expected): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'Field name ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $prepared = (new PluginFieldsField())->prepareName([
            'name'                        => $name,
            'label'                       => 'Field name',
            'type'                        => $type,
            'plugin_fields_containers_id' => $container->getID(),
        ]);

        $this->assertSame($expected, $prepared);
    }

    public function testUpdateCannotChangeSystemName(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'Field name ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'name'                        => 'safe_name',
            'label'                       => 'Field name',
            'type'                        => 'text',
            'ranking'                     => 1,
            'plugin_fields_containers_id' => $container->getID(),
            'is_active'                   => 1,
        ]);

        $this->assertTrue($field->update([
            'id'                            => $field->getID(),
            'name'                          => 'evil`,DROP COLUMN `id',
            'label'                         => 'Renamed label',
            'plugin_fields_containers_id'   => $container->getID(),
        ]));

        $this->assertTrue($field->getFromDB($field->getID()));
        $this->assertSame('safe_name', $field->fields['name']);
        $this->assertSame('Renamed label', $field->fields['label']);
    }
}
