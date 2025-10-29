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
use PluginFieldsContainer;
use PluginFieldsField;

trait FieldTestTrait
{
    /** @var PluginFieldsContainer[] */
    private static array $createdContainers = [];
    /** @var PluginFieldsField[] */
    private static array $createdFields = [];

    public function tearDownFieldTest(): void
    {
        // Re-login to ensure we are logged in
        $this->login();

        // Clean created containers
        array_map(
            fn(PluginFieldsContainer $container) => $container->delete($container->fields, true),
            self::$createdContainers,
        );
        self::$createdContainers = [];

        // Clean created fields
        array_map(
            fn(PluginFieldsField $field) => $field->delete($field->fields, true),
            self::$createdFields,
        );
        self::$createdFields = [];

        /** @var DBmysql $DB */
        global $DB;
        $DB->clearSchemaCache();
    }

    public function createFieldContainer(array $inputs): PluginFieldsContainer
    {
        // Re-login to ensure we are logged in
        $this->login();

        $container = $this->createItem(PluginFieldsContainer::class, $inputs, ['itemtypes']);
        self::$createdContainers[] = $container;

        // Re-initialize fields plugin to register new container logic
        plugin_init_fields();

        // Clear DB schema cache to avoid issues with new container
        /** @var DBmysql $DB */
        global $DB;
        $DB->clearSchemaCache();

        return $container;
    }

    public function createField(array $inputs): PluginFieldsField
    {
        // Re-login to ensure we are logged in
        $this->login();

        $field = $this->createItem(PluginFieldsField::class, $inputs, ['allowed_values', 'question_types']);
        self::$createdFields[] = $field;

        // Re-initialize fields plugin to register new field logic
        plugin_init_fields();

        // Clear DB schema cache to avoid issues with new field
        /** @var DBmysql $DB */
        global $DB;
        $DB->clearSchemaCache();

        return $field;
    }
}
