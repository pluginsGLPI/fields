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
use PluginFieldsContainerDisplayCondition;
use Search;

final class ContainerDisplayConditionTest extends DbTestCase
{
    public function setUp(): void
    {
        GLPITestCase::setUp();
        $this->login();
    }

    public function tearDown(): void
    {
        GLPITestCase::tearDown();
    }

    public function testGetRawValueOnCustomAssetTypeAndModel(): void
    {
        $definition = $this->initAssetDefinition();
        $asset_class = $definition->getAssetClassName();

        $type  = $this->createItem($definition->getAssetTypeClassName(), ['name' => 'Laptop']);
        $model = $this->createItem($definition->getAssetModelClassName(), ['name' => 'ProBook']);

        $type_table  = $definition->getAssetTypeClassName()::getTable();
        $model_table = $definition->getAssetModelClassName()::getTable();

        $type_so_id  = null;
        $model_so_id = null;
        foreach (Search::getOptions($asset_class) as $so_id => $so) {
            if (($so['table'] ?? null) === $type_table) {
                $type_so_id = $so_id;
            }

            if (($so['table'] ?? null) === $model_table) {
                $model_so_id = $so_id;
            }
        }

        ob_start();
        PluginFieldsContainerDisplayCondition::getRawValue($type_so_id, $asset_class, $type->getID());
        $this->assertSame('Laptop', ob_get_clean());

        ob_start();
        PluginFieldsContainerDisplayCondition::getRawValue($model_so_id, $asset_class, $model->getID());
        $this->assertSame('ProBook', ob_get_clean());
    }
}
