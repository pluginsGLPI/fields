<?php

class %%CLASSNAME%% extends CommonDBTM
{
   static $rightname = '%%ITEMTYPE_RIGHT%%';

   static function install() {
      global $DB;

      $default_charset = DBConnection::getDefaultCharset();
      $default_collation = DBConnection::getDefaultCollation();
      $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

      $obj = new self();
      $table = $obj->getTable();

      // create Table
      if (!$DB->tableExists($table)) {
         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`                               INT          {$default_key_sign} NOT NULL auto_increment,
                  `items_id`                         INT          {$default_key_sign} NOT NULL,
                  `itemtype`                         VARCHAR(255) DEFAULT '%%ITEMTYPE%%',
                  `plugin_fields_containers_id`      INT          {$default_key_sign} NOT NULL DEFAULT '%%CONTAINER%%',
                  PRIMARY KEY                        (`id`),
                  UNIQUE INDEX `itemtype_item_container`
                     (`itemtype`, `items_id`, `plugin_fields_containers_id`)
               ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
         $DB->query($query) or die ($DB->error());
      } else {
         // 1.15.4
         // fix nullable state for 'glpi_item' field
         $result = $DB->query("SHOW COLUMNS FROM `$table`");
         if ($result && $DB->numrows($result) > 0) {
            $changed = false;
            $migration = new PluginFieldsMigration(0);
            while ($data = $DB->fetchAssoc($result)) {
               if (str_starts_with($data['Field'], 'itemtype_') && $data['Null'] !== 'YES') {
               Toolbox::logDebug($data);
                  $migration->changeField($table, $data['Field'], $data['Field'], "varchar(100) DEFAULT NULL");
                  $changed = true;
               }
            }
            if ($changed) {
               $migration->executeMigration();
            }
         }
      }
   }

   static function uninstall() {
      global $DB;

      $obj = new self();
      return $DB->query("DROP TABLE IF EXISTS `".$obj->getTable()."`");
   }

   static function addField($fieldname, $type) {
      $migration = new PluginFieldsMigration(0);

      $sql_fields = PluginFieldsMigration::getSQLFields($fieldname, $type);
      foreach ($sql_fields as $sql_field_name => $sql_field_type) {
         $migration->addField(self::getTable(), $sql_field_name, $sql_field_type);
      }

      $migration->migrationOneTable(self::getTable());
   }

   static function removeField($fieldname, $type) {
      $migration = new PluginFieldsMigration(0);

      $sql_fields = PluginFieldsMigration::getSQLFields($fieldname, $type);
      foreach (array_keys($sql_fields) as $sql_field_name) {
         $migration->dropField(self::getTable(), $sql_field_name);
      }

      $migration->migrationOneTable(self::getTable());
   }
}
