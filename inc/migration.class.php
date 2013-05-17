<?php

class PluginFieldsMigration {

   static function install(Migration $migration, $version) {
      set_time_limit(900);
      ini_set('memory_limit','1024M');

      $fields_migration = new self;

      if (TableExists("glpi_plugin_customfields_fields")) {
         if (!$fields_migration->updateFromCustomfields()) return false;
      }

      if ($version === "2.0") {
         if (!$fields_migration->migrateNewSchema()) return false;
      }
      return true;
   }

   static function uninstall() {
      return true;
   }
   
   function updateFromCustomfields($glpi_version = "0.80") {
      global $DB, $LANG;

      $query = "";

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
            GROUP BY fi.system_name, fi.data_type
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
            $systemname = "";
            if ($field['data_type'] === "dropdown") {
               $systemname = $field['system_name'];
            } 
            
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
                  $datatype = $fields[$fieldname]['data_type'];
                  
                  //pass if empty value
                  if ($datatype == "sectionhead") continue;
                  if ($value === "" || $value === "NULL" && $value === NULL) continue;
                  if ($value <= 0 && ($datatype == "dropdown" || $datatype == "yesno")) continue;

                  if ($fields[$fieldname]['data_type'] === "dropdown" && !empty($value)) {
                     if (!isset($dropdowns_ids[$fieldname][$value])) continue;
                     //find the new the new id of dropdowns
                     $value = $dropdowns_ids[$fieldname][$value];
                  }   
                  
                  //get correct key for storing value
                  $value_type = $this->migrateCustomfieldValue($datatype);

                  //correct insertion for strict mysql mode
                  if ($datatype == "dropdown" || $datatype == "yesno") {
                     $value_insert = $value;
                  } else {
                     //test if empty value
                     if ($value == "") continue;

                     $value_insert = "'".mysql_real_escape_string($value)."'";
                  }

                  //prepare insertion of value
                  if ($value_type == "value_varchar") {
                     $values_insert = "0,$value_insert,NULL";
                  } elseif ($value_type == "value_text") { 
                     $values_insert = "0,NULL,$value_insert";
                  } else {
                     $values_insert = "$value_insert,NULL,NULL";
                  }
             
                  //prepare values insertion sql.
                  //(too long to be executed in this script)
                  $query.= "(NULL, 
                     $values_insert,$old_id,$containers_id,".$fields[$fieldname]['newId']."),";
               }
               
            }
            
         }
      }

      //dump prepared queries into a file
      $query = "INSERT INTO glpi_plugin_fields_values VALUES ".substr($query, 0, -1);
      file_put_contents(GLPI_DUMP_DIR."/customfields_import_values.$rand.sql", $query);
      
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

   function migrateNewSchema() {
      global $DB;
      $rand = mt_rand();
      $values_to_insert = array();

      //create new table for each container
      $container_obj = new PluginFieldsContainer;
      $containers = $container_obj->find();
      $field_obj = new PluginFieldsField;
      $value_obj = new PluginFieldsValue;
      foreach ($containers as $containers_id  => $container) {
         //find fields associated with this container for create new table
         $fields = $field_obj->find("plugin_fields_containers_id = $containers_id 
                                     AND type != 'header'");

         //prepare base table declaration
         $new_table_name = "glpi_plugin_fields_".strtolower($container['itemtype'].
                                                            $container['name']);
         $new_table_sql = "CREATE TABLE IF NOT EXISTS `$new_table_name` (
            `id`                               INT(11) NOT NULL auto_increment,
            `items_id`                         INT(11) NOT NULL,
            `itemtype`                         VARCHAR(255) DEFAULT '".$container['itemtype']."',
            ";

         //complete table declaration with each fields
         foreach ($fields as $fields_id => $field) {
            if ($field['type'] === "dropdown") {
               $field['name'] = getForeignKeyFieldForItemType(
                  PluginFieldsDropdown::getClassname($field['name']));
            }

            $new_table_sql.= "`".$field['name']."` ".self::getSqlType($field['type']).", 
            ";
         }

         //finish base table declaration
         $new_table_sql.= "PRIMARY KEY                         (`id`),
            UNIQUE INDEX `itemtype_item`       (`itemtype`, `items_id`)
         ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"; 
         $DB->query($new_table_sql) or die ($DB->error());

         //create class file for this new table
         $classname = "PluginFields".ucfirst($container['itemtype'].
                                             preg_replace('/s$/', '', $container['name']));
         $template_class = file_get_contents(GLPI_ROOT.
                                             "/plugins/fields/templates/container.class.tpl");
         $template_class = str_replace("%%CLASSNAME%%", $classname, $template_class);
         $template_class = str_replace("%%ITEMTYPE%%", $container['itemtype'], $template_class);
         $class_filename = strtolower($container['itemtype'].
                                      preg_replace('/s$/', '', $container['name']).".class.php");
         if (file_put_contents(GLPI_ROOT."/plugins/fields/inc/$class_filename", 
                               $template_class) === false) return false;

         //retrieve values for this containers
         $values = $value_obj->find("plugin_fields_containers_id = $containers_id");
         foreach ($values as $value) {
            $field_name = $fields[$value['plugin_fields_fields_id']]['name'];

            if ($fields[$value['plugin_fields_fields_id']]['type'] === "dropdown") {
               $field_name = getForeignKeyFieldForItemType(
                  PluginFieldsDropdown::getClassname($field_name));
            }

            //retieve correct value field
            $value_to_insert = $value[
               self::getValueSQLField($fields[$value['plugin_fields_fields_id']]['type'])
            ];

            //compute an array with value to easier insertion in mysql 
            //(key => table, items_id, fieldname)
            $values_to_insert[$new_table_name][$value['items_id']][$field_name] = $value_to_insert;
         }
         
      }

      //insert all values in new tables
      foreach ($values_to_insert as $tablename => $table_content) {
         foreach ($table_content as $items_id => $fields) {
            $columns = implode("`, `",array_keys($fields));
            $escaped_values = array_map('mysql_real_escape_string', array_values($fields));
            $values  = implode("', '", $escaped_values);
            $query_value = "INSERT INTO `$tablename` (`items_id`, `$columns`) 
                                              VALUES ('$items_id', '$values')";
            $DB->query($query_value) or die ($DB->error());
         }
      }

      Toolbox::logDebug(date("r"));

      return true;
   }

   static function getSQLType($field_type) {
      $types = array(
         'text'     => 'VARCHAR(255) DEFAULT NULL',
         'textarea' => 'TEXT         DEFAULT NULL',
         'number'   => 'VARCHAR(255) DEFAULT NULL',
         'dropdown' => 'INT(11)      NOT NULL DEFAULT 0',
         'yesno'    => 'INT(11)      NOT NULL DEFAULT 0',
         'date'     => 'VARCHAR(255) DEFAULT NULL',
         'datetime' => 'VARCHAR(255) DEFAULT NULL'
      );

      return $types[$field_type];
   }

   static function getValueSQLField($field_type) {
      $value_field = array(
         'text'     => 'value_varchar',
         'textarea' => 'value_text',
         'number'   => 'value_varchar',
         'dropdown' => 'value_int',
         'yesno'    => 'value_int',
         'date'     => 'value_varchar',
         'datetime' => 'value_varchar'
      );

      return $value_field[$field_type];
   }
}