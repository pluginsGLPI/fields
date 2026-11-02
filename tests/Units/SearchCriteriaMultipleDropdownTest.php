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
use PluginFieldsDropdown;
use PluginFieldsField;
use Search;
use User;

require_once __DIR__ . '/../FieldTestCase.php';

/**
 * The value input of a search criterion on a multiple dropdown field must keep
 * the searched value once the search is submitted, otherwise sorting or paging
 * the results relaunches the search with an empty criterion.
 */
final class SearchCriteriaMultipleDropdownTest extends DbTestCase
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

    public function testCriterionKeepsValueOnMultipleDropdown(): void
    {
        $this->login();

        $container = $this->createComputerContainer();
        $field = $this->createField([
            'label'                                     => 'Accessoire',
            'type'                                      => 'dropdown',
            'multiple'                                  => 1,
            'default_value'                             => [],
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ], ['default_value']);

        $dropdown_class = PluginFieldsDropdown::getClassname($field->fields['name']);
        $value = $this->createItem($dropdown_class, ['name' => 'Searched value', 'entities_id' => 0]);

        $html = $this->getCriterionValueInput($container, $field, $value->getID());

        $this->assertStringContainsString(
            sprintf('<option value="%d" selected="selected">Searched value</option>', $value->getID()),
            $html,
        );
    }

    public function testCriterionKeepsValueOnMultipleGlpiDropdown(): void
    {
        $this->login();

        $container = $this->createComputerContainer();
        $field = $this->createField([
            'label'                                     => 'Users',
            'type'                                      => 'dropdown-User',
            'multiple'                                  => 1,
            'default_value'                             => [],
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ], ['default_value']);

        $user_id = getItemByTypeName(User::class, TU_USER, true);

        $html = $this->getCriterionValueInput($container, $field, $user_id);

        $this->assertStringContainsString(sprintf('<option value="%d" selected="selected">', $user_id), $html);
    }

    private function createComputerContainer(): PluginFieldsContainer
    {
        return $this->createFieldContainer([
            'label'        => 'Search ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
    }

    /**
     * Render the value input of a search criterion, as done by the search query builder.
     */
    private function getCriterionValueInput(PluginFieldsContainer $container, PluginFieldsField $field, int $value): string
    {
        $so_id = PluginFieldsField::SEARCH_OPTION_STARTING_INDEX + $field->getID();
        $searchopt = Search::getOptions(Computer::class);
        $this->assertArrayHasKey($so_id, $searchopt);

        $container_class = PluginFieldsContainer::getClassname(Computer::class, $container->fields['name']);
        $container_item = new $container_class();

        return $container_item->getValueToSelect($searchopt[$so_id], 'criteria[0][value]', $value);
    }
}
