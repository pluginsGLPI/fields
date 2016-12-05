<?php

class PluginFieldsMigration {

   static function install(Migration $migration, $version) {
      $fields_migration = new self;

      if (TableExists("glpi_plugin_customfields_fields")) {
         if (!$fields_migration->updateFromCustomfields()) return false;
      }

      return true;
   }

   static function uninstall() {
      return true;
   }

   function updateFromCustomfields($glpi_version = "0.80") {
      global $DB;

      //TODO : REWRITE customfield update
      return true;
   }

   function migrateCustomfieldTypes($old_type) {
      $types = array(
         'sectionhead' => 'header',
         'general'     => 'text',
         'money'       => 'text',
         'note'        => 'textarea',
         'text'        => 'textarea',
         'number'      => 'number',
         'dropdown'    => 'dropdown',
         'yesno'       => 'yesno',
         'date'        => 'date'
      );

      return $types[$old_type];
   }

   static function getSQLType($field_type) {
      $types = array(
         'text'     => 'VARCHAR(255) DEFAULT NULL',
         'url'      => 'TEXT DEFAULT NULL',
         'textarea' => 'TEXT         DEFAULT NULL',
         'number'   => 'VARCHAR(255) DEFAULT NULL',
         'dropdown' => 'INT(11)      NOT NULL DEFAULT 0',
         'yesno'    => 'INT(11)      NOT NULL DEFAULT 0',
         'date'     => 'VARCHAR(255) DEFAULT NULL',
         'datetime' => 'VARCHAR(255) DEFAULT NULL',
         'dropdownuser' => 'INT(11)  NOT NULL DEFAULT 0'
      );

      return $types[$field_type];
   }
}
