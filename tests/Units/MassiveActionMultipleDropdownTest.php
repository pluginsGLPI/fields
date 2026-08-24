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

use PluginFieldsField;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use PluginFieldsContainer;
use PluginFieldsDropdown;

require_once __DIR__ . '/../FieldTestCase.php';

/**
 * Reproduces the bug where a massive action "update" of one field wipes out
 * a previously mass-updated multiple-value dropdown field in the same
 * container, because PluginFieldsContainer::populateData() re-evaluates
 * every field of the container on every update, not just the field being
 * edited.
 */
final class MassiveActionMultipleDropdownTest extends DbTestCase
{
    use FieldTestTrait;

    public function setUp(): void
    {
        GLPITestCase::setUp();
        $this->login();
    }

    public function tearDown(): void
    {
        unset($_REQUEST['massiveaction']);
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    public function testMassiveUpdateOfOtherFieldDoesNotWipeMultipleDropdown(): void
    {
        $definition = $this->initAssetDefinition('so' . substr((string) $this->getUniqueString(), 0, 6));
        $asset_class = $definition->getAssetClassName();

        $container = $this->createFieldContainer([
            'label'        => 'F',
            'type'         => 'tab',
            'itemtypes'    => [$asset_class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        // Multiple-value Fields dropdown (e.g. "Transmission")
        $dropdown_field = $this->createItem(PluginFieldsField::class, [
            'label'                                       => 'Transmission',
            'type'                                        => 'dropdown',
            'multiple'                                    => 1,
            'default_value'                               => [],
            PluginFieldsContainer::getForeignKeyField()   => $container->getID(),
            'ranking'                                     => 1,
            'is_active'                                   => 1,
            'is_readonly'                                 => 0,
        ], ['allowed_values', 'question_types', 'default_value']);
        $dropdown_field_name = $dropdown_field->fields['name'];
        $multiple_key = 'plugin_fields_' . $dropdown_field_name . 'dropdowns_id';

        // A plain sibling field in the SAME container
        $text_field = $this->createField([
            'label'                                       => 'Other Field',
            'type'                                        => 'text',
            PluginFieldsContainer::getForeignKeyField()   => $container->getID(),
            'ranking'                                     => 2,
            'is_active'                                   => 1,
            'is_readonly'                                 => 0,
        ]);
        $text_field_name = $text_field->fields['name'];

        // Two allowed dropdown values
        $dropdown_class = PluginFieldsDropdown::getClassname($dropdown_field_name);
        $value1 = $this->createItem($dropdown_class, ['name' => 'Manual', 'entities_id' => 0]);
        $value2 = $this->createItem($dropdown_class, ['name' => 'Automatic', 'entities_id' => 0]);

        $asset = $this->createItem($asset_class, ['name' => 'Test asset', 'entities_id' => 0]);

        $_REQUEST['massiveaction'] = 1;

        // First massive action: set the multiple dropdown field
        $item = new $asset_class();
        $this->assertTrue($item->update([
            'id'          => $asset->getID(),
            'c_id'        => $container->getID(),
            $multiple_key => [$value1->getID(), $value2->getID()],
        ]));

        $container_classname = PluginFieldsContainer::getClassname($asset_class, $container->fields['name']);
        $container_item = new $container_classname();
        $this->assertTrue($container_item->getFromDBByCrit(['items_id' => $asset->getID()]));
        $this->assertSame(
            [$value1->getID(), $value2->getID()],
            json_decode($container_item->fields[$multiple_key], true),
        );

        // Second massive action: update the sibling text field only
        $item2 = new $asset_class();
        $this->assertTrue($item2->update([
            'id'             => $asset->getID(),
            'c_id'           => $container->getID(),
            $text_field_name => 'hello',
        ]));

        $container_item2 = new $container_classname();
        $this->assertTrue($container_item2->getFromDBByCrit(['items_id' => $asset->getID()]));
        $this->assertSame(
            [$value1->getID(), $value2->getID()],
            json_decode($container_item2->fields[$multiple_key], true),
            'Massive-updating the sibling text field must not wipe the previously set multiple dropdown value',
        );
    }
}
