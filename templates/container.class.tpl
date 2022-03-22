<?php

class %%CLASSNAME%% extends CommonDBTM
{
   static $rightname = '%%ITEMTYPE_RIGHT%%';

   static function install($containers_id = 0) {
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
      }

      // and its fields
      if ($containers_id) {
         foreach ($DB->request(PluginFieldsField::getTable(), [
            'plugin_fields_containers_id' => $containers_id
         ]) as $field) {
            self::addField($field['name'], $field['type']);
         }
      }
   }

   static function uninstall() {
      global $DB;

      $obj = new self();
      return $DB->query("DROP TABLE IF EXISTS `".$obj->getTable()."`");
   }

   static function addField($fieldname, $type) {
      if ($type != 'header' && $type != 'glpi_object') {
         $sql_type = PluginFieldsMigration::getSQLType($type);

         $migration = new PluginFieldsMigration(0);
         $migration->addField(self::getTable(), $fieldname, $sql_type);
         $migration->migrationOneTable(self::getTable());
      }

      if ($type != 'header' && $type == 'glpi_object') {
         $sql_type = PluginFieldsMigration::getSQLType($type);

         $migration = new PluginFieldsMigration(0);
         foreach ($sql_type as $column_name => $column_definition) {
            $migration->addField(self::getTable(), $fieldname."_".$column_name, $column_definition);
         }
         $migration->migrationOneTable(self::getTable());
      }
   }

   static function removeField($fieldname, $type) {
      if ($type != 'header' && $type != 'glpi_object') {
         $migration = new PluginFieldsMigration(0);
         $migration->dropField(self::getTable(), $fieldname);
         $migration->migrationOneTable(self::getTable());
      }

      if ($type != 'header' && $type == 'glpi_object') {
         $migration = new PluginFieldsMigration(0);
         $migration->dropField(self::getTable(), $fieldname.'_itemtype');
         $migration->dropField(self::getTable(), $fieldname.'_items_id');
         $migration->migrationOneTable(self::getTable());
      }
   }
}
