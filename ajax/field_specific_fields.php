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
 * @copyright 2015-2022 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/fields
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');
header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();
Session::checkLoginUser();

$id   = $_POST['id'];
$type = $_POST['type'];
$rand = $_POST['rand'];

$field = new PluginFieldsField();
if ($id > 0) {
    $field->getFromDB($id);
} else {
    $field->getEmpty();
}

if ($type === 'glpi_item') {
    // Display "allowed values" field
    echo '<td>';
    echo __('Allowed values', 'fields') . ' :';
    echo '</td>';

    echo '<td style="line-height:var(--tblr-body-line-height);">';
    if ($field->isNewItem()) {
        Dropdown::showFromArray('allowed_values', PluginFieldsToolbox::getGlpiItemtypes(), [
            'display_emptychoice' => true,
            'multiple'            => true
        ]);
    } else {
        $allowed_itemtypes = !empty($field->fields['allowed_values'])
            ? json_decode($field->fields['allowed_values'])
            : [];
        echo implode(
            ', ',
            array_map(
                function ($itemtype) {
                    return is_a($itemtype, CommonDBTM::class, true)
                    ? $itemtype::getTypeName(Session::getPluralNumber())
                    : $itemtype;
                },
                $allowed_itemtypes
            )
        );
    }
    echo '</td>';
} else {
    $dropdown_matches = [];
    $is_dropdown = $type == 'dropdown' || preg_match('/^dropdown-(?<class>.+)$/', $type, $dropdown_matches) === 1;

    // Display "default value(s)" field
    echo '<td>';
    if ($is_dropdown) {
        echo __('Multiple dropdown', 'fields') . ' :';
        echo '<br />';
    }
    echo __('Default value', 'fields') . ' :';
    if (in_array($type, ['date', 'datetime'])) {
        echo '<i class="pointer fa fa-info" title="' . __s("You can use 'now' for date and datetime field") . '"></i>';
    }
    echo '</td>';

    echo '<td>';
    if ($is_dropdown) {
        $multiple = (bool)($_POST['multiple'] ?? $field->fields['multiple']);

        if ($field->isNewItem()) {
            Dropdown::showYesNo(
                'multiple',
                $multiple,
                -1,
                [
                    'rand' => $rand,
                ]
            );
        } else {
            echo Dropdown::getYesNo($multiple);
        }
        echo '<br />';

        echo '<div style="line-height:var(--tblr-body-line-height);">';
        if ($field->isNewItem() && $type == 'dropdown') {
            echo '<em class="form-control-plaintext">';
            echo __s('Default value will be configurable once field will be created.', 'fields');
            echo '</em>';
        } else {
            $itemtype = $type == 'dropdown'
                ? PluginFieldsDropdown::getClassname($field->fields['name'])
                : $dropdown_matches['class'];
            $default_value = $multiple ? json_decode($field->fields['default_value']) : $field->fields['default_value'];
            Dropdown::show(
                $itemtype,
                [
                    'name'            => 'default_value' . ($multiple ? '[]' : ''),
                    'value'           => $default_value,
                    'entity_restrict' => -1,
                    'multiple'        => $multiple,
                    'rand'            => $rand,
                ]
            );
        }
        echo '</div>';
        Ajax::updateItemOnSelectEvent(
            "dropdown_multiple$rand",
            "plugin_fields_specific_fields_$rand",
            "../ajax/field_specific_fields.php",
            [
                'id'       => $id,
                'type'     => $type,
                'multiple' => '__VALUE__',
                'rand'     => $rand,
            ]
        );
    } else {
        echo Html::input(
            'default_value',
            [
                'value' => $field->fields['default_value'],
            ]
        );
    }
    echo '</td>';
}
