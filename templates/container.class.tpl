<?php

class %%CLASSNAME%% extends PluginFieldsAbstractContainerInstance
{
   static $rightname = '%%ITEMTYPE_RIGHT%%';

   static function install() {
      global $DB;

      $default_charset = DBConnection::getDefaultCharset();
      $default_collation = DBConnection::getDefaultCollation();
      $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

      $obj = new self();
      $table = $obj->getTable();
      $migration = new PluginFieldsMigration(0);

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
            while ($data = $DB->fetchAssoc($result)) {
               if (str_starts_with($data['Field'], 'itemtype_') && $data['Null'] !== 'YES') {
                  $migration->changeField($table, $data['Field'], $data['Field'], "varchar(100) DEFAULT NULL");
                  $changed = true;
               }
            }
            if ($changed) {
               $migration->executeMigration();
            }
         }
      }

      /**
      * Adds the 'entities_id' field to the database table and migrates existing data.
      *
      * This block ensures that the 'entities_id' field is created and populated if it
      * associated item type requires entity assignment
      */
      if (getItemForItemtype("%%ITEMTYPE%%")->isEntityAssign() && !$DB->fieldExists($table, 'entities_id')) {
         $migration->addField($table, 'entities_id', 'fkey', ['after' => 'plugin_fields_containers_id']);
         $migration->addKey($table, 'entities_id');
         $migration->executeMigration();

         // migrate data
         $query = $DB->buildUpdate(
               $table,
               ['entities_id' => new QueryParam()],
               ['id'       => new QueryParam()]
         );
         $stmt = $DB->prepare($query);

         //load all entries
         $data = $DB->request(
            [
               'SELECT'    => '*',
               'FROM'      => $table,
            ]
         );

         foreach ($data as $fields) {
            //load related item
            $related_item = $DB->request(
               [
                  'SELECT'    => '*',
                  'FROM'      => getTableForItemType($fields['itemtype']),
                  'WHERE'     => [
                        'id'      => $fields['items_id'],
                     ]
               ]
            )->current();

            if ($related_item === null) {
                continue;
            }

            //update if needed
            if ($fields['entities_id'] != $related_item['entities_id']) {
               $stmt->bind_param(
                  'ii',
                  $related_item['entities_id'],
                  $fields['id']
               );
               $stmt->execute();
            }
         }
      }

      /**
      * Adds the 'is_recursive' field to the database table and migrates existing data.
      *
      * This block ensures that the 'is_recursive' field is created and populated if it
      * associated item type requires recursive assignment
      */
      if (getItemForItemtype("%%ITEMTYPE%%")->maybeRecursive() && !$DB->fieldExists($table, 'is_recursive')) {
         $migration->addField($table, 'is_recursive', 'bool', ['after'  => 'entities_id']);
         $migration->addKey($table, 'is_recursive');
         $migration->executeMigration();

         //migrate data
         $query = $DB->buildUpdate(
            $table,
            ['is_recursive' => new QueryParam()],
            ['id'       => new QueryParam()]
         );
         $stmt = $DB->prepare($query);

         //load all entries
         $data = $DB->request(
            [
               'SELECT'    => '*',
               'FROM'      => $table,
            ]
         );

         foreach ($data as $fields) {
            //load related item
            $related_item = $DB->request(
               [
                  'SELECT'    => '*',
                  'FROM'      => getTableForItemType($fields['itemtype']),
                  'WHERE'     => [
                        'id'      => $fields['items_id'],
                     ]
               ]
            )->current();

            if ($related_item === null) {
                continue;
            }

            //update if needed
            if ($fields['is_recursive'] != $related_item['is_recursive']) {
               $stmt->bind_param(
                  'ii',
                  $related_item['is_recursive'],
                  $fields['id']
               );
               $stmt->execute();
            }
         }
      }
   }

   static function uninstall() {
      global $DB;

      $obj = new self();
      return $DB->query("DROP TABLE IF EXISTS `".$obj->getTable()."`");
   }

   static function addField($fieldname, $type, array $options) {
      $migration = new PluginFieldsMigration(0);

      $sql_fields = PluginFieldsMigration::getSQLFields($fieldname, $type, $options);
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
