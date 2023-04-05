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

use Glpi\Application\View\TemplateRenderer;
use Glpi\Toolbox\Sanitizer;

class PluginFieldsField extends CommonDBChild
{
    use Glpi\Features\Clonable;

    /**
     * Starting index for search options.
     * @var integer
     */
    public const SEARCH_OPTION_STARTING_INDEX = 76665;

    public static $itemtype = PluginFieldsContainer::class;
    public static $items_id = 'plugin_fields_containers_id';

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
                  `name`                              VARCHAR(255)   DEFAULT NULL,
                  `label`                             VARCHAR(255)   DEFAULT NULL,
                  `type`                              VARCHAR(255)   DEFAULT NULL,
                  `plugin_fields_containers_id`       INT            {$default_key_sign} NOT NULL DEFAULT '0',
                  `ranking`                           INT            NOT NULL DEFAULT '0',
                  `default_value`                     LONGTEXT       ,
                  `is_active`                         TINYINT        NOT NULL DEFAULT '1',
                  `is_readonly`                       TINYINT        NOT NULL DEFAULT '1',
                  `mandatory`                         TINYINT        NOT NULL DEFAULT '0',
                  `multiple`                          TINYINT        NOT NULL DEFAULT '0',
                  `allowed_values`                    TEXT           ,
                  PRIMARY KEY                         (`id`),
                  KEY `plugin_fields_containers_id`   (`plugin_fields_containers_id`),
                  KEY `is_active`                     (`is_active`),
                  KEY `is_readonly`                   (`is_readonly`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->query($query) or die($DB->error());
        }

        $migration->displayMessage("Updating $table");

        if (!$DB->fieldExists($table, 'is_active')) {
            $migration->addField($table, 'is_active', 'bool', ['value' => 1]);
            $migration->addKey($table, 'is_active', 'is_active');
        }
        if (!$DB->fieldExists($table, 'is_readonly')) {
            $migration->addField($table, 'is_readonly', 'bool', ['default' => false]);
            $migration->addKey($table, 'is_readonly', 'is_readonly');
        }
        if (!$DB->fieldExists($table, 'mandatory')) {
            $migration->addField($table, 'mandatory', 'bool', ['value' => 0]);
        }
        if (!$DB->fieldExists($table, 'multiple')) {
            $migration->addField($table, 'multiple', 'bool', ['value' => 0]);
        }

        //increase the size of column 'type' (25 to 255)
        $migration->changeField($table, 'type', 'type', 'string');

        if (!$DB->fieldExists($table, 'allowed_values')) {
            $migration->addField($table, 'allowed_values', 'text');
        }

        // change default_value from varchar to longtext
        $migration->changeField($table, 'default_value', 'default_value', 'longtext');

        $toolbox = new PluginFieldsToolbox();
        $toolbox->fixFieldsNames($migration, ['NOT' => ['type' => 'dropdown']]);

        //move old types to new format
        $migration->addPostQuery(
            $DB->buildUpdate(
                PluginFieldsField::getTable(),
                ['type' => 'dropdown-User'],
                ['type' => 'dropdownuser']
            )
        );

        $migration->addPostQuery(
            $DB->buildUpdate(
                PluginFieldsField::getTable(),
                ['type' => 'dropdown-OperatingSystem'],
                ['type' => 'dropdownoperatingsystems']
            )
        );

        // 1.18.3 Make search options ID stable over time ad constant across profiles
        if (Config::getConfigurationValue('plugin:fields', 'stable_search_options') !== 'yes') {
            self::migrateToStableSO($migration);
            $migration->addConfig(['stable_search_options' => 'yes'], 'plugin:fields');
        }

        return true;
    }

    /**
     * Migrate search options ID stored in DB to their new stable ID.
     *
     * Prior to 1.18.3, search options ID were built using a simple increment and filtered using current profile rights,
     * resulting in following behaviours:
     * - when a container was activated/deactivated/removed, SO ID were potentially changed;
     * - when a field was removed, SO ID were potentially changed;
     * - in a sessionless context (e.g. CLI command/crontask), no SO were available;
     * - when user added a SO in its display preference from a A profile, this SO was sometimes targetting a completely different field on a B profile.
     * All of these behaviours were resulting in unstable display preferences and saved searches.
     *
     * Producing an exact mapping between previous unstable SO ID and new stable SO ID is almost impossible in many cases, due to
     * previously described behaviours. Basically, we cannot know if the current SO ID in database is still correct
     * and what were the profile rights when it was generated.
     *
     * @param Migration $migration
     */
    private static function migrateToStableSO(Migration $migration): void
    {
        global $DB;

        // Flatten itemtype list
        $itemtypes = array_keys(array_merge([], ...array_values(PluginFieldsToolbox::getGlpiItemtypes())));

        foreach ($itemtypes as $itemtype) {
            // itemtype is stored in a JSON array, so entry is surrounded by double quotes
            $search_string = json_encode($itemtype);
            // Backslashes must be doubled in LIKE clause, according to MySQL documentation:
            // > To search for \, specify it as \\\\; this is because the backslashes are stripped
            // > once by the parser and again when the pattern match is made,
            // > leaving a single backslash to be matched against.
            $search_string = str_replace('\\', '\\\\', $search_string);

            $fields = $DB->request(
                [
                    'SELECT'     => [
                        'glpi_plugin_fields_fields.id',
                    ],
                    'FROM'       => 'glpi_plugin_fields_fields',
                    'INNER JOIN' => [
                        'glpi_plugin_fields_containers' => [
                            'FKEY' => [
                                'glpi_plugin_fields_containers' => 'id',
                                'glpi_plugin_fields_fields'     => 'plugin_fields_containers_id',
                                [
                                    'AND' => [
                                        'glpi_plugin_fields_containers.is_active' => 1,
                                    ]
                                ]
                            ]
                        ],
                    ],
                    'WHERE' => [
                        'glpi_plugin_fields_containers.itemtypes' => ['LIKE', '%' . $DB->escape($search_string) . '%'],
                        ['NOT' => ['glpi_plugin_fields_fields.type' => 'header']],
                    ],
                    'ORDERBY'      => [
                        'glpi_plugin_fields_fields.id',
                    ],
                ]
            );

            $i = PluginFieldsField::SEARCH_OPTION_STARTING_INDEX;

            foreach ($fields as $field_data) {
                $migration->changeSearchOption(
                    $itemtype,
                    $i,
                    PluginFieldsField::SEARCH_OPTION_STARTING_INDEX + $field_data['id']
                );

                $i++;
            }
        }
    }

    public static function uninstall()
    {
        global $DB;

        $DB->query("DROP TABLE IF EXISTS `" . self::getTable() . "`");

        return true;
    }

    public static function getTypeName($nb = 0)
    {
        return __("Field", "fields");
    }


    public function prepareInputForAdd($input)
    {
        //parse name
        $input['name'] = $this->prepareName($input);

        if ($input['multiple'] ?? false) {
            $input['default_value'] = json_encode($input['default_value'] ?? []);
        }

        //reject adding when field name is too long for mysql
        if (strlen($input['name']) > 64) {
            Session::AddMessageAfterRedirect(
                __("Field name is too long for database (digits in name are replaced by characters, try to remove them)", 'fields'),
                false,
                ERROR
            );
            return false;
        }

        if ($input['type'] === "dropdown") {
            //search if dropdown already exist in this container
            $found = $this->find(
                [
                    'name' => $input['name'],
                    'plugin_fields_containers_id' => $input['plugin_fields_containers_id'],
                ]
            );

            //reject adding for same dropdown on same bloc
            if (!empty($found)) {
                Session::AddMessageAfterRedirect(__("You cannot add same field 'dropdown' on same bloc", 'fields', false, ERROR));
                return false;
            }

            //reject adding when dropdown name is too long for mysql table name
            if (strlen(getTableForItemType(PluginFieldsDropdown::getClassname($input['name']))) > 64) {
                Session::AddMessageAfterRedirect(
                    __("Field name is too long for database (digits in name are replaced by characters, try to remove them)", 'fields'),
                    false,
                    ERROR
                );
                return false;
            }
        }

        // Before adding, add the ranking of the new field
        if (empty($input["ranking"])) {
            $input["ranking"] = $this->getNextRanking();
        }

        //add field to container table
        if ($input['type'] !== "header") {
            $container_obj = new PluginFieldsContainer();
            $container_obj->getFromDB($input['plugin_fields_containers_id']);
            foreach (json_decode($container_obj->fields['itemtypes']) as $itemtype) {
                $classname = PluginFieldsContainer::getClassname($itemtype, $container_obj->fields['name']);
                $classname::addField(
                    $input['name'],
                    $input['type'],
                    [
                        'multiple' => (bool)($input['multiple'] ?? false)
                    ]
                );
            }
        }

        if (isset($input['allowed_values'])) {
            $input['allowed_values'] = Sanitizer::dbEscape(json_encode($input['allowed_values']));
        }

        return $input;
    }


    public function prepareInputForUpdate($input)
    {
        if (
            array_key_exists('default_value', $input)
            && $this->fields['multiple']
        ) {
            $input['default_value'] = json_encode($input['default_value'] ?: []);
        }

        return $input;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function pre_deleteItem()
    {
        //remove field in container table
        if (
            $this->fields['type'] !== "header"
            && !isset($_SESSION['uninstall_fields'])
            && !isset($_SESSION['delete_container'])
        ) {
            $container_obj = new PluginFieldsContainer();
            $container_obj->getFromDB($this->fields['plugin_fields_containers_id']);
            foreach (json_decode($container_obj->fields['itemtypes']) as $itemtype) {
                $classname = PluginFieldsContainer::getClassname($itemtype, $container_obj->fields['name']);
                $classname::removeField($this->fields['name'], $this->fields['type']);
            }
        }

        //delete label translations
        $translation_obj = new PluginFieldsLabelTranslation();
        $translation_obj->deleteByCriteria([
            'itemtype' => self::getType(),
            'items_id' => $this->fields['id']
        ]);

        if ($this->fields['type'] === "dropdown") {
            return PluginFieldsDropdown::destroy($this->fields['name']);
        }
        return true;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function post_purgeItem()
    {
        global $DB;

        $table         = getTableForItemType(__CLASS__);
        $old_container = $this->fields['plugin_fields_containers_id'];
        $old_ranking   = $this->fields['ranking'];

        $query = "UPDATE $table SET
                ranking = ranking-1
                WHERE plugin_fields_containers_id = $old_container
                AND ranking > $old_ranking";
        $DB->query($query);

        return true;
    }


    /**
     * parse name for avoid non alphanumeric char in it and conflict with other fields
     * @param  array $input the field form input
     * @return string  the parsed name
     */
    public function prepareName($input, bool $prevent_duplicated = true)
    {
        $toolbox = new PluginFieldsToolbox();

        //contruct field name by processing label (remove non alphanumeric char)
        if (empty($input['name'])) {
            $input['name'] = $toolbox->getSystemNameFromLabel($input['label']) . 'field';
        }

        //for dropdown, if already exists, link to it
        if (isset($input['type']) && $input['type'] === "dropdown") {
            $found = $this->find(['name' => $input['name']]);
            if (!empty($found)) {
                return $input['name'];
            }
        }

        // for dropdowns like dropdown-User, dropdown-Computer, etc...
        $match = [];
        if (isset($input['type']) && preg_match('/^dropdown-(?<type>.+)$/', $input['type'], $match) === 1) {
            $input['name'] = getForeignKeyFieldForItemType($match['type']) . '_' . $input['name'];
        }

        //check if field name not already exist and not in conflict with itemtype fields name
        $container = new PluginFieldsContainer();
        $container->getFromDB($input['plugin_fields_containers_id']);

        $field      = new self();
        $field_name = $input['name'];

        if ($prevent_duplicated) {
            $i = 2;
            while (count($field->find(['name' => $field_name])) > 0) {
                $field_name = $toolbox->getIncrementedSystemName($input['name'], $i);
                $i++;
            }
        }

        // if it's too long then use a random postfix
        // MySQL/MariaDB official limit for a column name is 64 chars,
        // but there is a bug when trying to drop the column and the real max len is 53 chars
        // FIXME: see: https://bugs.mysql.com/bug.php?id=107165
        if (strlen($field_name) > 52) {
            $rand = rand();
            $field_name = substr($field_name, 0, 52 - strlen($rand)) . $rand;
        }

        return $field_name;
    }

    /**
     * Get the next ranking for a specified field
     *
     * @return integer
     */
    public function getNextRanking()
    {
        global $DB;

        $sql = "SELECT max(`ranking`) AS `rank`
              FROM `" . self::getTable() . "`
              WHERE `plugin_fields_containers_id` = '" .
                  $this->fields['plugin_fields_containers_id'] . "'";
        $result = $DB->query($sql);

        if ($DB->numrows($result) > 0) {
            $data = $DB->fetchAssoc($result);
            return $data["rank"] + 1;
        }
        return 0;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate) {
            switch ($item->getType()) {
                case __CLASS__:
                    $ong[1] = $this->getTypeName(1);
                    return $ong;
            }
        }

        return self::createTabEntry(
            __("Fields", "fields"),
            countElementsInTable(
                self::getTable(),
                ['plugin_fields_containers_id' => $item->getID()]
            )
        );
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $fup = new self();
        $fup->showSummary($item);
        return true;
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab('PluginFieldsLabelTranslation', $ong, $options);

        return $ong;
    }

    public function showSummary($container)
    {
        global $DB, $CFG_GLPI;

        $cID = $container->fields['id'];

        // Display existing Fields
        $query  = "SELECT `id`, `label`
                FROM `" . $this->getTable() . "`
                WHERE `plugin_fields_containers_id` = '$cID'
                ORDER BY `ranking` ASC";
        $result = $DB->query($query);

        $rand   = mt_rand();

        echo "<div id='viewField$cID$rand'></div>";

        $ajax_params = [
            'type'                        => __CLASS__,
            'parenttype'                  => PluginFieldsContainer::class,
            'plugin_fields_containers_id' => $cID,
            'id'                          => -1
        ];
        echo Html::scriptBlock('
            viewAddField' . $cID . $rand . ' = function() {
                $("#viewField' . $cID . $rand . '").load(
                    "' . $CFG_GLPI['root_doc'] . '/ajax/viewsubitem.php",
                    ' . json_encode($ajax_params) . '
                );
            };
        ');

        echo "<div class='center'>" .
           "<a href='javascript:viewAddField$cID$rand();'>";
        echo __("Add a new field", "fields") . "</a></div><br>";

        if ($DB->numrows($result) == 0) {
            echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
            echo "<th class='b'>" . __("No field for this block", "fields") . "</th></tr></table>";
        } else {
            echo '<div id="drag">';
            echo Html::hidden("_plugin_fields_containers_id", ['value' => $cID,
                'id'    => 'plugin_fields_containers_id'
            ]);
            echo "<table class='tab_cadre_fixehov'>";
            echo "<tr>";
            echo "<th>" . __("Label")              . "</th>";
            echo "<th>" . __("Type")               . "</th>";
            echo "<th>" . __("Default values")     . "</th>";
            echo "<th>" . __("Mandatory field")    . "</th>";
            echo "<th>" . __("Active")             . "</th>";
            echo "<th>" . __("Read only", "fields") . "</th>";
            echo "<th width='16'>&nbsp;</th>";
            echo "</tr>";

            $fields_type = self::getTypes();

            Session::initNavigateListItems('PluginFieldsField', __('Fields list'));

            while ($data = $DB->fetchArray($result)) {
                if ($this->getFromDB($data['id'])) {
                    echo "<tr class='tab_bg_2' style='cursor:pointer'>";

                    echo "<td>";
                    echo "<a href='" . Plugin::getWebDir('fields') . "/front/field.form.php?id={$this->getID()}'>{$this->fields['label']}</a>";
                    echo "</td>";
                    echo "<td>" . $fields_type[$this->fields['type']] . "</td>";
                    echo "<td>" ;
                    $dropdown_matches = [];
                    if (
                        preg_match('/^dropdown-(?<class>.+)$/', $this->fields['type'], $dropdown_matches) === 1
                        && !empty($this->fields['default_value'])
                    ) {
                        $itemtype = $dropdown_matches['class'];
                        // Itemtype may not exists (for instance for a deactivated plugin)
                        if (is_a($itemtype, CommonDBTM::class, true)) {
                            $item = new $itemtype();
                            if ($this->fields['multiple']) {
                                $values = json_decode($this->fields['default_value']);

                                $names = [];
                                foreach ($values as $value) {
                                    if ($item->getFromDB($value)) {
                                        $names[] = $item->getName();
                                    }
                                }

                                echo implode(', ', $names);
                            } else {
                                if ($item->getFromDB($this->fields['default_value'])) {
                                    echo $item->getName();
                                }
                            }
                        }
                    } elseif ($this->fields['type'] === 'dropdown' && !empty($this->fields['default_value'])) {
                        $table = getTableForItemType(PluginFieldsDropdown::getClassname($this->fields['name']));
                        if ($this->fields['multiple']) {
                            echo implode(
                                ', ',
                                Dropdown::getDropdownArrayNames($table, json_decode($this->fields['default_value']))
                            );
                        } else {
                            echo Dropdown::getDropdownName($table, $this->fields['default_value']);
                        }
                    } else {
                        echo $this->fields['default_value'];
                    }
                    echo "</td>";
                    echo "<td align='center'>" . Dropdown::getYesNo($this->fields["mandatory"]) . "</td>";
                    echo "<td align='center'>";
                    echo ($this->isActive())
                     ? __('Yes')
                     : '<b class="red">' . __('No') . '</b>';
                    echo "</td>";

                    echo "<td>";
                    echo Dropdown::getYesNo($this->fields["is_readonly"]);
                    echo "</td>";

                    echo '<td class="rowhandler control center">';
                    echo '<div class="drag row" style="cursor:move; border: 0;">';
                    echo '<i class="ti ti-grip-horizontal" title="' . __('Move') . '">';
                    echo '</div>';
                    echo '</td>';
                    echo "</tr>";
                }
            }
        }
        echo '</table>';
        echo '</div>';
        echo Html::scriptBlock('$(document).ready(function() {
            redipsInit()
        });');
    }


    public function showForm($ID, $options = [])
    {
        $rand = mt_rand();

        if (isset($options['parent_id']) && !empty($options['parent_id'])) {
            $container = new PluginFieldsContainer();
            $container->getFromDB($options['parent_id']);
        } else if (
            isset($options['parent'])
                 && $options['parent'] instanceof CommonDBTM
        ) {
            $container = $options['parent'];
        }

        if ($ID > 0) {
            $attrs = ['readonly' => 'readonly'];
            $edit = true;
        } else {
            $attrs = [];
            // Create item
            $edit = false;
            $options['plugin_fields_containers_id'] = $container->getField('id');
        }

        $this->initForm($ID, $options);
        $this->showFormHeader($ID, $options);

        echo "<tr>";
        echo "<td>" . __("Label") . " : </td>";
        echo "<td colspan='3'>";
        echo Html::hidden('plugin_fields_containers_id', ['value' => $container->getField('id')]);
        echo Html::input(
            'label',
            [
                'value' => $this->fields['label'],
            ] + $attrs
        );
        echo "</td>";

        echo "</tr>";
        echo "<tr>";
        echo "<td>" . __("Type") . " : </td>";
        echo "<td colspan='3'>";
        if ($edit) {
            echo self::getTypes(true)[$this->fields['type']];
        } else {
            Dropdown::showFromArray(
                'type',
                self::getTypes(false),
                [
                    'value' => $this->fields['type'],
                    'rand'  => $rand,
                ]
            );
        }

        echo "</td>";
        echo "</tr>";

        echo '<tr id="plugin_fields_specific_fields_' . $rand . '" style="line-height: 46px;">';
        echo '<td>';
        Ajax::updateItemOnSelectEvent(
            "dropdown_type$rand",
            "plugin_fields_specific_fields_$rand",
            "../ajax/field_specific_fields.php",
            [
                'id'   => $ID,
                'type' => '__VALUE__',
                'rand' => $rand,
            ]
        );
        Ajax::updateItem(
            "plugin_fields_specific_fields_$rand",
            "../ajax/field_specific_fields.php",
            [
                'id'   => $ID,
                'type' => $this->fields['type'] ?? '',
                'rand' => $rand,
            ]
        );
        echo '</td>';
        echo '</tr>';

        echo "<tr>";
        echo "<td>" . __('Active') . " :</td>";
        echo "<td>";
        Dropdown::showYesNo('is_active', $this->fields["is_active"]);
        echo "</td>";
        echo "<td>" . __("Mandatory field") . " : </td>";
        echo "<td>";
        Dropdown::showYesNo("mandatory", $this->fields["mandatory"]);
        echo "</td>";
        echo "</tr>";

        echo "<tr>";
        echo "<td>" . __("Read only", "fields") . " :</td>";
        echo "<td colspan='3'>";
        Dropdown::showYesNo("is_readonly", $this->fields["is_readonly"]);
        echo "</td>";
        echo "</tr>";

        $this->showFormButtons($options);
    }

    public static function showForTabContainer($c_id, $item)
    {
        //profile restriction
        $right = PluginFieldsProfile::getRightOnContainer($_SESSION['glpiactiveprofile']['id'], $c_id);
        if ($right < READ) {
            return;
        }
        $canedit = $right > READ;

        //get fields for this container
        $field_obj = new self();
        $fields = $field_obj->find(['plugin_fields_containers_id' => $c_id, 'is_active' => 1], "ranking");
        echo "<form method='POST' action='" . Plugin::getWebDir('fields') . "/front/container.form.php'>";
        echo Html::hidden('plugin_fields_containers_id', ['value' => $c_id]);
        echo Html::hidden('items_id', ['value' => $item->getID()]);
        echo Html::hidden('itemtype', ['value' => $item->getType()]);
        echo "<table class='tab_cadre_fixe'>";
        echo self::prepareHtmlFields($fields, $item, $canedit);

        if ($canedit) {
            echo "<tr><td class='tab_bg_2 center' colspan='4'>";
            echo "<input class='btn btn-primary' type='submit' name='update_fields_values' value=\"" .
            _sx("button", "Save") . "\" class='submit'>";
            echo "</td></tr>";
        }

        echo "</table>";
        Html::closeForm();

        return true;
    }

    /**
     * Display dom container
     *
     * @param int         $id       Container's ID
     * @param CommonDBTM  $item     Item
     * @param string      $type     Type (either 'dom' or 'domtab'
     * @param string      $subtype  Requested subtype (used for domtab only)
     *
     * @return void
     */
    public static function showDomContainer($id, $item, $type = "dom", $subtype = "")
    {

        if ($id !== false) {
            //get fields for this container
            $field_obj = new self();
            $fields = $field_obj->find(
                [
                    'plugin_fields_containers_id' => $id,
                    'is_active' => 1,
                ],
                "ranking"
            );
        } else {
            $fields = [];
        }

        echo Html::hidden('_plugin_fields_type', ['value' => $type]);
        echo Html::hidden('_plugin_fields_subtype', ['value' => $subtype]);
        echo self::prepareHtmlFields($fields, $item);
    }

    /**
     * Display fields in any existing tab
     *
     * @param array $params [item, options]
     *
     * @return void
     */
    public static function showForTab($params)
    {
        $item = $params['item'];

        $functions = array_column(debug_backtrace(), 'function');

        $subtype = isset($_SESSION['glpi_tabs'][strtolower($item::getType())]) ? $_SESSION['glpi_tabs'][strtolower($item::getType())] : "";
        $type = substr($subtype, -strlen('$main')) === '$main'
            || in_array('showForm', $functions)
            || in_array('showPrimaryForm', $functions)
            || in_array('showFormHelpdesk', $functions)
                ? 'dom'
                : 'domtab';
        if ($subtype == -1) {
            $type = 'dom';
        }
        // if we are in 'dom' or 'tab' type, no need for subtype ('domtab' specific)
        if ($type != 'domtab') {
            $subtype = "";
        }

        //find container (if not exist, do nothing)
        if (isset($_REQUEST['c_id'])) {
            $c_id = $_REQUEST['c_id'];
        } else if (!$c_id = PluginFieldsContainer::findContainer(get_Class($item), $type, $subtype)) {
            return false;
        }

        $right = PluginFieldsProfile::getRightOnContainer($_SESSION['glpiactiveprofile']['id'], $c_id);
        if ($right < READ) {
            return;
        }

        Html::requireJs('tinymce');

        //need to check if container is usable on this object entity
        $loc_c = new PluginFieldsContainer();
        $loc_c->getFromDB($c_id);
        $entities = [$loc_c->fields['entities_id']];
        if ($loc_c->fields['is_recursive']) {
            $entities = getSonsOf(getTableForItemType('Entity'), $loc_c->fields['entities_id']);
        }

        if ($item->isEntityAssign()) {
            $current_entity = $item->getEntityID();
            if (!in_array($current_entity, $entities)) {
                return false;
            }
        }

        //parse REQUEST_URI
        if (!isset($_SERVER['REQUEST_URI'])) {
            return false;
        }
        $current_url = $_SERVER['REQUEST_URI'];
        if (
            strpos($current_url, ".form.php") === false
            && strpos($current_url, ".injector.php") === false
            && strpos($current_url, ".public.php") === false
        ) {
            return false;
        }

        //Retrieve dom container
        $itemtypes = PluginFieldsContainer::getUsedItemtypes($type, true);

        //if no dom containers defined for this itemtype, do nothing (in_array case insensitive)
        if (!in_array(strtolower($item::getType()), array_map('strtolower', $itemtypes))) {
            return false;
        }

        $html_id = 'plugin_fields_container_' . mt_rand();
        echo "<div id='{$html_id}'>";
        $display_condition = new PluginFieldsContainerDisplayCondition();
        if ($display_condition->computeDisplayContainer($item, $c_id)) {
            self::showDomContainer(
                $c_id,
                $item,
                $type,
                $subtype
            );
        }
        echo "</div>";

        //JS to trigger any change and check if container need to be display or not
        $ajax_url   = Plugin::getWebDir('fields') . '/ajax/container.php';
        $items_id = !$item->isNewItem() ? $item->getID() : 0;
        echo Html::scriptBlock(<<<JAVASCRIPT
            function refreshContainer() {
                const data = $('#{$html_id}').closest('form').serializeArray().reduce(
                    function(obj, item) {
                        obj[item.name] = item.value;
                        return obj;
                    },
                    {}
                );

                $.ajax(
                    {
                        url: '{$ajax_url}',
                        type: 'GET',
                        data: {
                            action:   'get_fields_html',
                            id:       {$c_id},
                            itemtype: '{$item::getType()}',
                            items_id: {$items_id},
                            type:     '{$type}',
                            subtype:  '{$subtype}',
                            input:    data
                        },
                        success: function(data) {
                            // Close open select2 dropdown that will be replaced
                            $('#{$html_id}').find('.select2-hidden-accessible').select2('close');
                            // Refresh fields HTML
                            $('#{$html_id}').html(data);
                        }
                    }
                );
            }
            $(
                function () {
                    const form = $('#{$html_id}').closest('form');
                    form.on(
                        'change',
                        'input, select, textarea',
                        function(evt) {
                            if (evt.target.name == "itilcategories_id") {
                                // Do not refresh tab container when form is reloaded
                                // to prevent issues diues to duplicated calls
                                return;
                            }
                            if ($(evt.target).closest('#{$html_id}').length > 0) {
                                return; // Do nothing if element is inside fields container
                            }
                            refreshContainer();
                        }
                    );

                    var refresh_timeout = null;
                    form.find('textarea').each(
                        function () {
                            const editor = tinymce.get(this.id);
                            if (editor !== null) {
                                editor.on(
                                    'change',
                                    function(evt) {
                                        if ($(evt.target.targetElm).closest('#{$html_id}').length > 0) {
                                            return; // Do nothing if element is inside fields container
                                        }

                                        if (refresh_timeout !== null) {
                                            window.clearTimeout(refresh_timeout);
                                        }
                                        refresh_timeout = window.setTimeout(refreshContainer, 1000);
                                    }
                                );
                            }
                        }
                    );
                }
            );
JAVASCRIPT
        );
    }

    public static function prepareHtmlFields(
        $fields,
        $item,
        $canedit = true,
        $show_table = true,
        $massiveaction = false
    ) {

        if (empty($fields)) {
            return false;
        }

        //get object associated with this fields
        $first_field = reset($fields);
        $container_obj = new PluginFieldsContainer();
        if (!$container_obj->getFromDB($first_field['plugin_fields_containers_id'])) {
            return false;
        }

        // check if current profile can edit fields
        $right = PluginFieldsProfile::getRightOnContainer($_SESSION['glpiactiveprofile']['id'], $container_obj->getID());
        if ($right < READ) {
            return;
        }
        $canedit = $right > READ;

        // Fill status overrides if needed
        if (in_array($item->getType(), PluginFieldsStatusOverride::getStatusItemtypes())) {
            $status_overrides = PluginFieldsStatusOverride::getOverridesForItem($container_obj->getID(), $item);
            foreach ($status_overrides as $status_override) {
                if (isset($fields[$status_override['plugin_fields_fields_id']])) {
                    $fields[$status_override['plugin_fields_fields_id']]['is_readonly'] = $status_override['is_readonly'];
                    $fields[$status_override['plugin_fields_fields_id']]['mandatory'] = $status_override['mandatory'];
                }
            }
        }

        $found_v = null;
        if (!$item->isNewItem()) {
            //find row for this object with the items_id
            $classname = PluginFieldsContainer::getClassname($item->getType(), $container_obj->fields['name']);
            $obj = new $classname();
            $found_values = $obj->find(
                [
                    'plugin_fields_containers_id' => $first_field['plugin_fields_containers_id'],
                    'items_id' => $item->getID(),
                ]
            );
            $found_v = array_shift($found_values);
        }

        // test status for "CommonITILObject" objects
        if ($item instanceof CommonITILObject && in_array($item->fields['status'] ?? null, $item->getClosedStatusArray())) {
            $canedit = false;
        }

        //show all fields
        foreach ($fields as &$field) {
            $field['itemtype'] = self::getType();
            $field['label'] = PluginFieldsLabelTranslation::getLabelFor($field);

            $field['allowed_values'] = !empty($field['allowed_values']) ? json_decode($field['allowed_values']) : [];
            if ($field['type'] === 'glpi_item') {
               // Convert allowed values to [$itemtype_class => $itemtype_name] format
                $allowed_itemtypes = [];
                foreach ($field['allowed_values'] as $allowed_itemtype) {
                    if (is_a($allowed_itemtype, CommonDBTM::class, true)) {
                        $allowed_itemtypes[$allowed_itemtype] = $allowed_itemtype::getTypeName(Session::getPluralNumber());
                    }
                }
                $field['allowed_values'] = $allowed_itemtypes;
            }

            //compute classname for 'dropdown-XXXXXX' field
            $dropdown_matches = [];
            if (
                preg_match('/^dropdown-(?<class>.+)$/i', $field['type'], $dropdown_matches)
                && class_exists($dropdown_matches['class'])
            ) {
                $dropdown_class = $dropdown_matches['class'];

                $field['dropdown_class'] = $dropdown_class;
                $field['dropdown_condition'] = [];

                $object = new $dropdown_class();
                if ($object->maybeDeleted()) {
                    $field['dropdown_condition']['is_deleted'] = false;
                }
                if ($object->maybeActive()) {
                    $field['dropdown_condition']['is_active'] = true;
                }
            }

            //get value
            $value = null;
            if (is_array($found_v)) {
                if ($field['type'] == "dropdown") {
                    $value = $found_v["plugin_fields_" . $field['name'] . "dropdowns_id"];
                } else if ($field['type'] == "glpi_item") {
                    $itemtype_key = sprintf('itemtype_%s', $field['name']);
                    $items_id_key = sprintf('items_id_%s', $field['name']);
                    $value = [
                        'itemtype' => $found_v[$itemtype_key],
                        'items_id' => $found_v[$items_id_key],
                    ];
                } else {
                    $value = $found_v[$field['name']] ?? "";
                }
            }

            if (!$field['is_readonly']) {
                if ($field['type'] == "dropdown") {
                    $field_name = sprintf('plugin_fields_%sdropdowns_id', $field['name']);
                    if (isset($_SESSION['plugin']['fields']['values_sent'][$field_name])) {
                        $value = $_SESSION['plugin']['fields']['values_sent'][$field_name];
                    } elseif (isset($item->input[$field_name])) {
                        // find from $item->input due to ajax refresh container
                        $value = $item->input[$field_name];
                    }
                } else {
                    if (isset($_SESSION['plugin']['fields']['values_sent'][$field['name']])) {
                        $value = $_SESSION['plugin']['fields']['values_sent'][$field['name']];
                    } elseif (isset($item->input[$field['name']])) {
                        // find from $item->input due to ajax refresh container
                        $value = $item->input[$field['name']];
                    }
                }
            }

            //get default value
            if ($value === null) {
                if ($field['type'] === 'dropdown' && $field['default_value'] === '') {
                    $value = 0;
                } else if ($field['default_value'] !== "") {
                    $value = $field['default_value'];

                    // shortcut for date/datetime
                    if (
                        in_array($field['type'], ['date', 'datetime'])
                        && $value == 'now'
                    ) {
                        $value = $_SESSION["glpi_currenttime"];
                    }
                }
            }

            if ($field['multiple']) {
                $value = json_decode($value);
            }

            $field['value'] = $value;
        }

        $html = TemplateRenderer::getInstance()->render('@fields/fields.html.twig', [
            'item'           => $item,
            'fields'         => $fields,
            'canedit'        => $canedit,
            'massiveaction'  => $massiveaction,
            'container'      => $container_obj,
        ]);

        unset($_SESSION['plugin']['fields']['values_sent']);

        return $html;
    }

    public static function showSingle($itemtype, $searchOption, $massiveaction = false)
    {
        global $DB;

        //clean dropdown [pre/su]fix if exists
        $cleaned_linkfield = preg_replace(
            "/plugin_fields_(.*)dropdowns_id/",
            "$1",
            $searchOption['linkfield']
        );

        //find field
        $query = "SELECT fields.plugin_fields_containers_id, fields.is_readonly, fields.multiple, fields.default_value
                FROM glpi_plugin_fields_fields fields
                LEFT JOIN glpi_plugin_fields_containers containers
                  ON containers.id = fields.plugin_fields_containers_id
                  AND containers.itemtypes LIKE '%$itemtype%'
               WHERE fields.name = '$cleaned_linkfield'";
        $res = $DB->query($query);
        if ($DB->numrows($res) == 0) {
            return false;
        }

        $data = $DB->fetchAssoc($res);

        //display an hidden post field to store container id
        echo Html::hidden('c_id', ['value' => $data['plugin_fields_containers_id']]);

        //prepare array for function prepareHtmlFields
        $fields = [[
            'id'                          => 0,
            'type'                        => $searchOption['pfields_type'],
            'plugin_fields_containers_id' => $data['plugin_fields_containers_id'],
            'name'                        => $cleaned_linkfield,
            'is_readonly'                 => $data['is_readonly'],
            'default_value'               => $data['default_value'],
            'multiple'                    => $data['multiple']
        ]
        ];

        //show field
        $item = new $itemtype();
        $item->getEmpty();

        echo self::prepareHtmlFields($fields, $item, true, false, $massiveaction);

        return true;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function post_getEmpty()
    {
        $this->fields['is_active'] = 1;
        $this->fields['type']      = 'text';
    }

    public static function getTypes(bool $flat_list = true)
    {
        $common_types = [
            'header'       => __("Header", "fields"),
            'text'         => __("Text (single line)", "fields"),
            'textarea'     => __("Text (multiples lines)", "fields"),
            'number'       => __("Number", "fields"),
            'url'          => __("URL", "fields"),
            'dropdown'     => __("Dropdown", "fields"),
            'yesno'        => __("Yes/No", "fields"),
            'date'         => __("Date", "fields"),
            'datetime'     => __("Date & time", "fields"),
            'glpi_item'    => __("GLPI item", "fields"),
        ];

        $all_types = [
            __('Common') => $common_types,
        ];

        foreach (PluginFieldsToolbox::getGlpiItemtypes() as $section => $itemtypes) {
            $all_types[$section] = [];
            foreach ($itemtypes as $itemtype => $itemtype_name) {
                $all_types[$section]['dropdown-' . $itemtype] = $itemtype_name;
            }
        }

        return $flat_list ? array_merge([], ...array_values($all_types)) : $all_types;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function post_addItem()
    {
        $input = $this->fields;

        //dropdowns : create files
        if ($input['type'] === "dropdown") {
            //search if dropdown already exist in other container
            $found = $this->find(['id' => ['!=', $input['id']], 'name' => $input['name']]);
            //for dropdown, if already exist, don't create files
            if (empty($found)) {
                PluginFieldsDropdown::create($input);
            }
        }

        //Create label translation
        if (!isset($this->input['clone']) || !$this->input['clone']) {
            PluginFieldsLabelTranslation::createForItem($this);
        }
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'            => 2,
            'table'         => self::getTable(),
            'field'         => 'label',
            'name'          => __('Label'),
            'massiveaction' => false,
            'autocomplete'  => true,
        ];

        $tab[] = [
            'id'            => 3,
            'table'         => self::getTable(),
            'field'         => 'default_value',
            'name'          => __('Default values'),
            'massiveaction' => false,
            'autocomplete'  => true,
        ];

        return $tab;
    }

    public function prepareInputForClone($input)
    {
        if (array_key_exists('allowed_values', $input) && !empty($input['allowed_values'])) {
            // $input has been transformed with `Toolbox::addslashes_deep()`, and `self::prepareInputForAdd()`
            // is expecting an array, so it have to be unslashed then json decoded.
            $input['allowed_values'] = json_decode(Sanitizer::dbUnescape($input['allowed_values']));
        } else {
            unset($input['allowed_values']);
        }

        return $input;
    }

    public function getCloneRelations(): array
    {
        return [
            PluginFieldsStatusOverride::class,
            PluginFieldsLabelTranslation::class,
        ];
    }
}
