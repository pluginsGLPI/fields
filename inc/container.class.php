<?php

class PluginFieldsContainer extends CommonDBTM {
   
   static function install(Migration $migration) {
      global $DB;

      $obj = new self();
      $table = $obj->getTable();

      if (!TableExists($table)) {
         $migration->displayMessage("Installing $table");

         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`           INT(11)        NOT NULL auto_increment,
                  `name`         VARCHAR(255)   DEFAULT NULL,
                  `label`         VARCHAR(255)   DEFAULT NULL,
                  `itemtype`     VARCHAR(255)   DEFAULT NULL,
                  `type`         VARCHAR(255)   DEFAULT NULL,
                  `entities_id`  INT(11)        NOT NULL DEFAULT '0',
                  `is_recursive` TINYINT(1)     NOT NULL DEFAULT '0',
                  PRIMARY KEY    (`id`),
                  KEY            `entities_id`  (`entities_id`)
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

      return $LANG['fields']['type'][1];
   }

   public function canCreate() {
      return true;
   }

   public function canView() {
      return true;
   }

   public function showForm($ID, $options=array()) {
      global $LANG;

      if ($ID > 0) {
         $this->check($ID,'r');
      } else {
         // Create item
         $this->check(-1,'w');
      }

      $this->showFormHeader($options);

      echo "<tr>";
      echo "<td>".$LANG['common'][16]." : </td>";
      echo "<td>";
      Html::autocompletionTextField($this, 'name', array('value' => $this->fields["name"]));
      echo "</td>";
      echo "<td>".$LANG['mailing'][139]." : </td>";
      echo "<td>";
      Html::autocompletionTextField($this, 'label', array('value' => $this->fields["label"]));
      echo "</td>";
      echo "</tr>";

      echo "<tr>";
      echo "<td>".$LANG['common'][17]." : </td>";
      echo "<td>";
      Dropdown::showFromArray('type', array(
            'tab' => $LANG['fields']['container']['type']['tab'],
            'dom' => $LANG['fields']['container']['type']['dom']
         ), 
         array('value' => $this->fields["type"]));
      echo "</td>";
      echo "<td>".$LANG['common'][90]." : </td>";
      echo "<td>";
      Dropdown::showFromArray('itemtype', PluginFieldsConfig::getItemtypes(), 
         array('value' => $this->fields["itemtype"]));
      echo "</td>";
      echo "</tr>";

      $this->showFormButtons($options);

      return true;
   }

}