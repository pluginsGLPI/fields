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

class PluginFieldsLabelTranslation extends CommonDBChild
{
    use Glpi\Features\Clonable;

    public static $itemtype = 'itemtype';
    public static $items_id = 'items_id';

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
                  `id`                         INT          {$default_key_sign} NOT NULL auto_increment,
                  `itemtype`                   VARCHAR(30)  NOT NULL,
                  `items_id`                   INT          {$default_key_sign} NOT NULL,
                  `language`                   VARCHAR(5)   NOT NULL,
                  `label`                      VARCHAR(255) DEFAULT NULL,
                  PRIMARY KEY                  (`id`),
                  KEY `itemtype`               (`itemtype`),
                  KEY `items_id`               (`items_id`),
                  KEY `language`               (`language`),
                  UNIQUE KEY `unicity` (`itemtype`, `items_id`, `language`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->query($query) or die($DB->error());
        }

        if ($DB->fieldExists($table, 'plugin_fields_itemtype')) {
            $migration->dropKey($table, 'plugin_fields_itemtype');
            $migration->migrationOneTable($table);
            $migration->changeField($table, 'plugin_fields_itemtype', 'itemtype', 'VARCHAR(30) NOT NULL');
            $migration->addKey($table, 'itemtype');
        }

        if ($DB->fieldExists($table, 'plugin_fields_items_id')) {
            $migration->dropKey($table, 'plugin_fields_items_id');
            $migration->migrationOneTable($table);
            $migration->changeField($table, 'plugin_fields_items_id', 'items_id', "INT {$default_key_sign} NOT NULL");
            $migration->addKey($table, 'items_id');
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
        return _n("Translation", "Translations", $nb);
    }

    public static function createForItem(CommonDBTM $item)
    {
        $translation = new PluginFieldsLabelTranslation();
        $translation->add([
            'itemtype' => $item::getType(),
            'items_id' => $item->getID(),
            'language' => $_SESSION['glpilanguage'],
            'label'    => $item->fields['label']
        ]);
        return true;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        $nb = countElementsInTable(
            self::getTable(),
            [
                'itemtype' => $item::getType(),
                'items_id' => $item->getID(),
            ]
        );
        return self::createTabEntry(self::getTypeName($nb), $nb);
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        self::showTranslations($item);
    }

    /**
     * Display all translations for a label
     *
     * @param CommonDBTM $item Item instance
     *
     * @return void
     */
    public static function showTranslations(CommonDBTM $item)
    {
        $canedit = $item->can($item->getID(), UPDATE);
        $rand    = mt_rand();
        if ($canedit) {
            echo "<div id='viewtranslation" . $item->getID() . "$rand'></div>";

            $ajax_params = [
                'type'     => __CLASS__,
                'itemtype' => $item::getType(),
                'items_id' => $item->fields['id'],
                'id'       => -1
            ];
            echo Html::scriptBlock('
                addTranslation' . $item->getID() . $rand . ' = function() {
                    $("#viewtranslation' . $item->getID() . $rand . '").load(
                        "' . Plugin::getWebDir('fields') . '/ajax/viewtranslations.php",
                        ' . json_encode($ajax_params) . '
                    );
                };
            ');

            echo "<div class='center'>" .
                "<a class='vsubmit' href='javascript:addTranslation" . $item->getID() . "$rand();'>" .
                __('Add a new translation') . "</a></div><br>";
        }

        $obj   = new self();
        $found = $obj->find(
            [
                'itemtype' => $item::getType(),
                'items_id' => $item->getID(),
            ],
            "language ASC"
        );

        if (count($found) > 0) {
            if ($canedit) {
                Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
                $massiveactionparams = ['container' => 'mass' . __CLASS__ . $rand];
                Html::showMassiveActions($massiveactionparams);
            }
            echo "<div class='center'>";
            echo "<table class='tab_cadre_fixehov'><tr class='tab_bg_2'>";
            echo "<th colspan='4'>" . __("List of translations") . "</th></tr>";
            if ($canedit) {
                echo "<th width='10'>";
                echo Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
                echo "</th>";
            }
            echo "<th>" . __("Language", "fields") . "</th>";
            echo "<th>" . __("Label", "fields") . "</th>";
            foreach ($found as $data) {
                echo "<tr class='tab_bg_1' " . ($canedit ? "style='cursor:pointer'
                      onClick=\"viewEditTranslation" . $data['id'] . "$rand();\"" : '') . ">";
                if ($canedit) {
                     echo "<td class='center'>";
                     Html::showMassiveActionCheckBox(__CLASS__, $data["id"]);
                     echo "</td>";
                }
                echo "<td>";
                if ($canedit) {
                    $ajax_params = [
                        'type'     => __CLASS__,
                        'itemtype' => $item::getType(),
                        'items_id' => $item->getID(),
                        'id'       => $data['id']
                    ];
                    echo Html::scriptBlock('
                        viewEditTranslation' . $data['id'] . $rand . ' = function() {
                            $("#viewtranslation' . $item->getID() . $rand . '").load(
                                "' . Plugin::getWebDir('fields') . '/ajax/viewtranslations.php",
                                ' . json_encode($ajax_params) . '
                            );
                        };
                    ');
                }
                echo Dropdown::getLanguageName($data['language']);
                echo "</td><td>";
                echo  $data['label'];
                echo "</td></tr>";
            }
            echo "</table>";
            if ($canedit) {
                $massiveactionparams['ontop'] = false;
                Html::showMassiveActions($massiveactionparams);
                Html::closeForm();
            }
        } else {
            echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
            echo "<th class='b'>" . __("No translation found") . "</th></tr></table>";
        }

        return true;
    }

    /**
     * Display translation form
     *
     * @param string $itemtype Item type
     * @param int    $items_id Item ID
     * @param int    $id       Translation ID (defaults to -1)
     *
     * @return void
     */
    public function showFormForItem($itemtype, $items_id, $id = -1)
    {
        if ($id > 0) {
            $this->check($id, READ);
        } else {
            // Create item
            $this->check(-1, CREATE);
        }
        $this->showFormHeader();
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Language') . "&nbsp;:</td>";
        echo "<td>";
        echo "<input type='hidden' name='itemtype' value='{$itemtype}'>";
        echo "<input type='hidden' name='items_id' value='{$items_id}'>";
        if ($id > 0) {
            echo Dropdown::getLanguageName($this->fields['language']);
        } else {
            Dropdown::showLanguages(
                "language",
                ['display_none' => false,
                    'value'        => $_SESSION['glpilanguage'],
                    'used'         => self::getAlreadyTranslatedForItem(
                        $itemtype,
                        $items_id
                    )
                ]
            );
        }
        echo "</td><td colspan='2'>&nbsp;</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='label'>" . __('Label') . "</label></td>";
        echo "<td colspan='3'>";
        echo Html::input('label', [
            'value' => $this->fields["label"],
            'id'    => 'label'
        ]);
        echo "</td></tr>";

        $this->showFormButtons();
        return true;
    }

    /**
     * Get already translated languages for item
     *
     * @param string $itemtype Item type
     * @param int    $items_id Item ID
     *
     * @return array of already translated languages
    */
    public static function getAlreadyTranslatedForItem($itemtype, $items_id)
    {
        global $DB;

        $iterator = $DB->request(
            [
                'FROM'  => self::getTable(),
                'WHERE' => [
                    'itemtype' => $itemtype,
                    'items_id' => $items_id,
                ]
            ]
        );
        $tab = [];
        foreach ($iterator as $data) {
            $tab[$data['language']] = $data['language'];
        }
        return $tab;
    }

    /**
     * Get translated label for item
     *
     * @param array $item Item
     *
     * @return string
     */
    public static function getLabelFor(array $item)
    {
        $obj   = new self();
        $found = $obj->find(['itemtype' => $item['itemtype'],
            'items_id' => $item['id'],
            'language' => $_SESSION['glpilanguage']
        ]);

        if (count($found) > 0) {
            return array_values($found)[0]['label'];
        }

        return $item['label'] ?? '';
    }
}
