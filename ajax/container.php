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

include('../../../inc/includes.php');
Session::checkLoginUser();

use Glpi\Http\Response;

if (isset($_GET['action']) && $_GET['action'] === 'get_fields_html') {

    $right = PluginFieldsProfile::getRightOnContainer($_SESSION['glpiactiveprofile']['id'], $_GET['id']);
    if ($right < READ) {
        Response::sendError(403, 'Forbidden');
        return;
    }

    $containers_id = $_GET['id'];
    $itemtype      = $_GET['itemtype'];
    $items_id      = (int) $_GET['items_id'];
    $type          = $_GET['type'];
    $subtype       = $_GET['subtype'];
    $input         = is_array($_GET['input'] ?? null) ? $_GET['input'] : [];

    if ($items_id > 0 && !PluginFieldsContainer::canReadTargetItem($itemtype, $items_id)) {
        Response::sendError(403, 'Forbidden');
        return;
    }

    $item = (new DbUtils())->getItemForItemtype($itemtype);

    if (!$item instanceof CommonDBTM) {
        Response::sendError(404, 'Not Found');
        return;
    }

    if ($items_id > 0) {
        if (!$item->can($items_id, READ)) {
            Response::sendError(403, 'Forbidden');
            return;
        }
    } elseif (!$item->can(0, CREATE, $input)) {
        Response::sendError(403, 'Forbidden');
        return;
    }
    $item->input = $input;

    $display_condition = new PluginFieldsContainerDisplayCondition();
    if ($display_condition->computeDisplayContainer($item, $containers_id)) {
        PluginFieldsField::showDomContainer(
            $containers_id,
            $item,
            $type,
            $subtype,
        );
    } else {
        echo '';
    }
} else {
    Response::sendError(404, 'Not Found');
}
