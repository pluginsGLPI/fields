<?php

class PluginFieldsField extends CommonDBTM {

   static function install(Migration $migration) {
      global $DB;

      $obj = new self();
      $table = $obj->getTable();

      if (!TableExists($table)) {
         $migration->displayMessage("Installing $table");

         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`        INT(11)        NOT NULL auto_increment,
                  `name`      VARCHAR(255)   DEFAULT NULL,
                  `label`     VARCHAR(255)   DEFAULT NULL,
                  `type`      VARCHAR(25)    DEFAULT NULL,
                  PRIMARY KEY  (`id`)
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
      Dropdown::showFromArray('type', self::getTypes(), 
         array('value' => $this->fields["type"]));
      echo "</td>";
      echo "</tr>";

      $this->showFormButtons($options);

      return true;
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