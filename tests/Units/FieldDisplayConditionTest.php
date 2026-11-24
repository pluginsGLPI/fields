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

use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use PluginFieldsContainer;
use PluginFieldsContainerDisplayCondition;
use PluginFieldsField;
use PluginFieldsFieldDisplayCondition;
use Search;
use Ticket;

require_once __DIR__ . '/../FieldTestCase.php';

/**
 * Tests covering the "hide field" condition feature (as opposed to
 * PluginFieldsContainerDisplayCondition, which hides the whole block).
 */
final class FieldDisplayConditionTest extends DbTestCase
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

        $condition = new PluginFieldsFieldDisplayCondition();
        foreach ($condition->find() as $row) {
            $condition->delete($row, true);
        }

        GLPITestCase::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Find the search option id whose linkfield is 'name' for the given itemtype,
     * so tests can build conditions on the item's title/name without relying on a
     * hardcoded search option id (which can differ between GLPI versions).
     */
    private function getNameSearchOptionId(string $itemtype): int
    {
        foreach (Search::getOptions($itemtype) as $so_id => $so) {
            if (($so['linkfield'] ?? null) === 'name' && ($so['table'] ?? null) === $itemtype::getTable()) {
                return (int) $so_id;
            }
        }

        $this->fail(sprintf('Unable to find "name" search option for %s', $itemtype));
    }

    private function createHideFieldCondition(
        int $field_id,
        int $container_id,
        string $itemtype,
        int $search_option,
        int $condition,
        string $value,
    ): PluginFieldsFieldDisplayCondition {
        return $this->createItem(PluginFieldsFieldDisplayCondition::class, [
            'plugin_fields_fields_id'     => $field_id,
            'plugin_fields_containers_id' => $container_id,
            'itemtype'                    => $itemtype,
            'search_option'               => $search_option,
            'condition'                   => $condition,
            'value'                       => $value,
        ]);
    }

    // -----------------------------------------------------------------------
    // getFieldsForContainer()
    // -----------------------------------------------------------------------

    public function testGetFieldsForContainerListsContainerFields(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'FieldsForContainer ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'Listed Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                                => 0,
        ]);

        $choices = PluginFieldsFieldDisplayCondition::getFieldsForContainer($container->getID());

        $this->assertArrayHasKey($field->getID(), $choices);
    }

    public function testGetFieldsForContainerIsEmptyForContainerWithoutFields(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'EmptyFieldsContainer ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $choices = PluginFieldsFieldDisplayCondition::getFieldsForContainer($container->getID());

        $this->assertSame([], $choices);
    }

    // -----------------------------------------------------------------------
    // prepareInputForAdd() / prepareInputForUpdate()
    // -----------------------------------------------------------------------

    public function testAddWithoutTargetFieldIsRejected(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'NoTargetField ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $so_id = $this->getNameSearchOptionId(Ticket::class);

        $condition = new PluginFieldsFieldDisplayCondition();
        $result = $condition->add([
            // plugin_fields_fields_id intentionally omitted
            'plugin_fields_containers_id' => $container->getID(),
            'itemtype'                    => Ticket::class,
            'search_option'               => $so_id,
            'condition'                   => PluginFieldsContainerDisplayCondition::SHOW_CONDITION_EQ,
            'value'                       => 'whatever',
        ]);

        $this->assertFalse($result);
    }

    public function testAddWithoutItemtypeIsRejected(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'NoItemtype ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'Orphan Condition Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                                => 0,
        ]);

        $condition = new PluginFieldsFieldDisplayCondition();
        $result = $condition->add([
            'plugin_fields_fields_id'     => $field->getID(),
            'plugin_fields_containers_id' => $container->getID(),
            // itemtype/search_option/condition intentionally omitted
        ]);

        $this->assertFalse($result);
    }

    // -----------------------------------------------------------------------
    // computeDisplayField()
    // -----------------------------------------------------------------------

    public function testComputeDisplayFieldReturnsTrueWhenNoConditionExists(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'NoCondition ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'Always Visible Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                                => 0,
        ]);

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Any ticket',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        $displayCondition = new PluginFieldsFieldDisplayCondition();
        $this->assertTrue(
            $displayCondition->computeDisplayField($ticket, $container->getID(), $field->getID()),
        );
    }

    public function testComputeDisplayFieldHidesFieldWhenConditionMatches(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'HideMatch ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'Conditionally Hidden Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                                => 0,
        ]);

        $so_id = $this->getNameSearchOptionId(Ticket::class);

        $this->createHideFieldCondition(
            $field->getID(),
            $container->getID(),
            Ticket::class,
            $so_id,
            PluginFieldsContainerDisplayCondition::SHOW_CONDITION_EQ,
            'Trigger hide',
        );

        $matching_ticket = $this->createItem(Ticket::class, [
            'name'        => 'Trigger hide',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        $displayCondition = new PluginFieldsFieldDisplayCondition();
        $this->assertFalse(
            $displayCondition->computeDisplayField($matching_ticket, $container->getID(), $field->getID()),
            'Field must be hidden when the condition matches.',
        );
    }

    public function testComputeDisplayFieldShowsFieldWhenConditionDoesNotMatch(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'ShowNoMatch ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'Conditionally Hidden Field 2',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                                => 0,
        ]);

        $so_id = $this->getNameSearchOptionId(Ticket::class);

        $this->createHideFieldCondition(
            $field->getID(),
            $container->getID(),
            Ticket::class,
            $so_id,
            PluginFieldsContainerDisplayCondition::SHOW_CONDITION_EQ,
            'Trigger hide',
        );

        $non_matching_ticket = $this->createItem(Ticket::class, [
            'name'        => 'Do not trigger',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        $displayCondition = new PluginFieldsFieldDisplayCondition();
        $this->assertTrue(
            $displayCondition->computeDisplayField($non_matching_ticket, $container->getID(), $field->getID()),
            'Field must stay visible when the condition does not match.',
        );
    }

    public function testComputeDisplayFieldWithZeroFieldIdAlwaysReturnsTrue(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'ZeroFieldId ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Any ticket',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        $displayCondition = new PluginFieldsFieldDisplayCondition();
        $this->assertTrue($displayCondition->computeDisplayField($ticket, $container->getID(), 0));
    }

    // -----------------------------------------------------------------------
    // End-to-end: rendered fields via PluginFieldsField::prepareHtmlFields()
    // -----------------------------------------------------------------------

    public function testHiddenFieldIsExcludedFromRenderedOutput(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'RenderedHidden ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $hidden_field = $this->createField([
            'label'                                     => 'Hidden In Output',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                                => 0,
        ]);

        $visible_field = $this->createField([
            'label'                                     => 'Stays Visible',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 2,
            'is_active'                                 => 1,
            'is_readonly'                                => 0,
        ]);

        $so_id = $this->getNameSearchOptionId(Ticket::class);

        $this->createHideFieldCondition(
            $hidden_field->getID(),
            $container->getID(),
            Ticket::class,
            $so_id,
            PluginFieldsContainerDisplayCondition::SHOW_CONDITION_EQ,
            'Hide the field',
        );

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Hide the field',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        $field_obj = new PluginFieldsField();
        $fields = $field_obj->find(['plugin_fields_containers_id' => $container->getID()], 'ranking');

        $html = PluginFieldsField::prepareHtmlFields($fields, $ticket);

        $this->assertIsString($html);
        $this->assertStringNotContainsString(
            $hidden_field->fields['name'],
            $html,
            'Hidden field must not be present in the rendered output.',
        );
        $this->assertStringContainsString(
            $visible_field->fields['name'],
            $html,
            'Non-targeted field must still be rendered.',
        );
    }

    public function testFieldIsRenderedWhenConditionDoesNotMatch(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'RenderedVisible ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'Not Hidden Here',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                                => 0,
        ]);

        $so_id = $this->getNameSearchOptionId(Ticket::class);

        $this->createHideFieldCondition(
            $field->getID(),
            $container->getID(),
            Ticket::class,
            $so_id,
            PluginFieldsContainerDisplayCondition::SHOW_CONDITION_EQ,
            'Hide the field',
        );

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Some other title',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        $field_obj = new PluginFieldsField();
        $fields = $field_obj->find(['plugin_fields_containers_id' => $container->getID()], 'ranking');

        $html = PluginFieldsField::prepareHtmlFields($fields, $ticket);

        $this->assertIsString($html);
        $this->assertStringContainsString($field->fields['name'], $html);
    }
}
