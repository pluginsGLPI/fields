<?php

class PluginFieldsDropdown {

   static function install(Migration $migration) {
      global $LANG;

      //check if "inc", "front" and "ajax" directories are writeable for httpd
      $directories = array(
         "../plugins/fields/inc",
         "../plugins/fields/front",
         "../plugins/fields/ajax",
      );
      foreach ($directories as $directory) {
         if(!is_writable($directory)) {
            Session::addMessageAfterRedirect($LANG['fields']['error']['dir_write'], false, ERROR);
            return false;
         }
      }

      return true;
   }

   static function uninstall() {
      global $DB;

      //remove dropdown tables and files
      if (TableExists("glpi_plugin_fields_fields")) {
         require_once "field.class.php";
         $field = new PluginFieldsField;
         $dropdowns = $field->find("`type` = 'dropdown'");
         foreach ($dropdowns as $dropdown) {
            self::destroy($dropdown['name']);
         }
      }
      return true;
   }
   
   static function create($input) {
      //get class template
      $template_class = file_get_contents(GLPI_ROOT."/plugins/fields/templates/dropdown.class.tpl");
      if ($template_class === false) return false;

      $classname = self::getClassname($input['name']);
      
      //create dropdown class file
      $template_class = str_replace("%%CLASSNAME%%", $classname, $template_class);
      $template_class = str_replace("%%FIELDNAME%%", $input['name'], $template_class);
      $class_filename = $input['name']."dropdown.class.php";
      if (file_put_contents(GLPI_ROOT."/plugins/fields/inc/$class_filename", 
                            $template_class) === false) return false;

      //get front template
      $template_front = file_get_contents(GLPI_ROOT."/plugins/fields/templates/dropdown.tpl");
      if ($template_front === false) return false;

      //create dropdown front file
      $template_front = str_replace("%%CLASSNAME%%", $classname, $template_front);
      $front_filename = $input['name']."dropdown.php";
      if (file_put_contents(GLPI_ROOT."/plugins/fields/front/$front_filename", 
                            $template_front) === false) return false;

      //get form template
      $template_form = file_get_contents(GLPI_ROOT."/plugins/fields/templates/dropdown.form.tpl");
      if ($template_form === false) return false;

      //create dropdown form file
      $template_form = str_replace("%%CLASSNAME%%", $classname, $template_form);
      $form_filename = $input['name']."dropdown.form.php";
      if (file_put_contents(GLPI_ROOT."/plugins/fields/front/$form_filename", 
                            $template_form) === false) return false;

      //load class manually on plugin installation
      if (!class_exists($classname)) require_once $class_filename;      

      //call install method (create table)
      $classname::install(); 

      return true;
   }

   static function destroy($dropdown_name) {

      $classname = self::getClassname($dropdown_name);
      
      //load class manually on plugin uninstallation
      if (!class_exists($classname)) require_once $dropdown_name."dropdown.class.php";      

      //call uninstall method in dropdown class
      if ($classname::uninstall() === false) return false;

      //remove class file for this dropdown
      $class_filename = GLPI_ROOT."/plugins/fields/inc/".$dropdown_name."dropdown.class.php";
      if (unlink($class_filename) === false) return false;

      //remove front file for this dropdown
      $front_filename = GLPI_ROOT."/plugins/fields/front/".$dropdown_name."dropdown.php";
      if (unlink($front_filename) === false) return false;

      //remove front.form file for this dropdown
      $form_filename = GLPI_ROOT."/plugins/fields/front/".$dropdown_name."dropdown.form.php";
      return unlink($form_filename);
   }

   static function getClassname($system_name) {
      return "PluginFields".ucfirst($system_name)."Dropdown";
   }
}