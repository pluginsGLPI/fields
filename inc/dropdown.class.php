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
   
   static function createFilesForField($input) {
      return true;
   }
}