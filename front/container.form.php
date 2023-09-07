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

include("../../../inc/includes.php");

if (empty($_GET["id"])) {
    $_GET["id"] = "";
}

$container = new PluginFieldsContainer();

if (isset($_POST["add"])) {
    $container->check(-1, CREATE, $_POST);
    $newID = $container->add($_POST);
    Html::redirect(PLUGINFIELDS_WEB_DIR . "/front/container.form.php?id=$newID");
} else if (isset($_POST["delete"])) {
    $container->check($_POST['id'], DELETE);
    $ok = $container->delete($_POST);
    Html::redirect(PLUGINFIELDS_WEB_DIR . "/front/container.php");
} else if (isset($_REQUEST["purge"])) {
    $container->check($_REQUEST['id'], PURGE);
    $container->delete($_REQUEST, 1);
    Html::redirect(PLUGINFIELDS_WEB_DIR . "/front/container.php");
} else if (isset($_POST["update"])) {
    $container->check($_POST['id'], UPDATE);
    $container->update($_POST);
    Html::back();
} else if (isset($_POST["update_fields_values"])) {
    $right = PluginFieldsProfile::getRightOnContainer($_SESSION['glpiactiveprofile']['id'], $_POST['plugin_fields_containers_id']);
    if ($right > READ) {
        $container->updateFieldsValues($_REQUEST, $_REQUEST['itemtype'], false);
    }
    Html::back();
} else {
    Html::header(
        __("Additional fields", "fields"),
        $_SERVER['PHP_SELF'],
        "config",
        "pluginfieldsmenu",
        "fieldscontainer"
    );
    $container->display(['id' => $_GET["id"]]);
    Html::footer();
}
