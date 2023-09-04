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
use Glpi\Toolbox\Sanitizer;

class PluginFieldsContainerDisplayCondition extends CommonDBChild
{
    use Glpi\Features\Clonable;

    public static $itemtype = PluginFieldsContainer::class;
    public static $items_id = 'plugin_fields_containers_id';

    const SHOW_CONDITION_EQ         = 1;
    const SHOW_CONDITION_NE         = 2;
    const SHOW_CONDITION_LT         = 3;
    const SHOW_CONDITION_GT         = 4;
    const SHOW_CONDITION_REGEX      = 5;
    const SHOW_CONDITION_UNDER      = 6;
    const SHOW_CONDITION_NOT_UNDER  = 7;

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
                  `plugin_fields_containers_id`       INT            {$default_key_sign} NOT NULL DEFAULT '0',
                  `itemtype`                          VARCHAR(100)   DEFAULT NULL,
                  `search_option`                            VARCHAR(255)   DEFAULT NULL,
                  `condition`                         VARCHAR(255)   DEFAULT NULL,
                  `value`                             VARCHAR(255)   DEFAULT NULL,
                  `is_visible`                        TINYINT        NOT NULL DEFAULT '0',
                  PRIMARY KEY                         (`id`),
                  KEY `plugin_fields_containers_id_itemtype`       (`plugin_fields_containers_id`, `itemtype`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->query($query) or die($DB->error());
        }

        return true;
    }

    /**
     * Get display condition comparison operators.
     *
     * @param bool $only_simple_conditions
     * @param bool $with_treedropdown_conditions
     *
     * @return array
     */
    private static function getComparisonOperators(
        bool $only_simple_conditions = false,
        bool $with_treedropdown_conditions = false
    ): array {
        $conditions = [
            self::SHOW_CONDITION_EQ => '=',
            self::SHOW_CONDITION_NE => '≠',
        ];

        if ($with_treedropdown_conditions) {
            $conditions[self::SHOW_CONDITION_UNDER] = __('under', 'fields');
            $conditions[self::SHOW_CONDITION_NOT_UNDER] = __('not under', 'fields');
        }

        if (!$only_simple_conditions) {
            $conditions[self::SHOW_CONDITION_LT] = '<';
            $conditions[self::SHOW_CONDITION_GT] = '>';
            $conditions[self::SHOW_CONDITION_REGEX] = __('regular expression matches', 'fields');
        }

        return $conditions;
    }

    public static function getConditionName($condition)
    {
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
                break;
            case self::SHOW_CONDITION_REGEX:
                echo __('regular expression matches', 'fields');
                break;
            case self::SHOW_CONDITION_UNDER:
                echo __('under', 'fields');
                break;
            case self::SHOW_CONDITION_NOT_UNDER:
                echo __('not under', 'fields');
                break;
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
        return _n('Condition to hide block', 'Conditions to hide block', $nb, 'fields');
    }


    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return self::createTabEntry(
            self::getTypeName(Session::getPluralNumber()),
            countElementsInTable(self::getTable(), ['plugin_fields_containers_id' => $item->getID()])
        );
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof PluginFieldsContainer) {
            self::showForTabContainer($item);
            return true;
        }
        return false;
    }


    public static function getDisplayConditionForContainer(int $container_id): array
    {
        global $DB;
        $iterator = $DB->request([
            'SELECT' => [
                self::getTable() . '.*',
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
            foreach ($itemtypes as $itemtype) {
                $results[$itemtype] = $itemtype::getTypeName();
            }
            return $results;
        }
        return [];
    }


    public static function getFieldName($so_id, $itemtype)
    {
        echo Search::getOptions($itemtype)[$so_id]['name'];
    }


    public static function showItemtypeFieldForm($itemtype)
    {

        $rand = mt_rand();
        $out = "";
        $out .= Dropdown::showFromArray("search_option", self::removeBlackListedOption(Search::getOptions($itemtype), $itemtype), ["display_emptychoice" => true, "display" => false, 'rand' => $rand]);

        $out .= Ajax::updateItemOnSelectEvent(
            "dropdown_search_option" . $rand,
            "results_condition",
            Plugin::getWebDir('fields') . '/ajax/container_display_condition.php',
            [
                'search_option_id'  => '__VALUE__',
                'itemtype'  => $itemtype,
                'action'     => 'get_condition_switch_so'
            ]
        );

        echo $out;
    }


    public static function showSearchOptionCondition($searchoption_id, $itemtype, ?string $condition = null, ?string $value = null)
    {
        $so = Search::getOptions($itemtype)[$searchoption_id];

        $itemtypetable = $itemtype::getTable();

        $twig_params = [
            'rand'           => rand(),
            'is_dropdown'    => false,
            'is_specific'    => false,
            'is_list_values' => false,
            'condition'      => $condition,
            'value'          => $value,
        ];

        if ($so['datatype'] == 'dropdown' || ($so['datatype'] == 'itemlink' && $so['table'] !== $itemtypetable)) {
            $twig_params['is_dropdown'] = true;
            $twig_params['dropdown_itemtype'] = getItemTypeForTable($so['table']);
            $twig_params['list_conditions']   = self::getComparisonOperators(
                true,
                is_a($twig_params['dropdown_itemtype'], CommonTreeDropdown::class, true)
            );
        } elseif ($so['datatype'] == 'specific' && get_parent_class($itemtype) == CommonITILObject::getType()) {
            $twig_params['list_conditions']   = self::getComparisonOperators(true);
            $twig_params['is_specific'] = true;
            switch ($so['field']) {
                case 'status':
                    $twig_params['is_list_values'] = true;
                    $twig_params['list_values'] = $itemtype::getAllStatusArray(false);
                    break;
                case 'type':
                        $twig_params['is_list_values'] = true;
                        $twig_params['list_values'] = $itemtype::getTypes();
                    break;
                case 'impact':
                case 'urgency':
                case 'priority':
                    $twig_params['item'] = new $itemtype();
                    $twig_params['itemtype_field'] = $so['field'];
                    break;
                case 'global_validation':
                    $twig_params['is_list_values'] = true;
                    $twig_params['list_values'] = CommonITILValidation::getAllStatusArray(false, true);
                    break;
            }
        } else {
            $twig_params['list_conditions']   = self::getComparisonOperators();
        }

        TemplateRenderer::getInstance()->display('@fields/forms/container_display_condition_so_condition.html.twig', $twig_params);
    }


    public static function getRawValue($searchoption_id, $itemtype, $value)
    {

        $so = Search::getOptions($itemtype)[$searchoption_id];
        $itemtypetable = $itemtype::getTable();

        $raw_value = '';

        if ($so['datatype'] == 'dropdown' || ($so['datatype'] == 'itemlink' && $so['table'] !== $itemtypetable)) {
            $dropdown_itemtype = getItemTypeForTable($so['table']);
            $dropdown = new $dropdown_itemtype();
            $dropdown->getFromDB($value);
            $raw_value = $dropdown->fields['name'];
        } else if ($so['datatype'] == 'specific' && get_parent_class($itemtype) == CommonITILObject::getType()) {
            switch ($so['field']) {
                case 'status':
                    $raw_value = $itemtype::getStatus($value);
                    break;
                case 'impact':
                    $raw_value = $itemtype::getImpactName($value);
                    break;
                case 'type':
                    $raw_value = $itemtype::getTicketTypeName($value);
                    break;
                case 'urgency':
                    $raw_value = $itemtype::getUrgencyName($value);
                    break;
                case 'priority':
                    $raw_value = $itemtype::getPriorityName($value);
                    break;
                case 'global_validation':
                    $raw_value = CommonITILValidation::getStatus($value);
                    break;
            }
        } else {
            $raw_value = $value;
        }

        echo $raw_value;
    }


    public static function removeBlackListedOption($array, $itemtype_class)
    {

        $itemtype_object = new $itemtype_class();
        $allowed_so = [];

        //remove "Common"
        unset($array['common']);

        $allowed_table = [getTableForItemType($itemtype_class), User::getTable(), Group::getTable()];
        if ($itemtype_object->maybeLocated()) {
            array_push($allowed_table, Location::getTable());
        }

        //use relation.constant.php to allow some tables (exclude Location which is managed using `CommonDBTM::maybeLocated()`)
        foreach (getDbRelations() as $relation) {
            foreach ($relation as $main_table => $foreignKey) {
                if (
                    $main_table == getTableForItemType($itemtype_class)
                    && !is_array($foreignKey)
                    && getTableNameForForeignKeyField($foreignKey) != getTableForItemType(Location::getType())
                ) {
                    $allowed_table[] = getTableNameForForeignKeyField($foreignKey);
                }
            }
        }

        if ($itemtype_object->isEntityAssign()) {
            $allowed_table[] = getTableForItemType(Entity::getType());
        }

        //allow specific datatype
        $allowed_datatype = ["email", "weblink", "specific", "itemlink", "string", "text","number", "dropdown", "decimal", "integer", "bool"];
        foreach ($array as $subKey => $subArray) {
            if (
                isset($subArray["table"]) && in_array($subArray["table"], $allowed_table)
                && (isset($subArray["datatype"]) && in_array($subArray["datatype"], $allowed_datatype))
                && !isset($subArray["nosearch"]) //Exclude SO with no search
                && !isset($subArray["usehaving"]) //Exclude count SO ex: Ticket -> Number of sons tickets
                && !isset($subArray["forcegroupby"]) //Exclude 1-n relation ex: Ticket_User
                && !isset($subArray["computation"]) //Exclude SO with computation Ex : Ticket -> Time to own exceeded
            ) {
                $allowed_so[$subKey] = $subArray["name"];
            }
        }

        return $allowed_so;
    }


    public function computeDisplayContainer($item, $container_id)
    {
        //load all condition for itemtype and container
        $displayCondition = new self();
        $found_dc   = $displayCondition->find(['itemtype' => get_class($item), 'plugin_fields_containers_id' => $container_id]);

        if (count($found_dc)) {
            $display = true;
            foreach ($found_dc as $data) {
                $displayCondition->getFromDB($data['id']);
                $result = $displayCondition->checkCondition($item);
                if (!$result) {
                    return $result;
                }
            }

            return $display;
        } else {
            //no condition found -> display container
            return true;
        }
    }


    public function checkCondition($item)
    {
        $value = $this->fields['value'];
        $condition = $this->fields['condition'];
        $searchOption = Search::getOptions(get_class($item))[$this->fields['search_option']];

        $fields = array_merge($item->fields, $item->input);

        switch ($condition) {
            case self::SHOW_CONDITION_EQ:
                // '='
                if ($value == $fields[$searchOption['linkfield']]) {
                    return false;
                }
                break;
            case self::SHOW_CONDITION_NE:
                // '≠'
                if ($value != $fields[$searchOption['linkfield']]) {
                    return false;
                }
                break;
            case self::SHOW_CONDITION_LT:
                // '<';
                if ($fields[$searchOption['linkfield']] > $value) {
                    return false;
                }
                break;
            case self::SHOW_CONDITION_GT:
                //'>';
                if ($fields[$searchOption['linkfield']] > $value) {
                    return false;
                }
                break;
            case self::SHOW_CONDITION_REGEX:
                //'regex';
                if (self::checkRegex($value)) {
                    $value = Sanitizer::unsanitize($value);
                    if (preg_match_all($value . "i", $fields[$searchOption['linkfield']]) > 0) {
                        return false;
                    }
                }
                break;
            case self::SHOW_CONDITION_UNDER:
                $sons = getSonsOf($searchOption['table'], $value);
                if (in_array($fields[$searchOption['linkfield']], $sons)) {
                    return false;
                }
                break;
            case self::SHOW_CONDITION_NOT_UNDER:
                $sons = getSonsOf($searchOption['table'], $value);
                if (!in_array($fields[$searchOption['linkfield']], $sons)) {
                    return false;
                }
                break;
        }

        return true;
    }


    public static function checkRegex($regex)
    {
        // Avoid php notice when validating the regular expression
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
        });
        $isValid = !(preg_match($regex, null) === false);
        restore_error_handler();
        return $isValid;
    }

    public function prepareInputForAdd($input)
    {
        // itemtype, search_option, condition, value must all be set
        if (!isset($input['itemtype'], $input['search_option'], $input['condition'])) {
            Session::addMessageAfterRedirect(
                __('You must specify an item type, search option and condition.', 'fields'),
                true,
                ERROR
            );
            return false;
        }

        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {
        // itemtype, search_option, condition, value must all be set
        if (!isset($input['itemtype'], $input['search_option'], $input['condition'])) {
            Session::addMessageAfterRedirect(
                __('You must specify an item type, search option and condition.', 'fields'),
                true,
                ERROR
            );
            return false;
        }
        return parent::prepareInputForUpdate($input);
    }

    public static function showForTabContainer(CommonGLPI $item, $options = [])
    {

        $displayCondition_id = $options['displaycondition_id'] ?? 0;
        $display_condition = null;

        if ($displayCondition_id) {
            $display_condition = new self();
            $display_condition->getFromDB($displayCondition_id);
        }

        $container_id = $item->getID();
        $has_fields = countElementsInTable(PluginFieldsField::getTable(), [
            'plugin_fields_containers_id' => $container_id
        ]) > 0;
        $twig_params = [
            'container_id'                  => $container_id,
            'container_display_conditions'  => self::getDisplayConditionForContainer($container_id),
            'has_fields'                    => $has_fields,
        ];

        TemplateRenderer::getInstance()->display('@fields/container_display_conditions.html.twig', $twig_params);
    }

    public function showForm($ID, array $options = [])
    {
        $container_id = $options['plugin_fields_containers_id'];

        $twig_params = [
            'container_display_condition' => $this,
            'container_id'                => $container_id,
            'container_itemtypes'         => self::getItemtypesForContainer($container_id),
            'search_options'              => $this->isNewItem()
                ? []
                : self::removeBlackListedOption(Search::getOptions($this->fields['itemtype']), $this->fields['itemtype']),
        ];
        TemplateRenderer::getInstance()->display('@fields/forms/container_display_condition.html.twig', $twig_params);
    }

    public function getCloneRelations(): array
    {
        return [];
    }
}
