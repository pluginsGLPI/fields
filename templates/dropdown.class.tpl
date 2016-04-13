<?php

class %%CLASSNAME%% extends CommonTreeDropdown {
   var $field_name      = "%%FIELDNAME%%";

   static function getTypeName($nb=0) {
      return "%%LABEL%%";
   }

   static function install() {
      global $DB;

      $obj = new self();
      $table = $obj->getTable();

      if (!TableExists($table)) {
         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`                                      INT(11)        NOT NULL auto_increment,
                  `name`                                    VARCHAR(255)   DEFAULT NULL,
                  `completename`                            TEXT           DEFAULT NULL,
                  `comment`                                 TEXT           DEFAULT NULL,
                  `plugin_fields_%%FIELDNAME%%dropdowns_id` INT(11)        DEFAULT NULL,
                  `level`                                   INT(11)        DEFAULT NULL,
                  `ancestors_cache`                         TEXT           DEFAULT NULL,
                  `sons_cache`                              TEXT           DEFAULT NULL,
                  `entities_id`                             INT(11)        NOT NULL DEFAULT '0',
                  `is_recursive`                            TINYINT(1)     NOT NULL DEFAULT '0',
                  PRIMARY KEY                               (`id`),
                  KEY                                       `entities_id`  (`entities_id`),
                  KEY                                       `is_recursive` (`is_recursive`),
                  KEY                                       `plugin_fields_%%FIELDNAME%%dropdowns_id`
                                                            (`plugin_fields_%%FIELDNAME%%dropdowns_id`)
               ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
         $DB->query($query) or die ($DB->error());
      }
   }

   static function uninstall() {
      global $DB;

      $obj = new self();
      return $DB->query("DROP TABLE IF EXISTS `".$obj->getTable()."`");
   }
}