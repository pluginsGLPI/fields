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

if (
    !array_key_exists('container_id', $_POST)
    || !array_key_exists('old_order', $_POST)
    || !array_key_exists('new_order', $_POST)
) {
    // Missing input
    exit();
}

$table        = PluginFieldsField::getTable();
$container_id = (int)$_POST['container_id'];
$old_order    = (int)$_POST['old_order'];
$new_order    = (int)$_POST['new_order'];

// Retrieve id of field to update
$field_iterator = $DB->request(
    [
        'SELECT' => 'id',
        'FROM'   => $table,
        'WHERE'  => [
            'plugin_fields_containers_id' => $container_id,
            'ranking'                     => $old_order,
        ],
    ]
);

if (0 === $field_iterator->count()) {
    // Unknown field
    exit();
}

$field_id = $field_iterator->current()['id'];

// Move all elements to their new ranking
if ($old_order < $new_order) {
    $DB->update(
        $table,
        [
            'ranking' => new \QueryExpression($DB->quoteName('ranking') . ' - 1'),
        ],
        [
            'plugin_fields_containers_id' => $container_id,
            ['ranking' => ['>',  $old_order]],
            ['ranking' => ['<=', $new_order]],
        ]
    );
} else {
    $DB->update(
        $table,
        [
            'ranking' => new \QueryExpression($DB->quoteName('ranking') . ' + 1'),
        ],
        [
            'plugin_fields_containers_id' => $container_id,
            ['ranking' => ['<',  $old_order]],
            ['ranking' => ['>=', $new_order]],
        ]
    );
}

// Update current element
$DB->update(
    $table,
    [
        'ranking' => $new_order,
    ],
    [
        'id' => $field_id,
    ]
);
