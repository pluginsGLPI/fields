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
use PluginFieldsContainer;
use PluginFieldsField;
use Search;

require_once __DIR__ . '/../FieldTestCase.php';

/**
 * Regression test for ticket #45192: a search on an itemtype threw
 * TooManyResultsException as soon as two containers defined a 'dropdown'
 * field with the same name.
 *
 * PluginFieldsField::prepareName() intentionally links a new 'dropdown'
 * field to an existing field with the same name so both containers share
 * the same underlying dropdown table. This produces two distinct rows in
 * glpi_plugin_fields_fields with the same name, and plugin_fields_addWhere()
 * used to look the field up by name, which is ambiguous in that case.
 */
final class HookAddWhereTest extends DbTestCase
{
    use FieldTestTrait;

    public function setUp(): void
    {
        GLPITestCase::setUp();
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    public function testAddWhereDoesNotThrowOnDuplicateFieldName(): void
    {
        $this->login();

        $container1 = $this->createFieldContainer([
            'label'        => 'Container A ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        // Bypass createField(): default_value gets JSON re-encoded by prepareInputForAdd()
        // and cannot be compared as-is, so it must be excluded from the input check.
        $field1 = $this->createItem(PluginFieldsField::class, [
            'label'                                      => 'Accessoire',
            'type'                                        => 'dropdown',
            'multiple'                                    => 1,
            'default_value'                               => [],
            PluginFieldsContainer::getForeignKeyField()   => $container1->getID(),
            'ranking'                                     => 1,
            'is_active'                                   => 1,
            'is_readonly'                                  => 0,
        ], ['allowed_values', 'question_types', 'default_value']);

        $container2 = $this->createFieldContainer([
            'label'        => 'Container B ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field2 = $this->createItem(PluginFieldsField::class, [
            'label'                                      => 'Accessoire',
            'type'                                        => 'dropdown',
            'multiple'                                    => 1,
            'default_value'                               => [],
            PluginFieldsContainer::getForeignKeyField()   => $container2->getID(),
            'ranking'                                     => 1,
            'is_active'                                   => 1,
            'is_readonly'                                  => 0,
        ], ['allowed_values', 'question_types', 'default_value']);

        // Both containers intentionally share the same underlying field name.
        $this->assertSame($field1->fields['name'], $field2->fields['name']);

        $searchopt = Search::getOptions(Computer::class);
        $so_id = PluginFieldsField::SEARCH_OPTION_STARTING_INDEX + $field1->getID();
        $this->assertArrayHasKey($so_id, $searchopt);

        // Before the fix, this threw:
        // `PluginFieldsField::getFromDBByCrit()` expects to get one result, 2 found.
        $result = plugin_fields_addWhere('', false, Computer::class, $so_id, '1', 'equals');

        $this->assertNotFalse($result);
    }
}
