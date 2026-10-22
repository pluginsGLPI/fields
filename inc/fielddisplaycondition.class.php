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
use Glpi\Features\Clonable;

/**
 * Conditions to hide a single field of a container (as opposed to
 * PluginFieldsContainerDisplayCondition, which hides the whole block).
 *
 * This class deliberately mirrors PluginFieldsContainerDisplayCondition and
 * delegates to it for everything that is independent of the storage table
 * (comparison operators, search option rendering, etc).
 */
class PluginFieldsFieldDisplayCondition extends CommonDBChild
{
    use Clonable;

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
        /** @var DBmysql $DB */
        global $DB;
        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();
        $table             = self::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage(sprintf(__('Installing %s'), $table));
            $query = "CREATE TABLE IF NOT EXISTS `{$table}` (
                  `id`                                INT            {$default_key_sign} NOT NULL auto_increment,
                  `plugin_fields_containers_id`       INT            {$default_key_sign} NOT NULL DEFAULT '0',
                  `plugin_fields_fields_id`           INT            {$default_key_sign} NOT NULL DEFAULT '0',
                  `itemtype`                          VARCHAR(100)   DEFAULT NULL,
                  `search_option`                     VARCHAR(255)   DEFAULT NULL,
                  `condition`                         VARCHAR(255)   DEFAULT NULL,
                  `value`                             VARCHAR(255)   DEFAULT NULL,
                  `is_visible`                        TINYINT        NOT NULL DEFAULT '0',
                  PRIMARY KEY                         (`id`),
                  KEY `plugin_fields_containers_id_itemtype`       (`plugin_fields_containers_id`, `itemtype`),
                  KEY `plugin_fields_fields_id`                    (`plugin_fields_fields_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->doQuery($query);
        }

        return true;
    }

    public static function uninstall()
    {
        /** @var DBmysql $DB */
        global $DB;
        $DB->doQuery('DROP TABLE IF EXISTS `' . self::getTable() . '`');

        return true;
    }

    public static function getTypeName($nb = 0)
    {
        return _n('Condition to hide field', 'Conditions to hide field', $nb, 'fields');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!($item instanceof CommonDBTM)) {
            return '';
        }

        return self::createTabEntry(
            self::getTypeName(Session::getPluralNumber()),
            countElementsInTable(self::getTable(), ['plugin_fields_containers_id' => $item->getID()]),
            null,
            'ti ti-forms-off',
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
        /** @var DBmysql $DB */
        global $DB;
        $iterator = $DB->request([
            'SELECT' => [
                self::getTable() . '.*',
            ],
            'FROM'  => self::getTable(),
            'WHERE' => [
                'plugin_fields_containers_id' => $container_id,
            ],
        ]);

        $conditions = [];
        foreach ($iterator as $data) {
            $conditions[] = $data;
        }

        return $conditions;
    }

    /**
     * Get the list of fields of a container, to fill the mandatory
     * "field to hide" dropdown.
     *
     * @param int $container_id Container's ID
     *
     * @return array [field_id => field_label]
     */
    public static function getFieldsForContainer(int $container_id): array
    {
        $choices = [];

        $field_obj = new PluginFieldsField();
        $fields    = $field_obj->find(['plugin_fields_containers_id' => $container_id], 'ranking');
        foreach ($fields as $field) {
            $field['itemtype']     = PluginFieldsField::class;
            $choices[$field['id']] = PluginFieldsLabelTranslation::getLabelFor($field);
        }

        return $choices;
    }

    /**
     * Human readable label of the field targeted by a given condition row.
     *
     * @param array $condition_data Row from the fielddisplaycondition table
     *
     * @return string
     */
    public static function getTargetFieldName(array $condition_data): string
    {
        $field = new PluginFieldsField();
        if (!$field->getFromDB($condition_data['plugin_fields_fields_id'])) {
            return '';
        }

        return PluginFieldsLabelTranslation::getLabelFor($field->fields + ['itemtype' => PluginFieldsField::class]);
    }

    /**
     * Check whether a single field of a container must be hidden for the given item.
     *
     * @param CommonDBTM $item          Item currently displayed
     * @param int        $container_id  Container's ID
     * @param int        $field_id      Field's ID (PluginFieldsField)
     *
     * @return bool true if the field must be displayed, false if it must be hidden
     */
    public function computeDisplayField($item, $container_id, $field_id): bool
    {
        if (!$field_id) {
            return true;
        }

        $displayCondition = new self();
        $found_dc         = $displayCondition->find([
            'itemtype'                     => $item::class,
            'plugin_fields_containers_id'  => $container_id,
            'plugin_fields_fields_id'      => $field_id,
        ]);

        if (!count($found_dc)) {
            //no condition found -> display field
            return true;
        }

        foreach ($found_dc as $data) {
            $displayCondition->getFromDB($data['id']);
            if (!$displayCondition->checkCondition($item)) {
                return false;
            }
        }

        return true;
    }

    public function checkCondition($item)
    {
        $value        = $this->fields['value'];
        $condition    = $this->fields['condition'];
        $searchOption = Search::getOptions($item::class)[$this->fields['search_option']];

        $fields = array_merge($item->fields, $item->input);

        switch ($condition) {
            case PluginFieldsContainerDisplayCondition::SHOW_CONDITION_EQ:
                // '='
                if ($value == $fields[$searchOption['linkfield']]) {
                    return false;
                }

                break;
            case PluginFieldsContainerDisplayCondition::SHOW_CONDITION_NE:
                // '≠'
                if ($value != $fields[$searchOption['linkfield']]) {
                    return false;
                }

                break;
            case PluginFieldsContainerDisplayCondition::SHOW_CONDITION_LT:
            case PluginFieldsContainerDisplayCondition::SHOW_CONDITION_GT:
                // '<';
                if ($fields[$searchOption['linkfield']] > $value) {
                    return false;
                }

                break;
            case PluginFieldsContainerDisplayCondition::SHOW_CONDITION_REGEX:
                //'regex';
                if (
                    PluginFieldsContainerDisplayCondition::checkRegex($value)
                    && preg_match_all($value . 'i', (string) $fields[$searchOption['linkfield']]) > 0
                ) {
                    return false;
                }

                break;
            case PluginFieldsContainerDisplayCondition::SHOW_CONDITION_UNDER:
                $sons = getSonsOf($searchOption['table'], $value);
                if (in_array($fields[$searchOption['linkfield']], $sons)) {
                    return false;
                }

                break;
            case PluginFieldsContainerDisplayCondition::SHOW_CONDITION_NOT_UNDER:
                $sons = getSonsOf($searchOption['table'], $value);
                if (!in_array($fields[$searchOption['linkfield']], $sons)) {
                    return false;
                }

                break;
        }

        return true;
    }

    public function prepareInputForAdd($input)
    {
        // itemtype, search_option, condition, plugin_fields_fields_id must all be set
        if (!isset($input['itemtype'], $input['search_option'], $input['condition']) || empty($input['plugin_fields_fields_id'])) {
            Session::addMessageAfterRedirect(
                __('You must specify a field, an item type, search option and condition.', 'fields'),
                true,
                ERROR,
            );

            return false;
        }

        return parent::prepareInputForAdd($input);
    }

    public function prepareInputForUpdate($input)
    {
        // itemtype, search_option, condition, plugin_fields_fields_id must all be set
        if (!isset($input['itemtype'], $input['search_option'], $input['condition']) || empty($input['plugin_fields_fields_id'])) {
            Session::addMessageAfterRedirect(
                __('You must specify a field, an item type, search option and condition.', 'fields'),
                true,
                ERROR,
            );

            return false;
        }

        return parent::prepareInputForUpdate($input);
    }

    public static function showForTabContainer(CommonGLPI $item, $options = [])
    {
        if (!$item instanceof CommonDBTM) {
            return;
        }

        $displayCondition_id = $options['displaycondition_id'] ?? 0;
        $display_condition    = null;

        if ($displayCondition_id) {
            $display_condition = new self();
            $display_condition->getFromDB($displayCondition_id);
        }

        $container_id = $item->getID();
        $has_fields   = countElementsInTable(PluginFieldsField::getTable(), [
            'plugin_fields_containers_id' => $container_id,
        ]) > 0;
        $twig_params = [
            'container_id'              => $container_id,
            'field_display_conditions'  => self::getDisplayConditionForContainer($container_id),
            'has_fields'                => $has_fields,
        ];

        TemplateRenderer::getInstance()->display('@fields/field_display_conditions.html.twig', $twig_params);
    }

    public function showForm($ID, array $options = [])
    {
        $container_id = $options['plugin_fields_containers_id'];

        $twig_params = [
            'field_display_condition' => $this,
            'container_id'            => $container_id,
            'container_itemtypes'     => (new PluginFieldsContainerDisplayCondition())->getItemtypesForContainer($container_id),
            'container_fields'        => self::getFieldsForContainer($container_id),
            'search_options'          => $this->isNewItem() || empty($this->fields['itemtype'])
                ? []
                : PluginFieldsContainerDisplayCondition::removeBlackListedOption(Search::getOptions($this->fields['itemtype']), $this->fields['itemtype']),
        ];
        TemplateRenderer::getInstance()->display('@fields/forms/field_display_condition.html.twig', $twig_params);

        return true;
    }

    public function getCloneRelations(): array
    {
        return [];
    }
}
