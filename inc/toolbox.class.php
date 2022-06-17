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

class PluginFieldsToolbox
{
   /**
    * Get a clean system name from a label.
    *
    * @param string $label
    *
    * @return string
    */
    public function getSystemNameFromLabel($label)
    {

        $name = strtolower($label);

       // 1. remove trailing "s" (plural forms)
        $name = getSingular($name);

       // 2. keep only alphanum
        $name = preg_replace('/[^\da-z]/i', '', $name);

       // 3. if empty, uses a random number
        if (strlen($name) == 0) {
            $name = rand();
        }

       // 4. replace numbers by letters
        $name = $this->replaceIntByLetters($name);

        return $name;
    }

   /**
    * Return system name incremented by given increment.
    *
    * @param string  $name
    * @param integer $increment
    *
    * @return string
    */
    public function getIncrementedSystemName($name, $increment)
    {
        return $name . $this->replaceIntByLetters((string)$increment);
    }

   /**
    * Replace integers by corresponding letters inside given string.
    *
    * @param string $str
    *
    * @return mixed
    */
    private function replaceIntByLetters($str)
    {
        return str_replace(
            ['0',    '1',   '2',   '3',     '4',    '5',    '6',   '7',     '8',     '9'],
            ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'],
            $str
        );
    }

   /**
    * Fix dropdown names that were generated prior to Fields 1.9.2.
    *
    * @param Migration $migration
    * @param mixed     $condition
    *
    * @return void
    */
    public function fixFieldsNames(Migration $migration, $condition)
    {
        global $DB;

        $bad_named_fields = $DB->request(
            [
                'FROM' => PluginFieldsField::getTable(),
                'WHERE' => [
                    'name' => [
                        'REGEXP',
                        $DB->escape('[0-9]+')
                    ],
                    $condition,
                ],
            ]
        );

        if ($bad_named_fields->count() === 0) {
            return;
        }

        $migration->displayMessage(__("Fix fields names", "fields"));

        foreach ($bad_named_fields as $field) {
            $old_name = $field['name'];

           // Update field name
            $field['name'] = null;
            $field_obj = new PluginFieldsField();
            $new_name = $field_obj->prepareName($field);
            if ($new_name > 64) {
               // limit fields names to 64 chars (MySQL limit)
                $new_name = substr($new_name, 0, 64);
            }
            while (
                'dropdown' === $field['type']
                && strlen(getTableForItemType(PluginFieldsDropdown::getClassname($new_name))) > 64
            ) {
               // limit tables names to 64 chars (MySQL limit)
                $new_name = substr($new_name, 0, -1);
            }
            $field['name'] = $new_name;
            $field_obj->update(
                $field,
                false
            );

            $sql_fields_to_rename = [
                $old_name => $field['name'],
            ];

            if ('dropdown' === $field['type']) {
               // Rename dropdown table
                $old_table = getTableForItemType(PluginFieldsDropdown::getClassname($old_name));
                $new_table = getTableForItemType(PluginFieldsDropdown::getClassname($field['name']));

                if ($DB->tableExists($old_table)) {
                    $migration->renameTable($old_table, $new_table);
                }

               // Rename foreign keys in containers tables
                $old_fk = getForeignKeyFieldForTable($old_table);
                $new_fk = getForeignKeyFieldForTable($new_table);
                $sql_fields_to_rename[$old_fk] = $new_fk;
            }

           // Rename columns in plugin tables
            foreach ($sql_fields_to_rename as $old_field_name => $new_field_name) {
                $tables_to_update = $DB->request(
                    [
                        'SELECT'          => 'TABLE_NAME',
                        'DISTINCT'        => true,
                        'FROM'            => 'INFORMATION_SCHEMA.COLUMNS',
                        'WHERE'           => [
                            'TABLE_SCHEMA'  => $DB->dbdefault,
                            'TABLE_NAME'    => ['LIKE', 'glpi_plugin_fields_%'],
                            'COLUMN_NAME'   => $old_field_name
                        ],
                    ]
                );

                foreach ($tables_to_update as $table_to_update) {
                     $sql_fields = PluginFieldsMigration::getSQLFields($new_field_name, $field['type']);
                    if (count($sql_fields) !== 1 || !array_key_exists($new_field_name, $sql_fields)) {
                        // when this method has been made, only fields types that were matching a unique SQL field were existing
                        // other cases can be ignored
                        continue;
                    }
                     $migration->changeField(
                         $table_to_update['TABLE_NAME'],
                         $old_field_name,
                         $new_field_name,
                         $sql_fields[$new_field_name]
                     );
                     $migration->migrationOneTable($table_to_update['TABLE_NAME']);
                }
            }
        }
    }

   /**
    * Return a list of GLPI itemtypes.
    * These itemtypes will be available to attach fields containers on them,
    * and will be usable in dropdown / glpi_item fields.
    *
    * @return array
    */
    public static function getGlpiItemtypes(): array
    {
        global $CFG_GLPI, $PLUGIN_HOOKS;

        $assets_itemtypes = [
            Computer::class,
            Monitor::class,
            Software::class,
            NetworkEquipment::class,
            Peripheral::class,
            Printer::class,
            CartridgeItem::class,
            ConsumableItem::class,
            Phone::class,
            Rack::class,
            Enclosure::class,
            PDU::class,
            PassiveDCEquipment::class,
            Cable::class,
        ];

        $assistance_itemtypes = [
            Ticket::class,
            Problem::class,
            Change::class,
            TicketRecurrent::class,
            RecurrentChange::class,
            PlanningExternalEvent::class,
        ];

        $management_itemtypes = [
            SoftwareLicense::class,
            SoftwareVersion::class,
            Budget::class,
            Supplier::class,
            Contact::class,
            Contract::class,
            Document::class,
            Line::class,
            Certificate::class,
            Datacenter::class,
            Cluster::class,
            Domain::class,
            Appliance::class,
            Database::class,
            DatabaseInstance::class,
        ];

        $tools_itemtypes = [
            Project::class,
            ProjectTask::class,
            Reminder::class,
            RSSFeed::class,
        ];

        $administration_itemtypes = [
            User::class,
            Group::class,
            Entity::class,
            Profile::class,
        ];

        $components_itemtypes = [];
        foreach ($CFG_GLPI['device_types'] as $device_itemtype) {
            $components_itemtypes[] = $device_itemtype;
        }
        sort($components_itemtypes, SORT_NATURAL);

        $component_items_itemtypes = [];
        foreach ($CFG_GLPI['itemdevices'] as $deviceitem_itemtype) {
            $component_items_itemtypes[] = $deviceitem_itemtype;
        }
        sort($component_items_itemtypes, SORT_NATURAL);

        $plugins_itemtypes = [];
        foreach ($PLUGIN_HOOKS['plugin_fields'] as $itemtype) {
            $itemtype_specs = isPluginItemType($itemtype);
            if ($itemtype_specs) {
                $plugins_itemtypes[] = $itemtype;
            }
        }

        $dropdowns_sections  = [];
        foreach (Dropdown::getStandardDropdownItemTypes() as $section => $itemtypes) {
            $section_name = sprintf(
                __('%s: %s'),
                _n('Dropdown', 'Dropdowns', Session::getPluralNumber()),
                $section
            );
            $dropdowns_sections[$section_name] = array_keys($itemtypes);
        }

        $other_itemtypes = [
            NetworkPort::class,
            Notification::class,
            NotificationTemplate::class,
        ];

        $all_itemtypes = [
            _n('Asset', 'Assets', Session::getPluralNumber())         => $assets_itemtypes,
            __('Assistance')                                          => $assistance_itemtypes,
            __('Management')                                          => $management_itemtypes,
            __('Tools')                                               => $tools_itemtypes,
            __('Administration')                                      => $administration_itemtypes,
            _n('Plugin', 'Plugins', Session::getPluralNumber())       => $plugins_itemtypes,
            _n('Component', 'Components', Session::getPluralNumber()) => $components_itemtypes,
            __('Component items', 'fields')                           => $component_items_itemtypes,
        ] + $dropdowns_sections + [
            __('Other')                                               => $other_itemtypes,
        ];

        $plugin = new Plugin();
        if ($plugin->isActivated('genericobject') && method_exists('PluginGenericobjectType', 'getTypes')) {
            $go_itemtypes = [];
            foreach (array_keys(PluginGenericobjectType::getTypes()) as $go_itemtype) {
                if (!class_exists($go_itemtype)) {
                    continue;
                }
                $go_itemtypes[] = $go_itemtype;
            }
            if (count($go_itemtypes) > 0) {
                $all_itemtypes[$plugin->getInfo('genericobject', 'name')] = $go_itemtypes;
            }
        }

        $plugins_names = [];
        foreach ($all_itemtypes as $section => $itemtypes) {
            $named_itemtypes = [];
            foreach ($itemtypes as $itemtype) {
                $prefix = '';
                if ($itemtype_specs = isPluginItemType($itemtype)) {
                    $plugin_key = $itemtype_specs['plugin'];
                    if (!array_key_exists($plugin_key, $plugins_names)) {
                        $plugins_names[$plugin_key] = Plugin::getInfo($plugin_key, 'name');
                    }
                    $prefix = $plugins_names[$plugin_key] . ' - ';
                }

                $named_itemtypes[$itemtype] = $prefix . $itemtype::getTypeName(Session::getPluralNumber());
            }
            $all_itemtypes[$section] = $named_itemtypes;
        }

       // Remove empty lists (e.g. Plugin list).
        $all_itemtypes = array_filter($all_itemtypes);

        return $all_itemtypes;
    }
}
