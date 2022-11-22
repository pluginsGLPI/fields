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
    // Display correct label
    echo Html::scriptBlock(<<<JAVASCRIPT
        $('#plugin_fields_default_value_label_{$rand}').hide();
        $('#plugin_fields_allowed_values_label_{$rand}').show();
        $('#plugin_fields_multiple_dropdown_label_{$rand}').hide();
        $('#plugin_fields_multiple_dropdown_field_{$rand}').hide();
JAVASCRIPT
    );

    // Display "allowed values" field
    if ($field->isNewItem()) {
        Dropdown::showFromArray('allowed_values', PluginFieldsToolbox::getGlpiItemtypes(), [
            'display_emptychoice'   => true,
            'multiple' => true
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
} else {
    // Display correct label
    echo Html::scriptBlock(<<<JAVASCRIPT
        $('#plugin_fields_default_value_label_{$rand}').show();
        $('#plugin_fields_allowed_values_label_{$rand}').hide();
        $('#plugin_fields_multiple_dropdown_label_{$rand}').hide();
        $('#plugin_fields_multiple_dropdown_field_{$rand}').hide();
JAVASCRIPT
    );

    // Display "default values" field
    if (preg_match('/^dropdown-.+/', $type) === 1) {
        echo Html::scriptBlock(<<<JAVASCRIPT
        $('#plugin_fields_multiple_dropdown_label_{$rand}').show();
        $('#plugin_fields_multiple_dropdown_field_{$rand}').show();
JAVASCRIPT
        );
        if ($field->fields['multiple_dropdown'] == 1 || $field->fields['multiple_dropdown'] == 0) {
            Ajax::updateItem(
                "plugin_fields_specific_fields_$rand",
                "../ajax/field_is_multiple.php",
                [
                    'id'                => $id,
                    'is_multiple'       => $field->fields['multiple_dropdown'],
                    'rand'              => $rand,
                    'type'              => $type,
                ]
            );
        } else {
            Ajax::updateItemOnSelectEvent(
                "dropdown_multiple_dropdown$rand",
                "plugin_fields_specific_fields_$rand",
                "../ajax/field_is_multiple.php",
                [
                    'id'                => $id,
                    'is_multiple'       => '__VALUE__',
                    'rand'              => $rand,
                    'type'              => $type,
                ]
            );
        }
        echo Html::scriptBlock(<<<JAVASCRIPT
                $(
                    function () {
                        $('#dropdown_multiple_dropdown$rand').trigger('change');
                    }
                );
JAVASCRIPT
        );
    } elseif ($type == 'dropdown') {
        if ($field->isNewItem()) {
            echo '<em class="form-control-plaintext">';
            echo __s('Default value will be configurable once field will be created.', 'fields');
            echo '</em>';
        } else {
            Dropdown::show(
                PluginFieldsDropdown::getClassname($field->fields['name']),
                [
                    'name'            => 'default_value',
                    'value'           => $field->fields['default_value'],
                    'entity_restrict' => -1,
                    'rand'            => $rand,
                ]
            );
        }
    } else {
        echo Html::input(
            'default_value',
            [
                'value' => $field->fields['default_value'],
            ]
        );
    }
    if (in_array($type, ['date', 'datetime'])) {
        echo '<i class="pointer fa fa-info" title="' . __s("You can use 'now' for date and datetime field") . '"></i>';
    }
}
