<?php

class PluginFieldsMigration {
   
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
         $itemtype = $data_itemtypes['itemtype'];
         $itemtype_table = "glpi_plugin_customfields_".strtolower($itemtype);

         //get all fields description for current type (systemname, label, type, etc)
         $query_desc_itemtype = "SELECT 
               ic.COLUMN_NAME, ic.COLUMN_TYPE, 
               fi.label, fi.sort_order, fi.default_value, fi.entities
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
            $fields[$data_fields['COLUMN_NAME']] = $data_fields;
         }

         //get all values for current type
         $query_values_itemtype = "SELECT * FROM $itemtype_table";
         $res_values_itemtype = $DB->query($query_values_itemtype);
         $values = array();
         while ($data_values = $DB->fetch_assoc($res_values_itemtype)) { 
            $values[$data_values['id']] = $data_values;
         }



         //create a container for this type
         

         //insert fields for this type
         

         //insert values for this type
      }
   }
}