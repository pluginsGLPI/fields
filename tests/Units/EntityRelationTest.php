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
use Entity;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use PluginFieldsContainer;
use PluginFieldsDropdown;
use PluginFieldsField;

require_once __DIR__ . '/../FieldTestCase.php';

final class EntityRelationTest extends DbTestCase
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

    public function testContainerAndDropdownValuesAreMovedWhenTheirEntityIsReplaced(): void
    {
        $this->login();
        $root_id = getItemByTypeName(Entity::class, '_test_root_entity', true);

        $entity = $this->createItem(Entity::class, [
            'name'        => __FUNCTION__ . ' ' . $this->getUniqueString(),
            'entities_id' => $root_id,
        ]);

        // Short label: the values table name is derived from it and limited to 64 chars
        $container = $this->createFieldContainer([
            'label'        => 'Entity relation ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => $entity->getID(),
            'is_recursive' => 1,
        ]);
        $field = $this->createField([
            'label'                                     => 'Entity relation dropdown',
            'type'                                      => 'dropdown',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ], ['default_value']);

        $dropdown_class = PluginFieldsDropdown::getClassname($field->fields['name']);
        $dropdown_value = $this->createItem($dropdown_class, [
            'name'        => __FUNCTION__,
            'entities_id' => $entity->getID(),
        ]);

        $this->assertTrue($entity->isUsed());

        $this->assertTrue($entity->delete(['id' => $entity->getID(), '_replace_by' => $root_id], true));

        $this->assertTrue($container->getFromDB($container->getID()));
        $this->assertSame($root_id, $container->fields['entities_id']);
        $this->assertTrue($dropdown_value->getFromDB($dropdown_value->getID()));
        $this->assertSame($root_id, $dropdown_value->fields['entities_id']);
    }
}
