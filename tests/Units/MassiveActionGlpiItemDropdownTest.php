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
use Location;
use MassiveAction;
use PluginFieldsContainer;
use ReflectionClass;
use Glpi\Search\SearchOption;

require_once __DIR__ . '/../FieldTestCase.php';

/**
 * Reproduces the bug where a massive action "update" of one field wipes a
 * sibling "dropdown-<GlpiItemtype>" field (e.g. a multi-select referencing
 * Location) in the same container, because PluginFieldsContainer::populateData()
 * unconditionally blanks it to [] whenever it's absent from the current
 * request's input - unlike the plain "dropdown" type, which is guarded
 * against this (fixed in #795/#974).
 */
final class MassiveActionGlpiItemDropdownTest extends DbTestCase
{
    use FieldTestTrait;

    public function setUp(): void
    {
        GLPITestCase::setUp();
        $this->login();
    }

    public function tearDown(): void
    {
        unset($_REQUEST['massiveaction'], $_POST);
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    private function buildMassiveAction(array $post, string $itemtype, int $id): MassiveAction
    {
        $ref = new ReflectionClass(MassiveAction::class);
        $ma = $ref->newInstanceWithoutConstructor();
        $ma->POST = $post;

        $set = static function (string $name, $value) use ($ref, $ma): void {
            $prop = $ref->getProperty($name);
            $prop->setValue($ma, $value);
        };

        $set('action', 'update');
        $set('done', []);
        $set('nb_done', 0);
        $set('current_itemtype', null);
        $set('remainings', [$itemtype => [$id => $id]]);
        $set('results', ['ok' => 0, 'noaction' => 0, 'ko' => 0, 'noright' => 0, 'messages' => []]);
        $set('start_time', microtime(true));

        return $ma;
    }

    public function testMassiveUpdateOfOtherFieldDoesNotWipeGlpiItemDropdown(): void
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

        $glpi_item_field = $this->createItem(PluginFieldsField::class, [
            'label'                                       => 'Locations',
            'type'                                        => 'dropdown-' . Location::class,
            'multiple'                                    => 1,
            'default_value'                               => [],
            PluginFieldsContainer::getForeignKeyField()   => $container->getID(),
            'ranking'                                     => 1,
            'is_active'                                   => 1,
            'is_readonly'                                 => 0,
        ], ['allowed_values', 'question_types', 'default_value']);
        $glpi_item_field_name = $glpi_item_field->fields['name'];

        $text_field = $this->createField([
            'label'                                       => 'Other Field',
            'type'                                        => 'text',
            PluginFieldsContainer::getForeignKeyField()   => $container->getID(),
            'ranking'                                     => 2,
            'is_active'                                   => 1,
            'is_readonly'                                 => 0,
        ]);
        $text_field_name = $text_field->fields['name'];

        $location1 = $this->createItem(Location::class, ['name' => $this->getUniqueString(), 'entities_id' => 0]);
        $location2 = $this->createItem(Location::class, ['name' => $this->getUniqueString(), 'entities_id' => 0]);

        $entities_id = reset($_SESSION['glpiactiveentities']);
        $asset = $this->createItem($asset_class, ['name' => 'Test asset', 'entities_id' => $entities_id]);

        $so = SearchOption::getOptionsForItemtype($asset_class);
        $glpi_item_so_index = null;
        $text_so_index = null;
        foreach ($so as $idx => $opt) {
            if (($opt['linkfield'] ?? null) === $glpi_item_field_name || ($opt['field'] ?? null) === $glpi_item_field_name) {
                $glpi_item_so_index = $idx;
            }

            if (($opt['linkfield'] ?? null) === $text_field_name) {
                $text_so_index = $idx;
            }
        }

        $this->assertNotNull($glpi_item_so_index, 'search option index not found for dropdown-Location field');
        $this->assertNotNull($text_so_index, 'search option index not found for text field');

        $_REQUEST['massiveaction'] = 1;

        // --- First massive action: set the multi-select Location field ---
        $_POST = [
            'common_options'         => [$asset_class . ':' . $glpi_item_so_index => $asset_class . ':' . $glpi_item_so_index],
            'search_options'         => [$asset_class => $glpi_item_so_index],
            'id_field'               => $asset_class . ':' . $glpi_item_so_index,
            'field'                  => $glpi_item_field_name,
            $glpi_item_field_name    => [$location1->getID(), $location2->getID()],
        ];
        $ma1 = $this->buildMassiveAction($_POST, $asset_class, $asset->getID());
        MassiveAction::processMassiveActionsForOneItemtype($ma1, $asset, [$asset->getID()]);

        $container_classname = PluginFieldsContainer::getClassname($asset_class, $container->fields['name']);
        $container_item = new $container_classname();
        $this->assertTrue($container_item->getFromDBByCrit(['items_id' => $asset->getID()]));
        $this->assertSame(
            [$location1->getID(), $location2->getID()],
            json_decode($container_item->fields[$glpi_item_field_name], true),
            'dropdown-Location value not correctly stored after first massive action',
        );

        // --- Second massive action: update the sibling text field only ---
        $_POST = [
            'common_options'   => [$asset_class . ':' . $text_so_index => $asset_class . ':' . $text_so_index],
            'search_options'   => [$asset_class => $text_so_index],
            'id_field'         => $asset_class . ':' . $text_so_index,
            'field'            => $text_field_name,
            $text_field_name   => 'hello',
        ];
        $ma2 = $this->buildMassiveAction($_POST, $asset_class, $asset->getID());
        MassiveAction::processMassiveActionsForOneItemtype($ma2, $asset, [$asset->getID()]);

        $container_item2 = new $container_classname();
        $this->assertTrue($container_item2->getFromDBByCrit(['items_id' => $asset->getID()]));
        $this->assertSame(
            [$location1->getID(), $location2->getID()],
            json_decode($container_item2->fields[$glpi_item_field_name], true),
            'Massive-updating the sibling text field must not wipe the previously set dropdown-Location value',
        );
    }
}
