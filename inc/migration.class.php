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

class PluginFieldsMigration extends Migration {

   function __construct($ver = "") {
      parent::__construct($ver);
   }

   static function install(Migration $migration, $version) {
      global $DB;

      $fields_migration = new self;

      if ($DB->tableExists("glpi_plugin_customfields_fields")) {
         if (!$fields_migration->updateFromCustomfields()) {
            return false;
         }
      }

      return true;
   }

   static function uninstall() {
      return true;
   }

   function updateFromCustomfields($glpi_version = "0.80") {
      //TODO : REWRITE customfield update
      return true;
   }

   function displayMessage($msg) {
      Session::addMessageAfterRedirect($msg);
   }

   function migrateCustomfieldTypes($old_type) {
      $types = [
         'sectionhead' => 'header',
         'general'     => 'text',
         'money'       => 'text',
         'note'        => 'textarea',
         'text'        => 'textarea',
         'number'      => 'number',
         'dropdown'    => 'dropdown',
         'yesno'       => 'yesno',
         'date'        => 'date'
      ];

      return $types[$old_type];
   }

   static function getSQLType($field_type) {

      global $CFG_GLPI;
      $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

      $types = [
         'text'                     => 'VARCHAR(255) DEFAULT NULL',
         'url'                      => 'TEXT DEFAULT NULL',
         'textarea'                 => 'TEXT         DEFAULT NULL',
         'number'                   => 'VARCHAR(255) DEFAULT NULL',
         'dropdown'                 => "INT          {$default_key_sign} NOT NULL DEFAULT 0",
         'yesno'                    => 'INT          NOT NULL DEFAULT 0',
         'date'                     => 'VARCHAR(255) DEFAULT NULL',
         'datetime'                 => 'VARCHAR(255) DEFAULT NULL',
      ];


      //get All available Model class and assets
      foreach ($CFG_GLPI['state_types'] as $class) {
         $itemtype = new $class();
         $model_class  = $itemtype->getModelClass();
         if ($model_class != null) {
            $types['dropdown-'.$model_class::getType()] = "INT {$default_key_sign}  NOT NULL DEFAULT 0";;
         }
         $types['dropdown-'.$class::getType()] = "INT {$default_key_sign}  NOT NULL DEFAULT 0";
      }

      //complete Model / Type list
      foreach ($CFG_GLPI['dictionnary_types'] as $class) {
         if (strpos(strtolower($class), "model") !== false) {
            $types['dropdown-'.$class::getType()] = "INT {$default_key_sign}  NOT NULL DEFAULT 0";
         } else if (strpos(strtolower($class), "type") !== false) {
            $types['dropdown-'.$class::getType()] = "INT {$default_key_sign}  NOT NULL DEFAULT 0";
         }
      }

      //complete Asset list
      foreach ($CFG_GLPI['state_types'] as $class) {
         $types['dropdown-'.$class::getType()] = "INT {$default_key_sign}  NOT NULL DEFAULT 0";
      }

      $types['dropdown-'.User::getType()] = "INT {$default_key_sign}  NOT NULL DEFAULT 0";
      $types['dropdown-'.Group::getType()] =  "INT {$default_key_sign}  NOT NULL DEFAULT 0";
      $types['dropdown-'.OperatingSystem::getType()] =  "INT {$default_key_sign}  NOT NULL DEFAULT 0";

      //remove slashes added by GLPI (useful for namespaced GLPI Object ie: GLPI\SocketModel)
      return $types[Toolbox::stripslashes_deep($field_type)];
   }
}
