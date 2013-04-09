<?php

class PluginFieldsMigration {

   static function install(Migration $migration) {
      $fields_migration = new self;

      if (TableExists("glpi_plugin_customfields_fields")) {
         return $fields_migration->updateFromCustomfields();
      }

      return true;
   }

   static function uninstall() {
      return true;
   }
   
   function updateFromCustomfields($glpi_version = "0.80") {
      global $DB, $LANG;

      set_time_limit(900);
      ini_set('memory_limit','256M');

      $rand = mt_rand();
      $values_dumped = false;

      //alter fields table encoding (avoid randomly sql errors)
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
      $query_dropdown_values = "SELECT dropdown.system_name, item.* 
         FROM glpi_plugin_customfields_dropdownsitems item
         LEFT JOIN glpi_plugin_customfields_dropdowns dropdown
            ON dropdown.id = item.plugin_customfields_dropdowns_id
         ORDER BY item.level ASC";
      $res_dropdown_values = $DB->query($query_dropdown_values);
      $dropdowns_values = array();
      while ($data_dropdowns_values = $DB->fetch_assoc($res_dropdown_values)) {
         $dropdowns_values[$data_dropdowns_values['system_name']][$data_dropdowns_values['id']] 
                  = $data_dropdowns_values;
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
               fi.system_name, fi.label, fi.data_type, 
               fi.sort_order, fi.default_value, fi.entities
            FROM glpi_plugin_customfields_fields fi
            WHERE fi.itemtype = '$itemtype'
               AND fi.deleted = 0
         ";
         $res_desc_itemtype = $DB->query($query_desc_itemtype);
         $fields = array();
         while ($data_fields = $DB->fetch_assoc($res_desc_itemtype)) { 
            $fields[$data_fields['system_name']] = $data_fields;
         }

         //get all values for current type
         $query_values_itemtype = "SELECT * FROM $itemtype_table";
         $res_values_itemtype = $DB->query($query_values_itemtype);
         $values = array();
         while ($data_values = $DB->fetch_assoc($res_values_itemtype)) { 
            $values[$data_values['id']] = $data_values;
         }

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
         $dropdowns_ids = array();
         foreach ($fields as &$field) {
            $systemname = $field['system_name'];
            $fields_id = $field_obj->add(array(
               'name'                        => $systemname,
               'label'                       => mysql_real_escape_string($field['label']),
               'type'                        => $this->migrateCustomfieldTypes($field['data_type']),
               'plugin_fields_containers_id' => $containers_id,
               'ranking'                     => $field['sort_order'],
               'default_value'               => $field['default_value']
            ));

            //get dropdown items and insert them
            if ($field['data_type'] === "dropdown") {
               $dropdowns_current_values = $dropdowns_values[$systemname];
               $dropdown_classname = PluginFieldsDropdown::getClassname($systemname);
               //include file if autoload doesn't work
               if (!class_exists($dropdown_classname)) {
                  require_once $class_filename = $systemname."dropdown.class.php"; 
               }
               $dropdown_obj = new $dropdown_classname;
                  
               foreach ($dropdowns_current_values as $d_value) {

                  $parents_id = 0;
                  //try to find parent id
                  if ($d_value['level'] > 1) {
                     $tree_parts = explode(" > ", $d_value['completename']);
                     
                     //we remove current level
                     array_pop($tree_parts);

                     //get the parent name
                     $parent_name = array_pop($tree_parts);
                     
                     //search id of the parent
                     $found = $dropdown_obj->find("`name` = '$parent_name' 
                                                   AND `level` = '".($d_value['level']-1)."'");
                     if (!empty($found)) {
                        $item_found = array_shift($found);
                        $parents_id = $item_found['id'];
                     }
                  }

                  //add current dropdown item
                  $d_items_id = $dropdown_obj->add(array(
                     'name'                                      => $d_value['name'],
                     'comment'                                   => $d_value['comment'],
                     'plugin_fields_'.$systemname.'dropdowns_id' => $parents_id,
                     'entities_id'                               => $d_value['entities_id'],
                     'is_recursive'                              => $d_value['is_recursive']
                  ));

                  //store in an array the correspondance between old id and new id
                  $dropdowns_ids[$systemname][$d_value['id']] = $d_items_id;
               }
            }
            
            //store id for re-use in values insertion
            $field['newId'] = $fields_id;
         }

         //prepare insert of values for this type
         $value_obj = new PluginFieldsValue;
         if (count($values) > 0) {
            $values_dumped = true;
            foreach ($values as $old_id => $value_line) {
               foreach ($value_line as $fieldname => $value) {
                  if ($fieldname === "id") continue;
                  if ($value === "" || $value === "NULL" && $value === NULL) continue;

                  if ($fields[$fieldname]['data_type'] === "dropdown" && !empty($value)) {
                     //find the new the new id of dropdowns
                     $value = $dropdowns_ids[$fieldname][$value];
                  }                  
                  
                  //get correct key for storing value
                  $value_type = $this->migrateCustomfieldValue($fields[$fieldname]['data_type']);
             
                  //prepare values insertion sql and dump it into a file .
                  //(too long to be executed in this script)
                  $query = "INSERT INTO glpi_plugin_fields_values (
                        `$value_type`, `items_id`, `itemtype`, 
                        `plugin_fields_containers_id`, `plugin_fields_fields_id`
                     ) VALUES (
                        '".mysql_real_escape_string($value)."', $old_id, '$itemtype', 
                        $containers_id, ".$fields[$fieldname]['newId']."
                     );\n";
                  file_put_contents(GLPI_DUMP_DIR."/customfields_import_values.$rand.sql", 
                                    $query, FILE_APPEND);
               }
            }
         }
      }

      //inform user (his dump is in files/_dumps location)
      if ($values_dumped) {
         Session::addMessageAfterRedirect($LANG['fields']['install'][1]);
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