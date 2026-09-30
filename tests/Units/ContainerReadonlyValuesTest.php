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
use Entity;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use PluginFieldsContainer;
use PluginFieldsDropdown;
use PluginFieldsField;
use PluginFieldsStatusOverride;
use State;

require_once __DIR__ . '/../FieldTestCase.php';

final class ContainerReadonlyValuesTest extends DbTestCase
{
    use FieldTestTrait;

    private int $entity_id;

    public function setUp(): void
    {
        GLPITestCase::setUp();
        $this->login();
        $this->entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $this->setEntity($this->entity_id, true);
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    public function testReadonlyValueIsReplacedByStoredValue(): void
    {
        $container     = $this->createContainer('tab');
        $readonly_name = $this->createTextField($container, 'Locked', 1);
        $editable_name = $this->createTextField($container, 'Editable', 0);
        $computer      = $this->createComputer();

        $this->storeValues($container, $computer, [$readonly_name => 'stored']);

        $data = PluginFieldsContainer::removeReadonlyValues(
            $this->buildData($container, $computer, [$readonly_name => 'hacked', $editable_name => 'new']),
            $computer,
        );

        $this->assertSame('stored', $data[$readonly_name]);
        $this->assertSame('new', $data[$editable_name]);
    }

    public function testReadonlyValueWithoutStoredRowIsDropped(): void
    {
        $container     = $this->createContainer('tab');
        $readonly_name = $this->createTextField($container, 'Locked', 1);
        $editable_name = $this->createTextField($container, 'Editable', 0);
        $computer      = $this->createComputer();

        $data = PluginFieldsContainer::removeReadonlyValues(
            $this->buildData($container, $computer, [
                $readonly_name                      => 'hacked',
                '_' . $readonly_name . '_defined'   => 1,
                $editable_name                      => 'new',
            ]),
            $computer,
        );

        $this->assertArrayNotHasKey($readonly_name, $data);
        $this->assertArrayNotHasKey('_' . $readonly_name . '_defined', $data);
        $this->assertSame('new', $data[$editable_name]);
    }

    public function testStatusOverrideTogglesReadonly(): void
    {
        $container  = $this->createContainer('dom');
        $field_name = $this->createTextField($container, 'Locked when stocked', 0);
        $state      = $this->createItem(State::class, ['name' => 'State ' . $this->getUniqueString(), 'entities_id' => $this->entity_id]);
        $computer   = $this->createComputer();

        $this->createItem(PluginFieldsStatusOverride::class, [
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'plugin_fields_fields_id'                   => $this->fieldIdByName($field_name),
            'itemtype'                                  => Computer::class,
            'states'                                    => [$state->getID()],
            'is_readonly'                               => 1,
            'mandatory'                                 => 0,
        ], ['states', PluginFieldsContainer::getForeignKeyField()]);
        $this->storeValues($container, $computer, [$field_name => 'stored']);

        $data = PluginFieldsContainer::removeReadonlyValues(
            $this->buildData($container, $computer, [$field_name => 'submitted']),
            $computer,
        );
        $this->assertSame('submitted', $data[$field_name]);

        $computer = $this->updateItem(Computer::class, $computer->getID(), ['states_id' => $state->getID()]);

        $data = PluginFieldsContainer::removeReadonlyValues(
            $this->buildData($container, $computer, [$field_name => 'submitted']),
            $computer,
        );
        $this->assertSame('stored', $data[$field_name]);
    }

    public function testStatusOverrideUsesSubmittedStatusOnUpdate(): void
    {
        $container  = $this->createContainer('dom');
        $field_name = $this->createTextField($container, 'Locked when stocked', 0);
        $state      = $this->createItem(State::class, ['name' => 'State ' . $this->getUniqueString(), 'entities_id' => $this->entity_id]);
        $computer   = $this->createComputer();
        $this->createReadonlyOverride($container, $field_name, $state);
        $this->storeValues($container, $computer, [$field_name => 'stored']);

        $computer->input = ['states_id' => $state->getID()];
        $data = PluginFieldsContainer::removeReadonlyValues(
            $this->buildData($container, $computer, [$field_name => 'submitted']),
            $computer,
        );
        $this->assertSame('stored', $data[$field_name]);

        $computer = $this->updateItem(Computer::class, $computer->getID(), ['states_id' => $state->getID()]);
        $computer->input = ['states_id' => 0];

        $data = PluginFieldsContainer::removeReadonlyValues(
            $this->buildData($container, $computer, [$field_name => 'submitted']),
            $computer,
        );
        $this->assertSame('submitted', $data[$field_name]);
    }

    public function testStatusOverrideAppliesOnItemCreation(): void
    {
        $container  = $this->createContainer('dom');
        $field_name = $this->createTextField($container, 'Locked when stocked', 0, 'default');
        $state      = $this->createItem(State::class, ['name' => 'State ' . $this->getUniqueString(), 'entities_id' => $this->entity_id]);
        $this->createReadonlyOverride($container, $field_name, $state);

        $computer        = new Computer();
        $computer->input = ['states_id' => $state->getID()];

        $data = PluginFieldsContainer::removeReadonlyValues(
            [$field_name => 'hacked', 'plugin_fields_containers_id' => $container->getID(), 'itemtype' => Computer::class, 'items_id' => 0],
            $computer,
        );
        $this->assertSame('default', $data[$field_name]);
    }

    public function testReadonlyMultipleDropdownIsRestoredAsArray(): void
    {
        $container = $this->createContainer('tab');
        $field     = $this->createField([
            'label'                                     => 'Tags',
            'type'                                      => 'dropdown',
            'multiple'                                  => 1,
            'default_value'                             => [],
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 1,
        ], ['default_value']);
        $dropdown_key   = sprintf('plugin_fields_%sdropdowns_id', $field->fields['name']);
        $dropdown_class = PluginFieldsDropdown::getClassname($field->fields['name']);
        $stored_ids     = [
            $this->createItem($dropdown_class, ['name' => 'A'])->getID(),
            $this->createItem($dropdown_class, ['name' => 'B'])->getID(),
        ];
        $other_id = $this->createItem($dropdown_class, ['name' => 'C'])->getID();
        $computer = $this->createComputer();

        $this->storeValues($container, $computer, [$dropdown_key => $stored_ids]);

        $data = PluginFieldsContainer::removeReadonlyValues(
            $this->buildData($container, $computer, [$dropdown_key => [$other_id], '_' . $dropdown_key . '_defined' => 1]),
            $computer,
        );

        $this->assertSame($stored_ids, $data[$dropdown_key]);
        $this->assertArrayNotHasKey('_' . $dropdown_key . '_defined', $data);
    }

    public function testDomBlockReadonlyFieldCannotBeOverwrittenFromItemForm(): void
    {
        $container     = $this->createContainer('dom');
        $readonly_name = $this->createTextField($container, 'Locked', 1);
        $editable_name = $this->createTextField($container, 'Editable', 0);
        $computer      = $this->createComputer();

        $this->storeValues($container, $computer, [$readonly_name => 'stored', $editable_name => 'old']);

        $this->updateItem(Computer::class, $computer->getID(), [
            'name'         => 'Renamed',
            $readonly_name => 'hacked',
            $editable_name => 'new',
        ], [$readonly_name, $editable_name]);

        $values = $this->getStoredValues($container, $computer);
        $this->assertSame('stored', $values[$readonly_name]);
        $this->assertSame('new', $values[$editable_name]);
    }

    public function testDomBlockReadonlyFieldTakesItsDefaultOnItemCreation(): void
    {
        $container     = $this->createContainer('dom');
        $readonly_name = $this->createTextField($container, 'Locked', 1, 'locked default');
        $editable_name = $this->createTextField($container, 'Editable', 0);

        $computer = $this->createItem(Computer::class, [
            'name'         => 'Computer ' . $this->getUniqueString(),
            'entities_id'  => $this->entity_id,
            $readonly_name => 'hacked',
            $editable_name => 'new',
        ], [$readonly_name, $editable_name]);
        $this->assertInstanceOf(Computer::class, $computer);

        $values = $this->getStoredValues($container, $computer);
        $this->assertSame('locked default', $values[$readonly_name]);
        $this->assertSame('new', $values[$editable_name]);
    }

    private function createContainer(string $type): PluginFieldsContainer
    {
        return $this->createFieldContainer([
            'label'        => 'Readonly ' . $type . ' ' . $this->getUniqueString(),
            'type'         => $type,
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => $this->entity_id,
            'is_recursive' => 1,
        ]);
    }

    private function createTextField(PluginFieldsContainer $container, string $label, int $is_readonly, string $default_value = ''): string
    {
        $field = $this->createField([
            'label'                                     => $label,
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => $is_readonly,
            'default_value'                             => $default_value,
        ]);

        return $field->fields['name'];
    }

    private function createReadonlyOverride(PluginFieldsContainer $container, string $field_name, State $state): void
    {
        $this->createItem(PluginFieldsStatusOverride::class, [
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'plugin_fields_fields_id'                   => $this->fieldIdByName($field_name),
            'itemtype'                                  => Computer::class,
            'states'                                    => [$state->getID()],
            'is_readonly'                               => 1,
            'mandatory'                                 => 0,
        ], ['states', PluginFieldsContainer::getForeignKeyField()]);
    }

    private function fieldIdByName(string $name): int
    {
        $field = new PluginFieldsField();
        $this->assertTrue($field->getFromDBByCrit(['name' => $name]));

        return $field->getID();
    }

    private function createComputer(): Computer
    {
        $computer = $this->createItem(Computer::class, [
            'name'        => 'Computer ' . $this->getUniqueString(),
            'entities_id' => $this->entity_id,
        ]);
        $this->assertInstanceOf(Computer::class, $computer);

        return $computer;
    }

    private function buildData(PluginFieldsContainer $container, Computer $computer, array $values): array
    {
        return $values + [
            'plugin_fields_containers_id' => $container->getID(),
            'itemtype'                    => Computer::class,
            'items_id'                    => $computer->getID(),
        ];
    }

    private function storeValues(PluginFieldsContainer $container, Computer $computer, array $values): void
    {
        $this->assertTrue($container->updateFieldsValues($this->buildData($container, $computer, $values), Computer::class, false));
    }

    private function getStoredValues(PluginFieldsContainer $container, Computer $computer): array
    {
        $classname  = PluginFieldsContainer::getClassname(Computer::class, $container->fields['name']);
        $values_obj = new $classname();
        $this->assertTrue($values_obj->getFromDBByCrit(['items_id' => $computer->getID()]));

        return $values_obj->fields;
    }
}
