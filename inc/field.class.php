<?php

class PluginFieldsField extends CommonDBTM {

   static function install(Migration $migration) {
      global $DB;

      $obj = new self();
      $table = $obj->getTable();

      if (!TableExists($table)) {
         $migration->displayMessage("Installing $table");

         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`                                INT(11)        NOT NULL auto_increment,
                  `name`                              VARCHAR(255)   DEFAULT NULL,
                  `label`                             VARCHAR(255)   DEFAULT NULL,
                  `type`                              VARCHAR(25)    DEFAULT NULL,
                  `plugin_fields_containers_id`       INT(11)        NOT NULL DEFAULT '0',
                  `ranking`                           INT(11)        NOT NULL DEFAULT '0',
                  `default_value`                     VARCHAR(255)   DEFAULT NULL,
                  PRIMARY KEY                         (`id`),
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

   static function getTypeName() {
      global $LANG;

      return $LANG['fields']['type'][0];
   }

   public function canCreate() {
      return true;
   }

   public function canView() {
      return true;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;

      return self::createTabEntry($LANG['fields']['types'][0],
                   countElementsInTable($this->getTable(),
                                        "`plugin_fields_containers_id` = '".$item->getID()."'"));

   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      $fup = new self();
      $fup->showSummary($item);
      return true;
   }

   function showSummary($container) {
      echo "test";
   }


   static function getTypes() {
      global $LANG;
      
      return array(
         'header'   => $LANG['fields']['field']['type']['header'],
         'text'     => $LANG['fields']['field']['type']['text'],
         'textarea' => $LANG['fields']['field']['type']['textarea'],
         'number'   => $LANG['fields']['field']['type']['number'],
         'dropdown' => $LANG['fields']['field']['type']['dropdown'],
         'yesno'    => $LANG['fields']['field']['type']['yesno'],
         'date'     => $LANG['fields']['field']['type']['date']
      );
   }

}