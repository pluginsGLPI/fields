<?php

use Glpi\Application\View\TemplateRenderer;
use Glpi\Toolbox\Sanitizer;
use PhpParser\Node\Stmt\Foreach_;

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

class PluginFieldsDisplayContainer extends CommonDBTM {
    static $rightname = 'config';

    const SHOW_CONDITION_EQ = 1;
    const SHOW_CONDITION_NE = 2;
    const SHOW_CONDITION_LT = 3;
    const SHOW_CONDITION_GT = 4;
    const SHOW_CONDITION_REGEX = 5;

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
                  `plugin_fields_containers_id`       INT            {$default_key_sign} NOT NULL DEFAULT '0',
                  `itemtype`                          VARCHAR(100)   DEFAULT NULL,
                  `fields`                            VARCHAR(255)   DEFAULT NULL,
                  `condition`                         VARCHAR(255)   DEFAULT NULL,
                  `value`                             VARCHAR(255)   DEFAULT NULL,
                  `is_visible`                        TINYINT        NOT NULL DEFAULT '0',
                  PRIMARY KEY                         (`id`),
                  KEY `plugin_fields_containers_id_itemtype`       (`plugin_fields_containers_id`, `itemtype`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->query($query) or die ($DB->error());
        }

        return true;
    }


    public static function getEnumCondition() : array {
        return [
        self::SHOW_CONDITION_EQ => '=',
        self::SHOW_CONDITION_NE => '≠',
        self::SHOW_CONDITION_LT => '<',
        self::SHOW_CONDITION_GT => '>',
        self::SHOW_CONDITION_REGEX => __('regular expression matches', 'fields'),
        ];
    }

    public static function getConditionName($condition) {
        switch ($condition) {
            case self::SHOW_CONDITION_EQ:
                echo '=';
                break;
            case self::SHOW_CONDITION_NE:
                echo '≠';
                break;
            case self::SHOW_CONDITION_LT:
                echo '<';
                break;
            case self::SHOW_CONDITION_GT:
                echo '>';
            case self::SHOW_CONDITION_REGEX:
                echo __('regular expression matches', 'fields');
                break;
        }
    }

    static function uninstall() {
        global $DB;

        $DB->query("DROP TABLE IF EXISTS `".self::getTable()."`");

        return true;
    }

    static function getTypeName($nb = 0) {
        return __('Condition to hide block', 'fields');
    }

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        return self::createTabEntry(self::getTypeName(), countElementsInTable(self::getTable(),
        ['plugin_fields_containers_id' => $item->getID()]));
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if ($item instanceof PluginFieldsContainer) {
            self::showForTabContainer($item);
            return true;
        }
        return false;
    }

    public function prepareInputForAdd($input) {
        return Toolbox::addslashes_deep($input);
    }

    public function prepareInputForUpdate($input) {
        return Toolbox::addslashes_deep($input);
    }

    public static function getDisplayConditionForContainer(int $container_id): array {
        global $DB;
        $iterator = $DB->request([
            'SELECT' => [
                self::getTable().'.*',
            ],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'plugin_fields_containers_id' => $container_id,
            ]
        ]);

        $conditions = [];
        foreach ($iterator as $data) {
            $conditions[] = $data;
        }
        return $conditions;
    }

    private static function getItemtypesForContainer(int $container_id): array {
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
            foreach ($itemtypes as $itemtype) {
                $results[$itemtype] = $itemtype::getTypeName();
            }
            return $results;
        }
        return [];
    }

    public static function getFieldName($so_id, $itemtype) {
        echo Search::getOptions($itemtype)[$so_id]['name'];
    }

    public static function showItemtypeFieldForm($itemtype) {
        return Dropdown::showFromArray("fields",self::removeBlackListedOption(Search::getOptions($itemtype), $itemtype),["display" => false]);
    }

    public static function removeBlackListedOption($array, $itemtype_class){

        $itemtype_object = new $itemtype_class();
        $allowed_so = [];

        //remove "Common"
        unset($array['common']);
        //remove ID
        unset($array[2]);

        $allowed_table = [getTableForItemType($itemtype_class), getTableForItemType(User::getType()), getTableForItemType(Group::getType())];

        //use relation.constant.php to allow some tables (exclude Location which is managed later)
        foreach (getDbRelations() as $tablename => $relation) {
            foreach ($relation as $main_table => $foreignKey) {
                if($main_table == getTableForItemType($itemtype_class)
                    && !is_array($foreignKey)
                    && getTableNameForForeignKeyField($foreignKey) != getTableForItemType(Location::getType())) {
                    $allowed_table[] = getTableNameForForeignKeyField($foreignKey);
                }
            }
        }

        if($itemtype_object->isEntityAssign()){
            $allowed_table[] = getTableForItemType(Entity::getType());
        }

        //allew specific datatype
        $allowed_datatype = ["email", "weblink", "specific", "itemlink", "string", "text","number", "dropdown", "decimal", "integer", "bool"];

        foreach($array as $subKey => $subArray){
            if(isset($subArray["table"]) && in_array($subArray["table"], $allowed_table)
                && in_array($subArray["datatype"], $allowed_datatype)
                && !isset($subArray["nosearch"]) //Exclude SO with no search
                && !isset($subArray["usehaving"]) //Exclude count SO ex: Ticket -> Number of sons tickets
                && !isset($subArray["forcegroupby"]) //Exclude 1-n relation ex: Ticket_User
                && !isset($subArray["computation"])){ //Exclude SO with computation Ex : Ticket -> Time to own exceeded
                $allowed_so[$subKey] = $subArray["name"];
            }else{
                Toolbox::logError($subArray);
            }
        }

        if($itemtype_object->maybeLocated()){
            $allowed_so[80] = Location::getTypeName(0);
        }

        return $allowed_so;
    }


    public function computeDisplayContainer($item, $container_id){
        //load all condition for itemtype and container
        $displayCondition = new self();
        $found_dc   = $displayCondition->find(['itemtype' => get_class($item), 'plugin_fields_containers_id' => $container_id]);

        if (count($found_dc)){
            $display = true;
            foreach ($found_dc as $data) {

                $displayCondition->getFromDB($data['id']);
                $result = $displayCondition->checkCondition($item);
                if(!$result){
                    return $result;
                }
            }

            return $display;
        }else {
            //no condition found -> display container
            return true;
        }
    }


    public function checkCondition($item){
        $valueToCheck = $this->fields['value'];
        $condition = $this->fields['condition'];
        $searchOption = Search::getOptions(get_class($item))[$this->fields['fields']];

        $value = null;
        switch ($searchOption['datatype']) {
            case 'dropdown':
                $dropdown_class = getItemTypeForTable($searchOption['table']);
                $dropdown_item = new $dropdown_class();
                if (!$value = $dropdown_item->getFromDBByCrit(['name' => $valueToCheck])){
                    $value = null;
                }
                break;

            case 'email':
            case 'weblink':
            case 'itemlink':
            case 'string':
            case 'text':
            case 'number':
            case 'decimal':
            case 'integer':
                $value = $valueToCheck;
                break;
        }

        if($value !== null){
            switch ($condition) {
                case self::SHOW_CONDITION_EQ:
                    // '='
                    if ($value == $item->fields[$searchOption['linkfield']]){
                        return false;
                    }
                    break;
                case self::SHOW_CONDITION_NE:
                    // '≠'
                    if ($value != $item->fields[$searchOption['linkfield']]){
                        return false;
                    }
                    break;
                case self::SHOW_CONDITION_LT:
                    // '<';
                    if ($item->fields[$searchOption['linkfield']] > $value){
                        return false;
                    }
                    break;
                case self::SHOW_CONDITION_GT:
                    //'>';
                    if ($item->fields[$searchOption['linkfield']] > $value){
                        return false;
                    }
                    break;
                case self::SHOW_CONDITION_REGEX:
                    //'regex';
                    if(self::checkRegex($value)) {
                        $value = Sanitizer::unsanitize($value);
                        if (preg_match_all($value . "i", $item->fields[$searchOption['linkfield']], $results) > 0) {
                            return false;
                        }
                    }
                    break;
            }
        }
        return true;
    }

    public static function checkRegex($regex) {
        // Avoid php notice when validating the regular expression
        set_error_handler(function ($errno, $errstr, $errfile, $errline, $errcontext) {
        });
        $isValid = !(preg_match($regex, null) === false);
        restore_error_handler();
        return $isValid;
     }


    public static function showForTabContainer(CommonGLPI $item, $options = []) {

        $displayCondition_id = $options['displaycondition_id'] ?? 0;
        $display_condition = null;
        $fields = null;

        if ($displayCondition_id) {
            $display_condition = new self();
            $display_condition->getFromDB($displayCondition_id);
            $fields = self::removeBlackListedOption(Search::getOptions($display_condition->fields['itemtype']),$display_condition->fields['itemtype']);
        }

        $container_id = $item->getID();
        $twig_params = [
            'container_id'              => $container_id,
            'display_condition'         => $display_condition,
            'list_conditions'           => SELF::getEnumCondition(),
            'list_fields'               => $fields,
            'list_display_conditions'   => self::getDisplayConditionForContainer($container_id),
            'container_itemtypes'       => self::getItemtypesForContainer($container_id),
            'target'                    => self::getFormURL(),
            'url_for_on_change'         => Plugin::getWebDir('fields') . '/ajax/display_container.php',
            'form_only'                 => $display_condition !== null || (($options['action'] ?? '') === 'get_add_form'),
        ];

        if ($display_condition === null) {
            TemplateRenderer::getInstance()->display('@fields/forms/display_block.html.twig', $twig_params);
        } else {
            return TemplateRenderer::getInstance()->render('@fields/forms/display_block.html.twig', $twig_params);
        }
        return '';
    }
}
