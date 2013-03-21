<?php

class PluginFieldsProfile extends CommonDBTM {
   
   static function install(Migration $migration) {
      global $DB;

      $obj = new self();
      $table = $obj->getTable();

      if (!TableExists($table)) {
         $migration->displayMessage("Installing $table");

         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`                                INT(11)  NOT NULL auto_increment,
                  `profiles_id`                       INT(11)  NOT NULL DEFAULT '0',
                  `plugin_fields_containers_id`       INT(11)  NOT NULL DEFAULT '0',
                  `right`                             CHAR(1)  DEFAULT NULL,
                  PRIMARY KEY                         (`id`),
                  KEY `profiles_id`                   (`profiles_id`),
                  KEY `plugin_fields_containers_id`   (`plugin_fields_containers_id`)
               ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"; 
            $DB->query($query) or die ($DB->error());
      }

      return true;
   }

   
   static function uninstall() {
      global $DB;

      $obj = new self();
      $DB->query("DROP TABLE IF EXISTS `".$obj->getTable()."`");

      return true;
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;

      return self::createTabEntry($LANG['Menu'][35]);

   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      global $LANG;

      $profile = new Profile;
      $found_profiles = $profile->find();

      $fields_profile = new self;
      echo "<form name='form' method='post' action='".$fields_profile->getFormURL()."'>";
      echo "<div class='spaced' id='tabsbody'>";
      echo "<table class='tab_cadre_fixe'>";

      echo "<tr><th colspan='2'>".$LANG['Menu'][35]."</th></tr>";
      foreach ($found_profiles as $profile_item) {
         echo "<tr>";
         echo "<td>".$profile_item['name']."</td>";
         echo "<td>";
         Profile::dropdownNoneReadWrite("rights[".$profile_item['name']."]", 0);
         echo "</td>";
         echo "<tr>";
      }
      echo "<ul>";
      echo "<tr><td class='tab_bg_2 center' colspan='2'>";
      echo "<input type='submit' name='update' value=\"".$LANG['buttons'][7]."\" class='submit'>";
      echo "</td>";
      echo "</tr>";
      echo "</table></div>";
      Html::closeForm();
   }

}