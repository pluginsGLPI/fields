<?php

class PluginFieldsToolbox {

   /**
    * Get a clean system name from a label.
    *
    * @param string $label
    *
    * @return string
    */
   public function getSystemNameFromLabel($label) {

      $name = strtolower($label);

      // 1. remove trailing "s" (plural forms)
      $name = getSingular($name);

      // 2. keep only alphanum
      $name = preg_replace('/[^\da-z]/i', '', $name);

      // 3. if empty, uses a random number
      if (strlen($name) == 0) {
         $name = rand();
      }

      // 4. replace numbers by letters
      $name = $this->replaceIntByLetters($name);

      return $name;
   }

   /**
    * Return system name incremented by given increment.
    *
    * @param string  $name
    * @param integer $increment
    *
    * @return string
    */
   public function getIncrementedSystemName($name, $increment) {
      return $name . $this->replaceIntByLetters((string)$increment);
   }

   /**
    * Replace integers by corresponding letters inside given string.
    *
    * @param string $str
    *
    * @return mixed
    */
   private function replaceIntByLetters($str) {
      return str_replace(
         ['0',    '1',   '2',   '3',     '4',    '5',    '6',   '7',     '8',     '9'],
         ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'],
         $str
      );
   }

   /**
    * Fix dropdown names that were generated prior to Fields 1.9.2.
    *
    * @param Migration $migration
    * @param mixed     $condition
    *
    * @return void
    */
   public function fixFieldsNames(Migration $migration, $condition) {
      global $DB;

      $glpi_version = preg_replace('/^((\d+\.?)+).*$/', '$1', GLPI_VERSION);
      $bad_named_fields = $DB->request(
         [
            'FROM' => PluginFieldsField::getTable(),
            'WHERE' => [
               'name' => [
                  'REGEXP',
                  // Regex will be escaped by PDO in GLPI 10+, but has to be escaped for GLPI < 10
                  version_compare($glpi_version, '10.0', '>=') ? '[0-9]+' : $DB->escape('[0-9]+')
               ],
               $condition,
            ],
         ]
      );

      if ($bad_named_fields->count() === 0) {
         return;
      }

      $migration->displayMessage(__("Fix fields names", "fields"));

      foreach ($bad_named_fields as $field) {
         $old_name = $field['name'];

         // Update field name
         $field['name'] = null;
         $field_obj = new PluginFieldsField();
         $new_name = $field_obj->prepareName($field);
         if ($new_name > 64) {
            // limit fields names to 64 chars (MySQL limit)
            $new_name = substr($new_name, 0, 64);
         }
         while ('dropdown' === $field['type']
                && strlen(getTableForItemType(PluginFieldsDropdown::getClassname($new_name))) > 64) {
            // limit tables names to 64 chars (MySQL limit)
            $new_name = substr($new_name, 0, -1);
         }
         $field['name'] = $new_name;
         $field_obj->update(
            $field,
            false
         );

         $sql_fields_to_rename = [
            $old_name => $field['name'],
         ];

         if ('dropdown' === $field['type']) {
            // Rename dropdown table
            $old_table = getTableForItemType(PluginFieldsDropdown::getClassname($old_name));
            $new_table = getTableForItemType(PluginFieldsDropdown::getClassname($field['name']));

            if ($DB->tableExists($old_table)) {
               $migration->renameTable($old_table, $new_table);
            }

            // Rename foreign keys in containers tables
            $old_fk = getForeignKeyFieldForTable($old_table);
            $new_fk = getForeignKeyFieldForTable($new_table);
            $sql_fields_to_rename[$old_fk] = $new_fk;
         }

         // Rename columns in plugin tables
         foreach ($sql_fields_to_rename as $old_field_name => $new_field_name) {
            $tables_to_update = $DB->request(
               [
                  'SELECT'          => 'TABLE_NAME',
                  'DISTINCT'        => true,
                  'FROM'            => 'INFORMATION_SCHEMA.COLUMNS',
                  'WHERE'           => [
                     'TABLE_SCHEMA'  => $DB->dbdefault,
                     'TABLE_NAME'    => ['LIKE', 'glpi_plugin_fields_%'],
                     'COLUMN_NAME'   => $old_field_name
                  ],
               ]
            );

            foreach ($tables_to_update as $table_to_update) {
               $sql_type = PluginFieldsMigration::getSQLType($field['type']);
               $migration->changeField(
                  $table_to_update['TABLE_NAME'],
                  $old_field_name,
                  $new_field_name,
                  $sql_type
               );
               $migration->migrationOneTable($table_to_update['TABLE_NAME']);
            }
         }
      }
   }
}
