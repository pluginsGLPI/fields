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

if (isset($_GET['action'])) {
    if ($_GET['action'] === 'get_add_form') {
        $status_override = new PluginFieldsContainerDisplayCondition();
        $status_override->showForm(0, $_GET);
    } else if ($_GET['action'] === 'get_edit_form') {
        $status_override = new PluginFieldsContainerDisplayCondition();
        $status_override->getFromDB($_GET['id']);
        $status_override->showForm($_GET['id'], $_GET);
    }
} else if (isset($_POST['action'])) {
    if ($_POST['action'] === 'get_itemtype_so') {
        if (isset($_POST['itemtype']) && class_exists($_POST['itemtype'])) {
            echo PluginFieldsContainerDisplayCondition::showItemtypeFieldForm($_POST['itemtype']) ;
        } else {
            echo "";
        }
    } else if ($_POST['action'] === 'get_condition_switch_so') {
        if (isset($_POST['search_option_id']) && (isset($_POST['itemtype']) && class_exists($_POST['itemtype']))) {
            echo PluginFieldsContainerDisplayCondition::showSearchOptionCondition($_POST['search_option_id'], $_POST['itemtype']);
        } else {
            echo "";
        }
    }
} else {
    http_response_code(400);
    die();
}
