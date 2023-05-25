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

use Glpi\Application\View\TemplateRenderer;

class PluginFieldsStatusOverride extends CommonDBChild
{
    use Glpi\Features\Clonable;

    public static $itemtype = PluginFieldsField::class;
    public static $items_id = 'plugin_fields_fields_id';

    /**
     * Install or update plugin base data.
     *
     * @param Migration $migration Migration instance
     * @param string    $version   Plugin current version
     *
     * @return boolean
     */
    public static function installBaseData(Migration $migration, $version)
    {
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

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
            $DB->query($query) or die($DB->error());
        }

        return true;
    }

    public static function uninstall()
    {
        global $DB;

        $DB->query("DROP TABLE IF EXISTS `" . self::getTable() . "`");

        return true;
    }

    public static function getTypeName($nb = 0)
    {
        return __('Override by status', 'fields');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return self::createTabEntry(self::getTypeName(), self::countOverridesForContainer($item->getID()));
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof PluginFieldsContainer) {
            self::showForTabContainer($item);
            return true;
        }
        return false;
    }

    public function prepareInputForAdd($input)
    {
        if (isset($input['states']) && is_array($input['states'])) {
            $input['states'] = json_encode($input['states']);
        }
        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {
        if (isset($input['states']) && is_array($input['states'])) {
            $input['states'] = json_encode($input['states']);
        }
        return parent::prepareInputForUpdate($input);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function post_getFromDB()
    {
        if (isset($this->fields['states']) && !empty($this->fields['states'])) {
            $this->fields['states'] = json_decode($this->fields['states']);
        }
        parent::post_getFromDB();
    }

    public static function getStatusItemtypes(): array
    {
        global $CFG_GLPI;
        return array_merge(['Ticket', 'Change', 'Problem', 'Project', 'ProjectTask'], $CFG_GLPI['state_types']);
    }

    public static function countOverridesForContainer(int $container_id)
    {
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

    public static function getOverridesForContainer(int $container_id): array
    {
        global $DB;

        $fields_table = PluginFieldsField::getTable();
        $container_table = PluginFieldsContainer::getTable();
        $iterator = $DB->request([
            'SELECT' => [
                self::getTable() . '.*',
                $fields_table . '.label AS field_name',
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
                ],
            ],
            'WHERE'  => [
                'plugin_fields_containers_id' => $container_id,
            ]
        ]);

        $overrides = [];
        foreach ($iterator as $data) {
            $data['states'] = !empty($data['states']) ? json_decode($data['states']) : [];
            $overrides[] = $data;
        }
        self::addStatusNames($overrides);
        return $overrides;
    }

    public static function getOverridesForItem(int $container_id, CommonDBTM $item): array
    {
        if (!in_array($item::getType(), self::getStatusItemtypes(), true)) {
            return [];
        }

        $status_field_name = self::getStatusFieldName($item->getType());
        $status = null;
        if (array_key_exists($status_field_name, $item->input) && $item->input[$status_field_name] !== '') {
            $status = (int)$item->input[$status_field_name];
        } elseif (array_key_exists($status_field_name, $item->fields) && $item->fields[$status_field_name] !== '') {
            $status = (int)$item->fields[$status_field_name];
        }

        return $status !== null
           ? self::getOverridesForItemtypeAndStatus($container_id, $item->getType(), $status)
           : [];
    }

    public static function getOverridesForItemtypeAndStatus(int $container_id, string $itemtype, int $status): array
    {
        if (!in_array($itemtype, self::getStatusItemtypes(), true)) {
            return [];
        }

        $overrides = self::getOverridesForContainer($container_id);
        return array_filter($overrides, static function ($override) use ($itemtype, $status) {
            return $override['itemtype'] === $itemtype && in_array($status, $override['states'], false);
        });
    }

    private static function getItemtypesForContainer(int $container_id): array
    {
        global $DB;

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
            $status_itemtypes = self::getStatusItemtypes();
            // Get only itemtypes that exist and have a status field
            $itemtypes = array_filter($itemtypes, static function ($itemtype) use ($status_itemtypes) {
                return class_exists($itemtype) && in_array($itemtype, $status_itemtypes, true);
            });
            $results = [];
            foreach ($itemtypes as $itemtype) {
                $results[$itemtype] = $itemtype::getTypeName();
            }
            return $results;
        }
        return [];
    }

    public static function getStatusFieldName(string $itemtype): string
    {
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

    private static function addStatusNames(array &$overrides): void
    {
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

        foreach ($overrides as &$override) {
            $names = $statuses[$override['itemtype']] ?? $statuses['Other'];
            $override['status_names'] = array_filter($names, static function ($name, $id) use ($override) {
                return in_array($id, $override['states']);
            }, ARRAY_FILTER_USE_BOTH);
        }
    }

    private static function getFieldsChoiceForContainer(int $container_id): array
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['glpi_plugin_fields_fields.*'],
            'FROM'   => 'glpi_plugin_fields_fields',
            'LEFT JOIN' => [
                'glpi_plugin_fields_containers' => [
                    'ON' => [
                        'glpi_plugin_fields_fields'     => 'plugin_fields_containers_id',
                        'glpi_plugin_fields_containers' => 'id'
                    ]
                ]
            ],
            'WHERE'  => [
                'plugin_fields_containers_id' => $container_id,
            ]
        ]);

        $fields = [];
        foreach ($iterator as $data) {
            $fields[$data['id']] = $data['label'];
        }
        return $fields;
    }

    public static function getStatusDropdownForItemtype(string $itemtype, array $values = []): string
    {
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
                    'name'     => 'states[]',
                    'value'    => $values,
                    'display'  => false,
                    'multiple' => true,
                ]);
        }

        return Dropdown::showFromArray('states', $statuses, [
            'values'   => $values,
            'display'  => false,
            'multiple' => true,
        ]);
    }

    public static function showForTabContainer(CommonGLPI $item, $options = [])
    {
        $container_id = $item->getID();
        $has_fields = countElementsInTable(PluginFieldsField::getTable(), [
            'plugin_fields_containers_id' => $container_id
        ]) > 0;
        $twig_params = [
            'container_id'          => $container_id,
            'overrides'             => self::getOverridesForContainer($container_id),
            'has_fields'            => $has_fields,
        ];
        TemplateRenderer::getInstance()->display('@fields/status_overrides.html.twig', $twig_params);
    }

    public function showForm($ID, array $options = [])
    {
        $container_id = $options['container_id'];

        $twig_params = [
            'override'            => $this,
            'container_id'        => $container_id,
            'container_itemtypes' => self::getItemtypesForContainer($container_id),
            'container_fields'    => self::getFieldsChoiceForContainer($container_id),
        ];
        TemplateRenderer::getInstance()->display('@fields/forms/status_override.html.twig', $twig_params);
    }
}
