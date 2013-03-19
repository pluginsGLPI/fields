<?php

class PluginFieldsMigration {

   static function install(Migration $migration) {
      $fields_migration = new self;

      return true;

      if (TableExists("glpi_plugin_customfields_fields")) {
         return $fields_migration->updateFromCustomfields();
      }

      return true;
   }

   static function uninstall() {
      return true;
   }
   
   function updateFromCustomfields($glpi_version = "0.80") {
      global $DB;

      //alter fields table encoding (avoid sql errors)
      $res = $DB->query("ALTER TABLE glpi_plugin_customfields_fields 
                         CONVERT TO CHARACTER SET utf8;");

      //get all dropdowns 
      $query_dropdown = "SELECT * FROM glpi_plugin_customfields_dropdowns";
      $res_dropdown = $DB->query($query_dropdown);
      $dropdowns = array();
      while ($data_dropdowns = $DB->fetch_assoc($res_dropdown)) {
         $dropdowns[$data_dropdowns['id']] = $data_dropdowns;
      }

      //get all dropdowns values
      $query_dropdown_values = "SELECT * FROM glpi_plugin_customfields_dropdowns";
      $res_dropdown_values = $DB->query($query_dropdown_values);
      $dropdowns_values = array();
      while ($data_dropdowns_values = $DB->fetch_assoc($res_dropdown_values)) {
         $dropdowns_values[$data_dropdowns_values['id']] = $data_dropdowns_values;
      }

      //get all types enabled
      $query_itemtypes = "SELECT itemtype 
         FROM glpi_plugin_customfields_itemtypes 
         WHERE enabled > 0
         AND itemtype != 'Version'";
      $res_itemtypes = $DB->query($query_itemtypes);
      while ($data_itemtypes = $DB->fetch_assoc($res_itemtypes)) {
         $itemtype       = $data_itemtypes['itemtype'];
         $itemtype_table = "glpi_plugin_customfields_".strtolower(getPlural($itemtype));

         //get all fields description for current type (systemname, label, type, etc)
         $query_desc_itemtype = "SELECT 
               ic.COLUMN_NAME as fieldname, ic.COLUMN_TYPE as fieldtype, 
               fi.label, fi.data_type, fi.sort_order, fi.default_value, fi.entities
            FROM INFORMATION_SCHEMA.COLUMNS ic
            INNER JOIN glpi_plugin_customfields_fields fi
               ON fi.system_name = ic.COLUMN_NAME
               AND fi.itemtype = '$itemtype'
               AND fi.deleted = 0
            WHERE ic.table_schema = '0337-glpi-0.80.2' 
               AND ic.table_name = '$itemtype_table'
               AND ic.COLUMN_NAME != 'id'
         ";
         $res_desc_itemtype = $DB->query($query_desc_itemtype);
         $fields = array();
         while ($data_fields = $DB->fetch_assoc($res_desc_itemtype)) { 
            $fields[$data_fields['fieldname']] = $data_fields;
         }

         //get all values for current type
         $query_values_itemtype = "SELECT * FROM $itemtype_table";
         $res_values_itemtype = $DB->query($query_values_itemtype);
         $values = array();
         while ($data_values = $DB->fetch_assoc($res_values_itemtype)) { 
            $values[$data_values['id']] = $data_values;
         }

         /*echo "<h2>Dropdowns</h2>";
         Html::printCleanArray($dropdowns);
         echo "<h2>Dropdowns values</h2>";
         Html::printCleanArray($dropdowns_values);
         echo "<h2>Fields</h2>";
         Html::printCleanArray($fields);
         echo "<h2>Values</h2>";
         Html::printCleanArray($values);*/

         //create a container for this type
         $container = new PluginFieldsContainer;
         $containers_id = $container->add(array(
            'name'         => 'migrated'.mt_rand(), 
            'label'        => 'Custom Fields', 
            'itemtype'     => $itemtype, 
            'type'         => 'tab', 
            'entities_id'  => 0, 
            'is_recursive' => 1, 
            'is_active'    => 1
         ));

         //insert fields for this type
         $field_obj = new PluginFieldsField;
         foreach ($fields as &$field) {
            if ($field['data_type'] === "dropdown") continue;
            $fields_id = $field_obj->add(array(
               'name'                        => $field['fieldname'],
               'label'                       => $field['label'],
               'type'                        => $this->migrateCustomfieldTypes($field['data_type']),
               'plugin_fields_containers_id' => $containers_id,
               'ranking'                     => $field['sort_order'],
               'default_value'               => $field['default_value']
            ));

            //store id for re-use in values insertion
            $field['newId'] = $fields_id;
         }

         //insert values for this type
         $value_obj = new PluginFieldsValue;
         foreach ($values as $old_id => $value_line) {
            foreach ($value_line as $fieldname => $value) {
               if ($fieldname === "id") continue;
               if ($fields[$fieldname]['data_type'] === "dropdown") continue;
               $values_id = $value_obj->add(array(
                  $this->migrateCustomfieldValue($fields[$fieldname]['data_type']) => $value,
                  'items_id' => $old_id,
                  'itemtype' => $itemtype,
                  'plugin_fields_containers_id' => $containers_id,
                  'plugin_fields_fields_id' => $fields[$fieldname]['newId']
               ));
            }
         }
      }
      
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

   function migrateCustomfieldValue($old_type) {
      $types = array(
         'sectionhead' => 'value_varchar',
         'general'     => 'value_varchar',
         'money'       => 'value_varchar',
         'note'        => 'value_text',
         'text'        => 'value_text',
         'number'      => 'value_varchar',
         'dropdown'    => 'value_int',
         'yesno'       => 'value_int',
         'date'        => 'value_varchar'    
      );

      return $types[$old_type];
   }
}