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
                $fields[sprintf('itemtype_%s', $field_name)] = "varchar(100) DEFAULT NULL";
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
}
