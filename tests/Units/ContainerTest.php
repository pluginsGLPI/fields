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

namespace GlpiPlugin\Field\Tests\Units;

use Computer;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PluginFieldsContainer;

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

    public function testPrepareInputForUpdateStripsLabelAndNameOnFormSubmission(): void
    {
        $container = new PluginFieldsContainer();
        $input = [
            'label'           => 'New Label',
            'name'            => 'new_name',
            'form_submission' => true,
            'is_active'       => 1,
        ];

        $result = $container->prepareInputForUpdate($input);

        $this->assertArrayNotHasKey('label', $result);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayHasKey('is_active', $result);
    }

    public function testPrepareInputForUpdatePreservesNameAndLabelForMigration(): void
    {
        $container = new PluginFieldsContainer();
        $input = [
            'name'      => 'migration_corrected_name',
            'label'     => 'Some Label',
            'itemtypes' => '["Computer"]',
        ];

        $result = $container->prepareInputForUpdate($input);

        $this->assertSame('migration_corrected_name', $result['name']);
        $this->assertArrayHasKey('label', $result);
    }

    public function testUpdateNameViaMigrationPathPersistsInDatabase(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'MigrationPath ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $original_name = $container->fields['name'];
        $new_name = 'mig_' . substr((string) $original_name, 0, 20);

        $container->update(['id' => $container->getID(), 'name' => $new_name]);

        $refreshed = new PluginFieldsContainer();
        $refreshed->getFromDB($container->getID());
        $this->assertSame($new_name, $refreshed->fields['name']);

        // Restore original name so teardown can locate and drop the physical table
        $container->update(['id' => $container->getID(), 'name' => $original_name]);
    }
}
