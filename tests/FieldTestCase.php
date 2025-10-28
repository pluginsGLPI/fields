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

namespace GlpiPlugin\Field\Tests;

use DBmysql;
use DbTestCase;
use PluginFieldsContainer;

abstract class FieldTestCase extends DbTestCase
{
    private static array $createdContainers = [];

    public function tearDown(): void
    {
        // Clean created containers
        array_map(
            fn(PluginFieldsContainer $container) => $container->delete($container->fields, true),
            self::$createdContainers,
        );
        self::$createdContainers = [];

        /** @var DBmysql $DB */
        global $DB;
        $DB->clearSchemaCache();

        parent::tearDown();
    }

    public function createFieldContainer(array $inputs): PluginFieldsContainer
    {
        $container = $this->createItem(PluginFieldsContainer::class, $inputs, ['itemtypes']);
        self::$createdContainers[] = $container;

        return $container;
    }
}
