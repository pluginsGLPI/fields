<?php

class %%CLASSNAME%% extends CommonDBTM
{
   static $rightname = '%%ITEMTYPE_RIGHT%%';

   static function install() {
      global $DB;

      $obj = new self();
      $table = $obj->getTable();

      if (!TableExists($table)) {
         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`                               INT(11)      NOT NULL auto_increment,
                  `items_id`                         INT(11)      NOT NULL,
                  `itemtype`                         VARCHAR(255) DEFAULT '%%ITEMTYPE%%',
                  `plugin_fields_containers_id`      INT(11)      NOT NULL DEFAULT '%%CONTAINER%%',
                  PRIMARY KEY                        (`id`),
                  UNIQUE INDEX `itemtype_item_container`
                     (`itemtype`, `items_id`, `plugin_fields_containers_id`)
               ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
         $DB->query($query) or die ($DB->error());
      }
   }

   static function uninstall() {
      global $DB;

      $obj = new self();
      return $DB->query("DROP TABLE IF EXISTS `".$obj->getTable()."`");
   }

   static function addField($fieldname, $type) {
      global $DB;

      $sql_type = PluginFieldsMigration::getSQLType($type);

      $obj = new self();
      return $DB->query("ALTER TABLE  `".$obj->getTable()."`
         ADD COLUMN `$fieldname` $sql_type
      ");
   }

   static function removeField($fieldname) {
      global $DB;

      $obj = new self();
      return $DB->query("ALTER TABLE  `".$obj->getTable()."`
         DROP COLUMN `$fieldname`
      ");
   }
}
