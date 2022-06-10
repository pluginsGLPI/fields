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
 * @copyright Copyright (C) 2013-2022 by Fields plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/fields
 * -------------------------------------------------------------------------
 */

class PluginFieldsMigration extends Migration
{
    public function displayMessage($msg)
    {
        Session::addMessageAfterRedirect($msg);
    }

    /**
     * Return SQL fields corresponding to given additionnal field.
     *
     * @param string $field_name
     * @param string $field_type
     *
     * @return array
     */
    public static function getSQLFields(string $field_name, string $field_type): array
    {
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $fields = [];
        switch (true) {
            case $field_type === 'header':
                // header type is for read-only display purpose only and has no SQL field
                break;
            case $field_type === 'dropdown':
            case preg_match('/^dropdown-.+/i', $field_type):
                if ($field_type === 'dropdown') {
                    $field_name = getForeignKeyFieldForItemType(PluginFieldsDropdown::getClassname($field_name));
                }
                $fields[$field_name] = "INT {$default_key_sign} NOT NULL DEFAULT 0";
                break;
            case $field_type === 'textarea':
            case $field_type === 'url':
                $fields[$field_name] = 'TEXT DEFAULT NULL';
                break;
            case $field_type === 'yesno':
                $fields[$field_name] = 'INT NOT NULL DEFAULT 0';
                break;
            case $field_type === 'glpi_item':
                $fields[sprintf('itemtype_%s', $field_name)] = 'varchar(100) DEFAULT NULL';
                $fields[sprintf('items_id_%s', $field_name)] = "int {$default_key_sign} NOT NULL DEFAULT 0";
                break;
            case $field_type === 'date':
            case $field_type === 'datetime':
            case $field_type === 'number':
            case $field_type === 'text':
            default:
                $fields[$field_name] = 'VARCHAR(255) DEFAULT NULL';
                break;
        }

        return $fields;
    }

    /**
     * An issue affected field removal in 1.15.0, 1.15.1 and 1.15.2.
     * Using these versions, removing a field from a container would drop the
     * field from glpi_plugin_fields_fields but not from the custom container
     * table
     *
     * This function looks into containers tables for fields that
     * should have been removed and list them.
     * If parameter $fix is true, fields are deleted from database.
     *
     * @param bool $fix
     *
     * @return array
     */
    public static function checkDeadFields(bool $fix): array
    {
        /** @var DBMysql $DB */
        global $DB;

        $dead_fields = [];

        // For each existing container
        $containers = (new PluginFieldsContainer())->find([]);
        foreach ($containers as $row) {
            // Get expected fields
            $valid_fields = self::getValidFieldsForContainer($row['id']);

            // Read itemtypes and container name
            $itemtypes = importArrayFromDB($row['itemtypes']);
            $name = $row['name'];

            // One table to handle per itemtype
            foreach ($itemtypes as $itemtype) {
                // Build table name
                $table = getTableForItemType("PluginFields{$itemtype}{$name}");

                if (!$DB->tableExists($table)) {
                    // Missing table; skip (abnormal)
                    continue;
                }

                // Get the actual fields defined in the container table
                $found_fields = self::getCustomFieldsInContainerTable($table);

                // Compute which fields should be removed
                $fields_to_drop = array_diff($found_fields, $valid_fields);

                if (count($fields_to_drop) > 0) {
                    $dead_fields[$table] = $fields_to_drop;
                }
            }
        }

        if ($fix) {
            $migration = new PluginFieldsMigration(0);

            foreach ($dead_fields as $table => $fields) {
                foreach ($fields as $field) {
                    $migration->dropField($table, $field);
                }
            }

            $migration->executeMigration();
        }

        return $dead_fields;
    }

    /**
     * Get all fields defined for a container in glpi_plugin_fields_fields
     *
     * @param int $container_id Id of the container
     *
     * @return array
     */
    private static function getValidFieldsForContainer(int $container_id): array
    {
        $valid_fields = [];

        // For each defined fields in the given container
        $fields = (new PluginFieldsField())->find(['plugin_fields_containers_id' => $container_id]);
        foreach ($fields as $row) {
            $fields = self::getSQLFields($row['name'], $row['type']);
            array_push($valid_fields, ...array_keys($fields));
        }

        return $valid_fields;
    }

    /**
     * Get custom fields in a given container table
     * This means all fields found in the table expect those defined in
     * $basic_fields
     *
     * @param string $table
     *
     * @return array
     */
    private static function getCustomFieldsInContainerTable(
        string $table
    ): array {
        /** @var DBMysql $DB */
        global $DB;

        // Read table fields
        $fields = $DB->listFields($table);

        // Reduce to fields name only
        $fields = array_column($fields, "Field");

        // Remove basic fields
        $basic_fields = [
            'id',
            'items_id',
            'itemtype',
            'plugin_fields_containers_id',
        ];
        return array_filter(
            $fields,
            function (string $field) use ($basic_fields) {
                return !in_array($field, $basic_fields);
            }
        );
    }
}
