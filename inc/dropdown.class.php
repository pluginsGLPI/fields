<?php

class PluginFieldsDropdown {
   static $rightname = 'dropdown';
   public $can_be_translated = true;

   /**
    * Install or update dropdowns
    *
    * @param Migration $migration Migration instance
    * @param string    $version   Plugin current version
    *
    * @return void
    */
   static function install(Migration $migration, $version) {
      $toolbox = new PluginFieldsToolbox();
      $toolbox->fixFieldsNames($migration, ['type' => 'dropdown']);

      $migration->displayMessage(__("Updating generated dropdown files", "fields"));
      // -> 0.90-1.3: generated class moved
      // OLD path: GLPI_ROOT."/plugins/fields/inc/$class_filename"
      // NEW path: PLUGINFIELDS_CLASS_PATH . "/$class_filename"
      // OLD path: GLPI_ROOT."/plugins/fields/front/$class_filename"
      // NEW path: PLUGINFIELDS_FRONT_PATH . "/$class_filename"
      $obj = new PluginFieldsField;
      $fields = $obj->find(['type' => 'dropdown']);
      foreach ($fields as $field) {
         //First, drop old fields from plugin directories
         $class_filename = $field['name']."dropdown.class.php";
         if (file_exists(PLUGINFIELDS_DIR."/inc/$class_filename")) {
            unlink(PLUGINFIELDS_DIR."/inc/$class_filename");
         }

         $front_filename = $field['name']."dropdown.php";
         if (file_exists(PLUGINFIELDS_DIR."/front/$front_filename")) {
            unlink(PLUGINFIELDS_DIR."/front/$front_filename");
         }

         $form_filename = $field['name']."dropdown.form.php";
         if (file_exists(PLUGINFIELDS_DIR."/front/$form_filename")) {
            unlink(PLUGINFIELDS_DIR."/front/$form_filename");
         }

         //Second, create new files
         self::create($field);
      }

      return true;
   }

   static function uninstall() {
      global $DB;

      //remove dropdown tables and files
      if ($DB->tableExists("glpi_plugin_fields_fields")) {
         require_once "field.class.php";
         $field = new PluginFieldsField;
         $dropdowns = $field->find(['type' => 'dropdown']);
         foreach ($dropdowns as $dropdown) {
            self::destroy($dropdown['name']);
         }
      }
      return true;
   }

   static function create($input) {
      //get class template
      $template_class = file_get_contents(PLUGINFIELDS_DIR."/templates/dropdown.class.tpl");
      if ($template_class === false) {
         return false;
      }

      $classname = self::getClassname($input['name']);

      //create dropdown class file
      $template_class = str_replace(
         "%%CLASSNAME%%",
         $classname,
         $template_class
      );
      $template_class = str_replace(
         "%%FIELDNAME%%",
         $input['name'],
         $template_class
      );
      $template_class = str_replace(
         "%%FIELDID%%",
         $input['id'],
         $template_class
      );
      $template_class = str_replace(
         "%%LABEL%%",
         $input['label'],
         $template_class
      );
      $class_filename = $input['name']."dropdown.class.php";
      if (file_put_contents(PLUGINFIELDS_CLASS_PATH . "/$class_filename",
                            $template_class) === false) {
         Toolbox::logDebug("Error : dropdown class file creation - $class_filename");
         return false;
      }

      //get front template
      $template_front = file_get_contents(PLUGINFIELDS_DIR."/templates/dropdown.tpl");
      if ($template_front === false) {
         Toolbox::logDebug("Error : get dropdown front template error");
         return false;
      }

      //create dropdown front file
      $template_front = str_replace("%%CLASSNAME%%", $classname, $template_front);
      $front_filename = $input['name']."dropdown.php";
      if (file_put_contents(PLUGINFIELDS_FRONT_PATH . "/$front_filename",
                            $template_front) === false) {
         Toolbox::logDebug("Error : dropdown front file creation - $class_filename");
         return false;
      }

      //get form template
      $template_form = file_get_contents(PLUGINFIELDS_DIR."/templates/dropdown.form.tpl");
      if ($template_form === false) {
         return false;
      }

      //create dropdown form file
      $template_form = str_replace("%%CLASSNAME%%", $classname, $template_form);
      $form_filename = $input['name']."dropdown.form.php";
      if (file_put_contents(PLUGINFIELDS_FRONT_PATH . "/$form_filename",
                            $template_form) === false) {
         Toolbox::logDebug("Error : get dropdown form template error");
         return false;
      }

      //load class manually on plugin installation
      if (!class_exists($classname)) {
         require_once $class_filename;
      }

      //call install method (create table)
      if ($classname::install() === false) {
         Toolbox::logDebug("Error : calling dropdown $classname installation");
         return false;
      }

      // Destroy menu in session for force to regenerate it
      unset($_SESSION['glpimenu']);

      return true;
   }

   static function destroy($dropdown_name) {
      $classname = self::getClassname($dropdown_name);
      $class_filename = PLUGINFIELDS_CLASS_PATH . "/".$dropdown_name."dropdown.class.php";

      //call uninstall method in dropdown class
      if ($classname::uninstall() === false) {
         Toolbox::logDebug("Error : calling dropdown $classname uninstallation");
         return false;
      }

      //remove class file for this dropdown
      if (file_exists($class_filename)) {
         if (unlink($class_filename) === false) {
            Toolbox::logDebug("Error : dropdown class file creation - ".$dropdown_name."dropdown.class.php");
            return false;
         }
      }

      //remove front file for this dropdown
      $front_filename = PLUGINFIELDS_FRONT_PATH . "/".$dropdown_name."dropdown.php";
      if (file_exists($front_filename)) {
         if (unlink($front_filename) === false) {
            Toolbox::logDebug("Error : dropdown front file removing - ".$dropdown_name."dropdown.php");
            return false;
         }
      }

      //remove front.form file for this dropdown
      $form_filename = PLUGINFIELDS_FRONT_PATH . "/".$dropdown_name."dropdown.form.php";
      if (file_exists($form_filename)) {
         if (unlink($form_filename) === false) {
            Toolbox::logDebug("Error : dropdown form file removing - ".$dropdown_name."dropdown.form.php");
            return false;
         }
      }

      return true;
   }

   static function getClassname($system_name) {
      return "PluginFields".ucfirst($system_name)."Dropdown";
   }
}
