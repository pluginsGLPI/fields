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
$multiple_dropdown = $_POST['is_multiple'] == 1;

$field = new PluginFieldsField();
if ($id > 0) {
    $field->getFromDB($id);
} else {
    $field->getEmpty();
}

$multiple_default = ($field->fields['default_value'] ? json_decode($field->fields['default_value']) : []);

Dropdown::show(
    preg_replace('/^dropdown-/', '', $type),
    [
        'name'                => $multiple_dropdown ? 'multiple_default_value[]' : 'default_value',
        'value'               => $multiple_dropdown ? $multiple_default : $field->fields['default_value'],
        'entity_restrict'     => -1,
        'rand'                => $rand,
        'multiple'            => $multiple_dropdown,
        'display_emptychoice' => $multiple_dropdown ? false : true,
        'width'               => "100%",
    ]
);
