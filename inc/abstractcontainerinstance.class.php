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

abstract class PluginFieldsAbstractContainerInstance extends CommonDBTM
{
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        $field_id = $options['searchopt']['pfields_fields_id'] ?? null;

        $field_specs = new PluginFieldsField();
        if ($field_id !== null && $field_specs->getFromDB($field_id)) {
            $dropdown_matches = [];
            if (
                preg_match('/^dropdown-(?<class>.+)$/i', $field_specs->fields['type'], $dropdown_matches) === 1
                && $field_specs->fields['multiple']
            ) {
                $itemtype = $dropdown_matches['class'];
                if (!is_a($itemtype, CommonDBTM::class, true)) {
                    return ''; // Itemtype not exists (maybe a deactivated plugin)
                }

                if (empty($values[$field])) {
                    return ''; // Value not defined
                }
                $values = json_decode($values[$field]);
                if (!is_array($values)) {
                    return ''; // Invalid value
                }

                $names = [];
                foreach ($values as $id) {
                    $item = new $itemtype();
                    if ($item->getFromDB($id)) {
                        $names[] = $item->getName();
                    }
                }

                return implode($options['separator'] ?? '<br />', $names);
            } elseif (
                $field_specs->fields['type'] === 'dropdown'
                && $field_specs->fields['multiple']
            ) {
                $itemtype = PluginFieldsDropdown::getClassname($field_specs->fields['name']);
                if (empty($values[$field])) {
                    return ''; // Value not defined
                }
                $values = json_decode($values[$field]);
                if (!is_array($values)) {
                    return ''; // Invalid value
                }

                return implode(
                    $options['separator'] ?? '<br />',
                    Dropdown::getDropdownArrayNames($itemtype::getTable(), $values)
                );
            }
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }
}
