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
use PluginFieldsField;
use Search;

require_once __DIR__ . '/../FieldTestCase.php';

/**
 * Reproduces the bug where the massive action "update" widget for a Fields
 * plugin field is never rendered for CustomAsset itemtypes (namespaced
 * classes like Glpi\CustomAsset\XxxAsset), because PluginFieldsField::showSingle()
 * builds a LIKE query against the un-escaped itemtype string.
 */
final class MassiveActionCustomAssetTest extends DbTestCase
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

    public function testShowSingleDisplaysFieldForCustomAsset(): void
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

        $field = $this->createField([
            'label'                                       => 'Custom Asset Field',
            'type'                                        => 'text',
            PluginFieldsContainer::getForeignKeyField()   => $container->getID(),
            'ranking'                                     => 1,
            'is_active'                                   => 1,
            'is_readonly'                                 => 0,
        ]);
        $field_name = $field->fields['name'];

        $search_option = null;
        foreach (Search::getOptions($asset_class) as $so) {
            if (($so['linkfield'] ?? null) === $field_name) {
                $search_option = $so;
                break;
            }
        }

        $this->assertIsArray($search_option, 'search option not found for plugin field on custom asset');

        ob_start();
        $result = PluginFieldsField::showSingle($asset_class, $search_option, true);
        $html = ob_get_clean();

        $this->assertTrue($result, 'showSingle() should find the field container for a CustomAsset itemtype');
        $this->assertStringContainsString($field_name, $html);
    }
}
