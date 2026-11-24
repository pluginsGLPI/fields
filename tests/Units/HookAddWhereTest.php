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

use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function numberSearchProvider(): iterable
    {
        $all = ['999', '1000', '1000.0', '1500', '12345', '1e40', '12.50', '-5', '0', 'cleared', 'unsaved'];
        $without = static fn(string ...$excluded): array => array_values(array_diff($all, $excluded));

        yield 'is 999' => ['equals', '999', false, ['999']];
        yield 'is 1000' => ['equals', '1000', false, ['1000', '1000.0']];
        yield 'is 12345' => ['equals', '12345', false, ['12345']];
        yield 'is 12.5' => ['equals', '12.5', false, ['12.50']];
        yield 'is 12,5' => ['equals', '12,5', false, ['12.50']];
        yield 'is -5' => ['equals', '-5', false, ['-5']];
        yield 'is 1e3' => ['equals', '1e3', false, ['1000', '1000.0']];
        yield 'is 1e40' => ['equals', '1e40', false, ['1e40']];
        yield 'is 1e999' => ['equals', '1e999', false, []];
        // Like core, items without value match an equality search on 0
        yield 'is 0' => ['equals', '0', false, ['0', 'cleared', 'unsaved']];
        yield 'is not 0' => ['notequals', '0', false, $without('0', 'cleared', 'unsaved')];
        yield 'is not 1000' => ['notequals', '1000', false, $without('1000', '1000.0')];
        yield 'is not 999' => ['notequals', '999', false, $without('999')];
        yield 'AND NOT is 1000' => ['equals', '1000', true, $without('1000', '1000.0')];
        yield 'AND NOT is not 1000' => ['notequals', '1000', true, ['1000', '1000.0']];
        yield 'contains > 900' => ['contains', '>900', false, ['999', '1000', '1000.0', '1500', '12345', '1e40']];
        yield 'contains > 1000' => ['contains', '>1000', false, ['1500', '12345', '1e40']];
        yield 'contains >= 1500' => ['contains', '>= 1500', false, ['1500', '12345', '1e40']];
        yield 'contains < 1000' => ['contains', '<1000', false, ['999', '12.50', '-5', '0']];
        yield 'contains <= 1000' => ['contains', '<=1000', false, ['999', '1000', '1000.0', '12.50', '-5', '0']];
        yield 'contains < 2000' => ['contains', '<2000', false, ['999', '1000', '1000.0', '1500', '12.50', '-5', '0']];
        yield 'contains &lt; 2000' => ['contains', '&lt;2000', false, ['999', '1000', '1000.0', '1500', '12.50', '-5', '0']];
        yield 'contains < 0' => ['contains', '<0', false, ['-5']];
        yield 'not contains < 2000' => ['notcontains', '<2000', false, ['12345', '1e40', 'cleared', 'unsaved']];
        yield 'contains 100 (no operator)' => ['contains', '100', false, ['1000', '1000.0']];
        // Casting a non numeric value would raise a SQL warning, which fails the test
        yield 'is abc' => ['equals', 'abc', false, []];
        yield 'is not abc' => ['notequals', 'abc', false, $all];
        yield 'contains < abc' => ['contains', '<abc', false, []];
    }

    /**
     * Number values are stored as VARCHAR: "is" / "is not" used to cast them to DECIMAL(10,7),
     * which overflows from 1000, and <, > compared them as strings.
     */
    #[DataProvider('numberSearchProvider')]
    public function testNumberFieldSearch(string $searchtype, string $value, bool $not, array $expected): void
    {
        $found = $this->searchNumberField($searchtype, $value, $not);
        sort($expected);
        $this->assertSame($expected, $found);
    }

    /**
     * Search computers on a number field, among items storing a predefined set of values.
     *
     * @return list<string> Sorted names (without prefix) of the found items
     */
    private function searchNumberField(string $searchtype, string $value, bool $not): array
    {
        $this->login();

        $container = $this->createFieldContainer([
            'label'        => 'Number search ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
        $field = $this->createField([
            'label'                                     => 'Number',
            'type'                                      => 'number',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);

        $entities_id = $this->getTestRootEntity(true);
        $prefix = 'Number search ' . $this->getUniqueString() . ' ';
        $container_class = PluginFieldsContainer::getClassname(Computer::class, $container->fields['name']);
        $stored_values = ['999', '1000', '1000.0', '1500', '12345', '1e40', '12.50', '-5', '0', 'cleared' => ''];
        foreach ($stored_values as $name => $stored_value) {
            $name = is_int($name) ? $stored_value : $name;
            $computer = $this->createItem(Computer::class, ['name' => $prefix . $name, 'entities_id' => $entities_id]);
            $this->createItem($container_class, [
                'items_id'                    => $computer->getID(),
                'itemtype'                    => Computer::class,
                'plugin_fields_containers_id' => $container->getID(),
                $field->fields['name']        => $stored_value,
            ]);
        }

        // Item for which the container was never saved: no row, NULL through the LEFT JOIN
        $this->createItem(Computer::class, ['name' => $prefix . 'unsaved', 'entities_id' => $entities_id]);

        $so_id = PluginFieldsField::SEARCH_OPTION_STARTING_INDEX + $field->getID();
        $data = Search::getDatas(Computer::class, [
            'is_deleted' => 0,
            'start'      => 0,
            'list_limit' => 100,
            'criteria'   => [
                ['field' => 1, 'searchtype' => 'contains', 'value' => '^' . $prefix],
                ['link' => $not ? 'AND NOT' : 'AND', 'field' => $so_id, 'searchtype' => $searchtype, 'value' => $value],
            ],
        ], [1]);

        $found = array_map(
            static fn(array $row): string => substr((string) $row['raw']['ITEM_Computer_1'], strlen($prefix)),
            $data['data']['rows'],
        );
        sort($found);

        return $found;
    }
}
