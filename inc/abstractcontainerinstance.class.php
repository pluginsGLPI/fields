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

abstract class PluginFieldsAbstractContainerInstance extends CommonDBChild
{
    public static $itemtype = 'itemtype';
    public static $items_id = 'items_id';

    public static $mustBeAttached     = false;

    /**
     * This function relies on the static property `static::$plugins_forward_entity`,
     * which should be populated using the following method (from setup):
     *
     * Plugin::registerClass(
     *     PluginFields<Itemtype><name>,
     *     ['forwardentityfrom' => <Itemtype>]
     * );
     *
     * However, the order in which plugins are loaded can affect the behavior.
     * For example, if a container is defined on a `GenericObject` itemtype and
     * the `fields` plugin initializes before the `genericobject` plugin, the
     * `itemtype` for the container will not yet exist, leading to potential issues.
     *
     * Modification of this function to meet specific requirements.
     */
    public function addNeededInfoToInput($input)
    {
        if ($this->tryEntityForwarding()) {
            $completeinput = array_merge($this->fields, $input);
            if (
                $itemToGetEntity = static::getItemFromArray(
                    static::$itemtype,
                    static::$items_id,
                    $completeinput,
                )
            ) {
                if (
                    ($itemToGetEntity instanceof CommonDBTM)
                ) {
                    if ($itemToGetEntity->isEntityAssign()) {
                        $input['entities_id']  = $itemToGetEntity->getEntityID();
                    }

                    if ($itemToGetEntity->maybeRecursive()) {
                        $input['is_recursive'] = intval($itemToGetEntity->isRecursive());
                    }
                }
            }
        }
        return $input;
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
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
                $display_with = [];
                if ($itemtype == User::class) {
                    $display_with = ['realname', 'firstname'];
                }

                return Dropdown::show($itemtype, ['displaywith' => $display_with, 'name' => $name, 'display' => false]);
            } elseif (
                $field_specs->fields['type'] === 'dropdown'
                && $field_specs->fields['multiple']
            ) {
                $itemtype = PluginFieldsDropdown::getClassname($field_specs->fields['name']);

                return Dropdown::show($itemtype, ['name' => $name, 'display' => false]);
            }
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

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
                    Dropdown::getDropdownArrayNames($itemtype::getTable(), $values),
                );
            }
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }
}
