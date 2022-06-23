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

use Glpi\Http\Response;

if (isset($_GET['action']) && $_GET['action'] === 'get_fields_html') {
    $containers_id = $_GET['id'];
    $itemtype      = $_GET['itemtype'];
    $items_id      = (int)$_GET['items_id'];
    $type          = $_GET['type'];
    $subtype       = $_GET['subtype'];
    $input         = $_GET['input'];

    $item = new $itemtype();
    if ($items_id > 0 && !$item->getFromDB($items_id)) {
        Response::sendError(404, 'Not Found');
    }
    $item->input = $input;

    $display_condition = new PluginFieldsContainerDisplayCondition();
    if ($display_condition->computeDisplayContainer($item, $containers_id)) {
        PluginFieldsField::showDomContainer(
            $containers_id,
            $item,
            $type,
            $subtype
        );
    } else {
        echo "";
    }
} else {
    Response::sendError(404, 'Not Found');
}
