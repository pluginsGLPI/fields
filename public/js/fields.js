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

// Load a subitem form into a target element. Used by links and table rows.
$(document).on('click', '[data-fields-load-target]', function (e) {
    if ($(this).is('a')) {
        e.preventDefault();
    }
    $($(this).attr('data-fields-load-target')).load(
        $(this).attr('data-fields-load-url'),
        JSON.parse($(this).attr('data-fields-load-params'))
    );
});

// Container display conditions list
$(document).on('click', '[data-fields-conditions] button[name="edit"]', function (e) {
    const row = $(e.currentTarget).closest('tr');
    const id = row.find('input[name="id"]').val();
    const container_id = row.find('input[name="plugin_fields_containers_id"]').val();

    glpi_ajax_dialog(
        {
            title: __('Edit condition to hide block', 'fields'),
            url: CFG_GLPI.root_doc + '/plugins/fields/ajax/container_display_condition.php',
            method: 'get',
            params: {
                action: 'get_edit_form',
                id: id,
                plugin_fields_containers_id: container_id
            },
            dialogclass: 'modal-xl'
        }
    );
});

$(document).on('click', '[data-fields-conditions] button[name="switch_add"]', function (e) {
    const container_id = $(e.currentTarget).attr('data-container-id');

    glpi_ajax_dialog(
        {
            title: __('Add condition to hide block', 'fields'),
            url: CFG_GLPI.root_doc + '/plugins/fields/ajax/container_display_condition.php',
            method: 'get',
            params: {
                action: 'get_add_form',
                plugin_fields_containers_id: container_id
            },
            dialogclass: 'modal-xl'
        }
    );
});

// Status overrides list
$(document).on('click', '[data-fields-status-overrides] button[name="edit"]', function (e) {
    const row = $(e.currentTarget).closest('tr');
    const id = row.find('input[name="id"]').val();
    const container_id = row.find('input[name="container_id"]').val();

    glpi_ajax_dialog(
        {
            title: __('Edit status override', 'fields'),
            url: CFG_GLPI.root_doc + '/plugins/fields/ajax/status_override.php',
            method: 'get',
            params: {
                action: 'get_edit_form',
                id: id,
                container_id: container_id
            },
            dialogclass: 'modal-xl'
        }
    );
});

$(document).on('click', '[data-fields-status-overrides] button[name="switch_add"]', function (e) {
    const container_id = $(e.currentTarget).attr('data-container-id');

    glpi_ajax_dialog(
        {
            title: __('Add new status override', 'fields'),
            url: CFG_GLPI.root_doc + '/plugins/fields/ajax/status_override.php',
            method: 'get',
            params: {
                action: 'get_add_form',
                container_id: container_id
            },
            dialogclass: 'modal-xl'
        }
    );
});

// Status override form: refresh the status list when the item type changes
$(document).on('change', 'form[data-fields-status-override] select[name="itemtype"]', function () {
    const form = $(this).closest('form');
    const rand = form.attr('data-fields-status-override');

    $.ajax({
        url: CFG_GLPI.root_doc + '/plugins/fields/ajax/status_override.php',
        method: 'GET',
        data: {
            action: 'get_status_dropdown',
            itemtype: $(this).val()
        }
    }).then((result) => {
        $('#status_inner_container' + rand).html(result);
    });
});
