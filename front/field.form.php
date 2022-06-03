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

include("../../../inc/includes.php");

if (empty($_GET["id"])) {
    $_GET["id"] = "";
}

Session::checkRight('entity', READ);

$field = new PluginFieldsField();

if (isset($_POST["add"])) {
    $field->check(-1, CREATE, $_POST);
    $field->add($_POST);
    Html::back();
} else if (isset($_POST["delete"])) {
    $field->check($_POST['id'], DELETE);
    $field->delete($_POST);
    Html::back();
} else if (isset($_REQUEST["purge"])) {
    $field->check($_REQUEST['id'], PURGE);
    $field->delete($_REQUEST, 1);
    $field->redirectToList();
} else if (isset($_POST["update"])) {
    $field->check($_POST['id'], UPDATE);
    $field->update($_POST);
    Html::back();
} else if (isset($_GET["id"])) {
    $field->check($_GET['id'], READ);

    Html::header(PluginFieldsField::getTypeName(1), $_SERVER['PHP_SELF']);

    $field->getFromDB($_GET['id']);
    $field->display(['id'        => $_GET['id'],
        'parent_id' => $field->fields['plugin_fields_containers_id']
    ]);

    Html::footer();
}
