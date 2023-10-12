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

use Glpi\Api\HL\Doc\Schema;

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_fields_install()
{
    // Regenerating tables/files can consume lot of memory/time
    $memory_limit       = (int)Toolbox::getMemoryLimit();
    $max_execution_time = ini_get('max_execution_time');
    if ($memory_limit > 0 && $memory_limit < (512 * 1024 * 1024)) {
        ini_set('memory_limit', '512M');
    }
    if ($max_execution_time > 0 && $max_execution_time < 300) {
        ini_set('max_execution_time', '300');
    }

    $plugin_fields = new Plugin();
    $plugin_fields->getFromDBbyDir('fields');
    $version = $plugin_fields->fields['version'];


    $migration = new Migration($version);
    if (isCommandLine()) {
        echo __("MySQL tables installation", "fields") . "\n";
    } else {
        echo "<center>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th>" . __("MySQL tables installation", "fields") . "<th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td align='center'>";
    }

    $classesToInstall = [
        PluginFieldsContainer::class,
        PluginFieldsContainerDisplayCondition::class,
        PluginFieldsDropdown::class,
        PluginFieldsField::class,
        PluginFieldsLabelTranslation::class,
        PluginFieldsProfile::class,
        PluginFieldsStatusOverride::class,
    ];

    // First, install base data
    foreach ($classesToInstall as $class) {
        if (method_exists($class, 'installBaseData')) {
            $class::installBaseData($migration, $version);
        }
    }
    $migration->executeMigration();

    // Then process specific user classes/tables
    foreach ($classesToInstall as $class) {
        if (method_exists($class, 'installUserData')) {
            $class::installUserData($migration, $version);
        }
    }
    $migration->executeMigration();

    if (!isCommandLine()) {
        echo "</td>";
        echo "</tr>";
        echo "</table></center>";
    }
    return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_fields_uninstall()
{

    if (!class_exists('PluginFieldsProfile')) {
        Session::addMessageAfterRedirect(
            __("The plugin can't be uninstalled when the plugin is disabled", 'fields'),
            true,
            WARNING,
            true
        );
        return false;
    }

    $_SESSION['uninstall_fields'] = true;

    echo "<center>";
    echo "<table class='tab_cadre_fixe'>";
    echo "<tr><th>" . __("MySQL tables uninstallation", "fields") . "<th></tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td align='center'>";

    $classesToUninstall = [
        'PluginFieldsDropdown',
        'PluginFieldsContainer',
        'PluginFieldsLabelTranslation',
        'PluginFieldsField',
        'PluginFieldsProfile',
        'PluginFieldsStatusOverride',
        'PluginFieldsContainerDisplayCondition'
    ];

    foreach ($classesToUninstall as $class) {
        if ($plug = isPluginItemType($class)) {
            $dir  = PLUGINFIELDS_DIR . "/inc/";
            $item = strtolower($plug['class']);

            if (file_exists("$dir$item.class.php")) {
                include_once("$dir$item.class.php");
                if (!call_user_func([$class,'uninstall'])) {
                    return false;
                }
            }
        }
    }

    echo "</td>";
    echo "</tr>";
    echo "</table></center>";

    unset($_SESSION['uninstall_fields']);

    // clean display preferences
    $pref = new DisplayPreference();
    $pref->deleteByCriteria([
        'itemtype' => ['LIKE' , 'PluginFields%']
    ]);

    return true;
}

function plugin_fields_getAddSearchOptions($itemtype)
{
    if (
        isset($_SESSION['glpiactiveentities'])
        && is_array($_SESSION['glpiactiveentities'])
        && count($_SESSION['glpiactiveentities']) > 0
    ) {
        $itemtypes = PluginFieldsContainer::getEntries('all');
        if (in_array($itemtype, $itemtypes)) {
            return PluginFieldsContainer::getAddSearchOptions($itemtype);
        }
    }

    return null;
}

// Define Dropdown tables to be manage in GLPI :
function plugin_fields_getDropdown()
{
    $dropdowns = [];

    $field_obj = new PluginFieldsField();
    $fields    = $field_obj->find(['type' => 'dropdown']);
    foreach ($fields as $field) {
        $field['itemtype'] = PluginFieldsField::getType();
        $label = PluginFieldsLabelTranslation::getLabelFor($field);
        $dropdowns["PluginFields" . ucfirst($field['name']) . "Dropdown"] = $label;
    }

    asort($dropdowns);
    return $dropdowns;
}


/**** MASSIVE ACTIONS ****/

// Display specific massive actions for plugin fields
function plugin_fields_MassiveActionsFieldsDisplay($options = [])
{
    $itemtypes = PluginFieldsContainer::getEntries('all');

    if (in_array($options['itemtype'], $itemtypes)) {
        PluginFieldsField::showSingle($options['itemtype'], $options['options'], true);
        return true;
    }

    // Need to return false on non display item
    return false;
}


/**** RULES ENGINE ****/

/**
 *
 * Actions for rules
 * @since 0.84
 * @param array $params input data
 * @return array    an array of actions
 */
function plugin_fields_getRuleActions($params = [])
{
    $actions = [];

    switch ($params['rule_itemtype']) {
        case "PluginFusioninventoryTaskpostactionRule":
            $options = PluginFieldsContainer::getAddSearchOptions("Computer");
            foreach ($options as $option) {
                $actions[$option['linkfield']]['name'] = $option['name'];
                $actions[$option['linkfield']]['type'] = $option['pfields_type'];
                if ($option['pfields_type'] == 'dropdown') {
                    $actions[$option['linkfield']]['table'] = $option['table'];
                }
            }

            break;
    }

    return $actions;
}


function plugin_fields_rule_matched($params = [])
{
    global $DB;

    $container = new PluginFieldsContainer();

    switch ($params['sub_type']) {
        case "PluginFusioninventoryTaskpostactionRule":
            $agent = new PluginFusioninventoryAgent();

            if (isset($params['input']['plugin_fusioninventory_agents_id'])) {
                foreach ($params['output'] as $field => $value) {
                    // check if current field is in a tab container
                    $iterator = $DB->request([
                        'SELECT' => 'glpi_plugin_fields_containers.id',
                        'FROM'   => 'glpi_plugin_fields_containers',
                        'LEFT JOIN' => [
                            'glpi_plugin_fields_fields' => [
                                'FKEY' => [
                                    'glpi_plugin_fields_containers' => 'id',
                                    'glpi_plugin_fields_fields'     => 'plugin_fields_containers_id'
                                ]
                            ]
                        ],
                        'WHERE' => [
                            'glpi_plugin_fields_fields.name' => $field,
                        ]
                    ]);
                    if (count($iterator) > 0) {
                        $data = $iterator->current();

                        //retrieve computer
                        $agents_id = $params['input']['plugin_fusioninventory_agents_id'];
                        $agent->getFromDB($agents_id);

                        // update current field
                        $container->updateFieldsValues(
                            [
                                'plugin_fields_containers_id' => $data['id'],
                                $field                        => $value,
                                'items_id'                    => $agent->fields['computers_id']
                            ],
                            Computer::getType()
                        );
                    }
                }
            }
            break;
    }
}

function plugin_fields_giveItem($itemtype, $ID, $data, $num)
{
    $searchopt = Search::getOptions($itemtype);
    $table = $searchopt[$ID]["table"];

    //fix glpi default Search::giveItem who for empty date display "--"
    if (
        strpos($table, "glpi_plugin_fields") !== false
        && isset($searchopt[$ID]["datatype"])
        && strpos($searchopt[$ID]["datatype"], "date") !== false
        && empty($data['raw']["ITEM_$num"])
    ) {
        return " ";
    }

    return false;
}

/**
 * Load Fields classes in datainjection.
 * Called by Setup.php:44 if Datainjection is installed and active
 */
function plugin_datainjection_populate_fields()
{
    global $INJECTABLE_TYPES;

    $container = new PluginFieldsContainer();
    $found     = $container->find(['is_active' => 1]);
    foreach ($found as $values) {
        $types = json_decode($values['itemtypes']);

        foreach ($types as $type) {
            $classname = PluginFieldsContainer::getClassname($type, $values['name'], 'Injection');
            $INJECTABLE_TYPES[$classname] = 'fields';
        }
    }
}

function plugin_fields_redefine_api_schemas(array $data): array
{
    global $DB;

    $fn_fieldTypeToAPIType = static function (string $type): array {
        $type = explode('-', $type)[0];
        return match ($type) {
            'number' => [Schema::TYPE_NUMBER, Schema::FORMAT_NUMBER_FLOAT],
            'yesno' => [Schema::TYPE_BOOLEAN, Schema::FORMAT_BOOLEAN_BOOLEAN],
            'date' => [Schema::TYPE_STRING, Schema::FORMAT_STRING_DATE],
            'datetime' => [Schema::TYPE_STRING, Schema::FORMAT_STRING_DATE_TIME],
            default => [Schema::TYPE_STRING, Schema::FORMAT_STRING_STRING],
        };
    };

    $new_schemas = [];

    foreach ($data['schemas'] as &$schema) {
        if (!isset($schema['x-itemtype'])) {
            continue;
        }
        $itemtype = $schema['x-itemtype'];
        $schema_name = $itemtype . '_CustomFields';
        //Note PluginFieldsContainer::findContainer already checks permissions
        $container_id = PluginFieldsContainer::findContainer($schema['x-itemtype'], 'dom');
        if ($container_id !== null) {
            $it = $DB->request([
                'SELECT' => [
                    'glpi_plugin_fields_fields.*',
                    'glpi_plugin_fields_containers.name AS container_name',
                ],
                'FROM'   => 'glpi_plugin_fields_fields',
                'LEFT JOIN' => [
                    'glpi_plugin_fields_containers' => [
                        'ON' => [
                            'glpi_plugin_fields_fields' => 'plugin_fields_containers_id',
                            'glpi_plugin_fields_containers' => 'id'
                        ]
                    ]
                ],
                'WHERE'  => [
                    'plugin_fields_containers_id' => $container_id,
                    'glpi_plugin_fields_fields.is_active' => 1
                ]
            ]);
            if (count($it)) {
                foreach ($it as $field) {
                    if (!isset($new_schemas[$schema_name])) {
                        $new_schemas[$schema_name] = [
                            'type' => Schema::TYPE_OBJECT,
                            'properties' => []
                        ];
                    }
                    $type_format = $fn_fieldTypeToAPIType($field['type']);
                    $table = strtolower("glpi_plugin_fields_{$schema['x-itemtype']}{$field['container_name']}s");
                    $sql_field = $field['name'];
                    if (str_starts_with($field['type'], 'dropdown')) {
                        if (str_starts_with($field['type'], 'dropdown-')) {
                            $dropdown_type = explode('-', $field['type'], 2)[1];
                        } else {
                            $dropdown_type = 'PluginFields' . ucfirst($field['name']) . 'Dropdown';
                        }
                        $is_tree = is_subclass_of($dropdown_type, CommonTreeDropdown::class);
                        $new_schemas[$schema_name]['properties'][$field['name']] = [
                            'type' => Schema::TYPE_OBJECT,
                            'x-join' => [
                                'table' => $dropdown_type::getTable(), // This is the table with the desired values
                                'field' => 'id',
                                'fkey' => $dropdown_type::getForeignKeyField(),
                                'ref_join' => [
                                    'table' => $table,
                                    'fkey' => 'id',
                                    'field' => 'items_id',
                                    'condition' => [
                                        'itemtype' => $schema['x-itemtype'],
                                    ]
                                ],
                            ],
                            'properties' => [
                                'id' => [
                                    'type' => Schema::TYPE_INTEGER,
                                    'format' => Schema::FORMAT_INTEGER_INT64,
                                    'x-readonly' => true,
                                ],
                                'value' => [
                                    'type' => Schema::TYPE_STRING,
                                    'x-field' => $is_tree ? 'completename' : 'name',
                                ]
                            ]
                        ];
                    } else {
                        $new_schemas[$schema_name]['properties'][$field['name']] = [
                            'type' => $type_format[0],
                            'format' => $type_format[1],
                            'x-join' => [
                                // This is the table with the desired values
                                'table' => $table,
                                'fkey' => 'id',
                                'field' => 'items_id',
                                'condition' => [
                                    'itemtype' => $schema['x-itemtype'],
                                ]
                            ],
                            'x-field' => $sql_field,
                            'x-readonly' => true
                        ];
                    }
                }
                if (isset($new_schemas[$schema_name]) && count($new_schemas[$schema_name]['properties']) > 0) {
                    $schema['properties']['custom_fields'] = [
                        'type' => Schema::TYPE_OBJECT,
                        'x-full-schema' => $schema_name,
                        'properties' => $new_schemas[$schema_name]['properties'],
                    ];
                }
            }
        }
    }
    $data['schemas'] = array_merge($data['schemas'], $new_schemas);
    return $data;
}
