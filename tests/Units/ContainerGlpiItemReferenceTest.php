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
use Location;
use PluginFieldsContainer;

require_once __DIR__ . '/../FieldTestCase.php';

final class ContainerGlpiItemReferenceTest extends DbTestCase
{
    use FieldTestTrait;

    private PluginFieldsContainer $container;

    private string $field_name;

    private Computer $holder;

    private Computer $root_target;

    private Computer $child_target;

    private int $root_entity_id;

    public function setUp(): void
    {
        GLPITestCase::setUp();
        $this->login();

        $this->root_entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $child_entity_id      = getItemByTypeName(Entity::class, '_test_child_1', true);
        $this->setEntity($this->root_entity_id, true);

        $this->container = $this->createFieldContainer([
            'label'        => 'Item reference ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => $this->root_entity_id,
            'is_recursive' => 1,
        ]);
        $field = $this->createField([
            'label'                                     => 'Linked computer',
            'type'                                      => 'glpi_item',
            PluginFieldsContainer::getForeignKeyField() => $this->container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
            'allowed_values'                            => [Computer::class],
        ]);
        $this->field_name = $field->fields['name'];

        $this->holder       = $this->createComputer($this->root_entity_id);
        $this->root_target  = $this->createComputer($this->root_entity_id);
        $this->child_target = $this->createComputer($child_entity_id);
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    public function testValidReferenceIsAccepted(): void
    {
        $this->assertTrue($this->validate(Computer::class, $this->root_target->getID()));
    }

    public function testUnknownItemIsRejected(): void
    {
        $this->assertFalse($this->validate(Computer::class, 999999));
        $this->assertReferenceError();
    }

    public function testItemtypeOutsideAllowedValuesIsRejected(): void
    {
        $location = $this->createItem(Location::class, [
            'name'        => 'Location ' . $this->getUniqueString(),
            'entities_id' => $this->root_entity_id,
        ]);

        $this->assertFalse($this->validate(Location::class, $location->getID()));
        $this->assertReferenceError();
    }

    public function testReferenceToItemWithoutReadRightIsRejected(): void
    {
        $this->setEntity($this->root_entity_id, false);

        $this->assertFalse($this->validate(Computer::class, $this->child_target->getID()));
        $this->assertReferenceError();
    }

    public function testUnchangedReferenceStaysAcceptedWithoutReadRight(): void
    {
        $this->assertTrue($this->container->updateFieldsValues(
            $this->buildData(Computer::class, $this->child_target->getID()),
            Computer::class,
            false,
        ));

        $this->setEntity($this->root_entity_id, false);

        $this->assertTrue($this->validate(Computer::class, $this->child_target->getID()));
    }

    private function validate(string $itemtype, int $items_id): bool
    {
        return PluginFieldsContainer::validateValues($this->buildData($itemtype, $items_id), Computer::class, false);
    }

    private function buildData(string $itemtype, int $items_id): array
    {
        return [
            'plugin_fields_containers_id'        => $this->container->getID(),
            'itemtype'                           => Computer::class,
            'items_id'                           => $this->holder->getID(),
            'itemtype_' . $this->field_name      => $itemtype,
            'items_id_' . $this->field_name      => $items_id,
        ];
    }

    private function assertReferenceError(): void
    {
        $this->hasSessionMessages(ERROR, ['Some item fields reference an invalid item : Linked computer']);
    }

    private function createComputer(int $entities_id): Computer
    {
        $computer = $this->createItem(Computer::class, [
            'name'        => 'Computer ' . $this->getUniqueString(),
            'entities_id' => $entities_id,
        ]);
        $this->assertInstanceOf(Computer::class, $computer);

        return $computer;
    }
}
