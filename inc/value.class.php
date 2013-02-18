<?php

class PluginFieldsValue extends CommonDBTM {
   
   static function install(Migration $migration) {
      global $DB;

      $obj = new self();
      $table = $obj->getTable();

      if (!TableExists($table)) {
         $migration->displayMessage("Installing $table");

         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`                                INT(11)        NOT NULL auto_increment,
                  `value`                             TEXT           DEFAULT NULL,
                  `items_id`                          INT(11)        NOT NULL DEFAULT '0',
                  `plugin_fields_containers_id`       INT(11)        NOT NULL DEFAULT '0',
                  `plugin_fields_fields_id`           INT(11)        NOT NULL DEFAULT '0',
                  PRIMARY KEY                         (`id`),
                  KEY `items_id`                      (`items_id`),
                  KEY `plugin_fields_containers_id`   (`plugin_fields_containers_id`)
                  KEY `plugin_fields_fields_id`       (`plugin_fields_fields_id`)
               ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"; 
            $DB->query($query) or die ($DB->error());
      }

      return true;
   }

   
   static function uninstall() {
      global $DB;

      $obj = new self();
      $DB->query("DROP TABLE IF EXISTS `".$obj->getTable()."`");

      return true;
   }

}