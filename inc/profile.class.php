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

class PluginFieldsProfile extends CommonDBRelation
{
    use Glpi\Features\Clonable;

    public static $itemtype_1 = PluginFieldsContainer::class;
    public static $items_id_1 = 'plugin_fields_containers_id';
    public static $itemtype_2 = Profile::class;
    public static $items_id_2 = 'profiles_id';

    public static function install(Migration $migration)
    {
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $table = self::getTable();

        if (!$DB->tableExists($table)) {
            $migration->displayMessage(sprintf(__("Installing %s"), $table));

            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`                                INT {$default_key_sign} NOT NULL auto_increment,
                  `profiles_id`                       INT {$default_key_sign} NOT NULL DEFAULT '0',
                  `plugin_fields_containers_id`       INT {$default_key_sign} NOT NULL DEFAULT '0',
                  `right`                             CHAR(1)  DEFAULT NULL,
                  PRIMARY KEY                         (`id`),
                  KEY `profiles_id`                   (`profiles_id`),
                  KEY `plugin_fields_containers_id`   (`plugin_fields_containers_id`)
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


    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return self::createTabEntry(_n("Profile", "Profiles", 2));
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        $profile = new Profile();
        $found_profiles = $profile->find();

        $fields_profile = new self();
        echo "<form name='form' method='post' action='" . $fields_profile->getFormURL() . "'>";
        echo "<div class='spaced' id='tabsbody'>";
        echo "<table class='tab_cadre_fixe'>";

        echo "<tr><th colspan='2'>" . _n("Profile", "Profiles", 2) . "</th></tr>";
        foreach ($found_profiles as $profile_item) {
            //get right for current profile
            $found = $fields_profile->find([
                'profiles_id' => $profile_item['id'],
                'plugin_fields_containers_id' => $item->fields['id']
            ]);
            $first_found = array_shift($found);

            //display right
            echo "<tr>";
            echo "<td>" . $profile_item['name'] . "</td>";
            echo "<td>";
            Profile::dropdownRight(
                "rights[" . $profile_item['id'] . "]",
                ['value' => $first_found['right']]
            );
            echo "</td>";
            echo "<tr>";
        }
        echo "<ul>";
        echo "<tr><td class='tab_bg_2 center' colspan='2'>";
        echo "<input type='hidden' name='plugin_fields_containers_id' value='" . $item->fields['id'] . "' />";
        echo "<input type='submit' name='update' value=\"" . _sx("button", "Save") . "\" class='submit'>";
        echo "</td>";
        echo "</tr>";
        echo "</table></div>";
        Html::closeForm();
    }

    public static function updateProfile($input)
    {
        $fields_profile = new self();
        foreach ($input['rights'] as $profiles_id => $right) {
            $found = $fields_profile->find(
                [
                    'profiles_id' => $profiles_id,
                    'plugin_fields_containers_id' => $input['plugin_fields_containers_id']
                ]
            );
            if (count($found) > 0) {
                 $first_found = array_shift($found);

                 $fields_profile->update([
                     'id'                          => $first_found['id'],
                     'profiles_id'                 => $profiles_id,
                     'plugin_fields_containers_id' => $input['plugin_fields_containers_id'],
                     'right'                       => $right
                 ]);
            } else {
                $fields_profile->add([
                    'profiles_id'                 => $profiles_id,
                    'plugin_fields_containers_id' => $input['plugin_fields_containers_id'],
                    'right'                       => $right
                ]);
            }
        }

        return true;
    }

    public static function createForContainer(PluginFieldsContainer $container)
    {
        $profile = new Profile();
        $found_profiles = $profile->find();

        $fields_profile = new self();
        foreach ($found_profiles as $profile_item) {
            $fields_profile->add([
                'profiles_id'                 => $profile_item['id'],
                'plugin_fields_containers_id' => $container->fields['id'],
                'right'                       => CREATE
            ]);
        }
        return true;
    }

    public static function addNewProfile(Profile $profile)
    {
        $containers = new PluginFieldsContainer();
        $found_containers = $containers->find();

        $fields_profile = new self();
        foreach ($found_containers as $container) {
            $fields_profile->add([
                'profiles_id'                 => $profile->fields['id'],
                'plugin_fields_containers_id' => $container['id']
            ]);
        }
        return true;
    }

    public static function deleteProfile(Profile $profile)
    {
        $fields_profile = new self();
        $fields_profile->deleteByCriteria(['profiles_id' => $profile->fields['id']]);
        return true;
    }
}
