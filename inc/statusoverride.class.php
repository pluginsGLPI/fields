<?php

use Glpi\Application\View\TemplateRenderer;

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

class PluginFieldsStatusOverride extends CommonDBTM {
    static $rightname = 'config';

    static function canCreate() {
        return self::canUpdate();
    }

    static function canPurge() {
        return self::canUpdate();
    }

    static function install(Migration $migration, $version) {
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = method_exists('DBConnection', 'getDefaultPrimaryKeySignOption') ? DBConnection::getDefaultPrimaryKeySignOption() : '';

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage(sprintf(__("Installing %s"), $table));

            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`                                INT            {$default_key_sign} NOT NULL auto_increment,
                  `plugin_fields_fields_id`           INT            {$default_key_sign} NOT NULL DEFAULT '0',
                  `itemtype`                          VARCHAR(100)   DEFAULT NULL,
                  `states`                            VARCHAR(255)   DEFAULT NULL,
                  `is_readonly`                       TINYINT        NOT NULL DEFAULT '1',
                  `mandatory`                         TINYINT        NOT NULL DEFAULT '0',
                  PRIMARY KEY                         (`id`),
                  KEY `plugin_fields_fields_id`       (`plugin_fields_fields_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->query($query) or die ($DB->error());
        }

        return true;
    }

    static function uninstall() {
        global $DB;

        $DB->query("DROP TABLE IF EXISTS `".self::getTable()."`");

        return true;
    }

    static function getTypeName($nb = 0) {
        return __('Override by status', 'fields');
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        return self::createTabEntry(self::getTypeName(), self::countOverridesForContainer($item->getID()));
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item instanceof PluginFieldsContainer) {
            self::showForTabContainer($item);
            return true;
        }
        return false;
    }

    public function prepareInputForAdd($input) {
        if (isset($input['states']) && is_array($input['states'])) {
            $input['states'] = exportArrayToDB($input['states']);
        }
        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input) {
        if (isset($input['states']) && is_array($input['states'])) {
            $input['states'] = exportArrayToDB($input['states']);
        }
        return parent::prepareInputForUpdate($input);
    }

    public function post_getFromDB()
    {
        if (isset($this->fields['states']) && is_string($this->fields['states'])) {
            $this->fields['states'] = importArrayFromDB($this->fields['states']);
        }
        parent::post_getFromDB();
    }

    public static function countOverridesForContainer(int $container_id) {
        global $DB;

        $fields_table = PluginFieldsField::getTable();
        $container_table = PluginFieldsContainer::getTable();
        $iterator = $DB->request([
            'COUNT' => 'cpt',
            'FROM'   => self::getTable(),
            'LEFT JOIN' => [
                $fields_table   => [
                    'ON' => [
                        self::getTable() => 'plugin_fields_fields_id',
                        $fields_table   => 'id'
                    ]
                ],
                $container_table => [
                    'ON' => [
                        $fields_table => 'plugin_fields_containers_id',
                        $container_table => 'id'
                    ]
                ]
            ],
            'WHERE'  => [
                'plugin_fields_containers_id' => $container_id,
            ]
        ]);

        return $iterator->current()['cpt'] ?? 0;
    }

    public static function getOverridesForContainer(int $container_id): array {
        global $DB;

        $fields_table = PluginFieldsField::getTable();
        $container_table = PluginFieldsContainer::getTable();
        $iterator = $DB->request([
            'SELECT' => [
                self::getTable().'.*',
                $fields_table.'.name AS field_name',
            ],
            'FROM'   => self::getTable(),
            'LEFT JOIN' => [
                $fields_table   => [
                    'ON' => [
                        self::getTable() => 'plugin_fields_fields_id',
                        $fields_table   => 'id'
                    ]
                ],
                $container_table => [
                    'ON' => [
                        $fields_table => 'plugin_fields_containers_id',
                        $container_table => 'id'
                    ]
                ]
            ],
            'WHERE'  => [
                'plugin_fields_containers_id' => $container_id,
            ]
        ]);

        $overrides = [];
        foreach ($iterator as $data) {
            $data['states'] = importArrayFromDB($data['states']);
            $overrides[] = $data;
        }
        self::addStatusNames($overrides);
        return $overrides;
    }

    private static function getItemtypesForContainer(int $container_id): array {
        global $DB, $CFG_GLPI;

        $iterator = $DB->request([
            'SELECT' => ['itemtypes'],
            'FROM'   => PluginFieldsContainer::getTable(),
            'WHERE'  => [
                'id' => $container_id,
            ]
        ]);

        if (count($iterator)) {
            $itemtypes = $iterator->current()['itemtypes'];
            $itemtypes = importArrayFromDB($itemtypes);
            $state_types = array_merge(['Ticket', 'Change', 'Problem', 'Project', 'ProjectTask'], $CFG_GLPI['state_types']);
            // Get only itemtypes that exist and have a status field
            $itemtypes = array_filter($itemtypes, static function($itemtype) use ($state_types) {
                return class_exists($itemtype) && in_array($itemtype, $state_types, true);
            });
            $results = [];
            foreach ($itemtypes as $itemtype) {
                $results[$itemtype] = $itemtype::getTypeName();
            }
            return $results;
        }
        return [];
    }

    public static function getStatusFieldName(string $itemtype): string {
        switch ($itemtype) {
            case 'Ticket':
            case 'Change':
            case 'Problem':
                return 'status';
            case 'Project':
            case 'ProjectTask':
                return ProjectState::getForeignKeyField();
            default:
                return State::getForeignKeyField();
        }
    }

    private static function addStatusNames(array &$data): void {
        global $DB;

        $statuses = [
            'Ticket' => Ticket::getAllStatusArray(),
            'Change' => Change::getAllStatusArray(),
            'Problem' => Problem::getAllStatusArray(),
            'Project' => [],
            'ProjectTask' => [],
            'Other' => [],
        ];

        $iterator = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => ProjectState::getTable()
        ]);
        foreach ($iterator as $row) {
            $statuses['Project'][$row['id']] = $row['name'];
        }
        $statuses['ProjectTask'] = $statuses['Project'];

        $iterator = $DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => State::getTable()
        ]);
        foreach ($iterator as $row) {
            $statuses['Other'][$row['id']] = $row['name'];
        }

        foreach ($data as &$row) {
            $names = $statuses[$row['itemtype']] ?? $statuses['Other'];
            $t = $names;
            $row['status_names'] = array_filter($names, static function($name, $id) use ($row) {
                return in_array($id, $row['states']);
            }, ARRAY_FILTER_USE_BOTH);
        }
    }

    public static function getStatusDropdownForItemtype(string $itemtype): string {
        global $DB;

        $statuses = [];

        switch ($itemtype) {
            case 'Ticket':
                $statuses = Ticket::getAllStatusArray();
                break;
            case 'Change':
                $statuses = Change::getAllStatusArray();
                break;
            case 'Problem':
                $statuses = Problem::getAllStatusArray();
                break;
            case 'Project':
            case 'ProjectTask':
                $projectstate_table = ProjectState::getTable();
                $iterator = $DB->request([
                    'SELECT' => ['name'],
                    'FROM'   => $projectstate_table
                ]);
                foreach ($iterator as $data) {
                    $statuses[] = $data['name'];
                }
                break;
            default:
                return State::dropdown([
                    'name'  => 'states',
                    'display' => false,
                    'multiple' => true,
                ]);
        }

        return Dropdown::showFromArray('states', $statuses, [
            'display' => false,
            'multiple' => true,
        ]);
    }

    public static function showForTabContainer(CommonGLPI $item, $options = []) {
        $override_id = $options['statusoverride_id'] ?? 0;
        $override = null;
        if ($override_id) {
            $so = new self();
            $so->getFromDB($override_id);
            $override = $so->fields;
        }
        $container_id = $item->getID();
        $twig_params = [
            'container_id'          => $container_id,
            'override'              => $override,
            'overrides'             => self::getOverridesForContainer($container_id),
            'container_itemtypes'   => self::getItemtypesForContainer($container_id),
            'target'                => self::getFormURL(),
            'form_only'             => $override !== null || (($options['action'] ?? '') === 'get_add_form'),
        ];
        if ($override === null) {
            TemplateRenderer::getInstance()->display('@fields/forms/status_overrides.html.twig', $twig_params);
        } else {
            return TemplateRenderer::getInstance()->render('@fields/forms/status_overrides.html.twig', $twig_params);
        }
        return '';
    }
}
