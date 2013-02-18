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

   function prepareInputForAdd($input) {

      // Before adding, add the ranking of the new field
      $input["ranking"] = $this->getNextRanking();
      return $input;
   }

   /**
    * Get the next ranking for a specified field
   **/
   function getNextRanking() {
      global $DB;

      $sql = "SELECT max(`ranking`) AS rank
              FROM `".$this->getTable()."`
              WHERE `plugin_fields_containers_id` = '".
                  $this->fields['plugin_fields_containers_id']."'";
      $result = $DB->query($sql);

      if ($DB->numrows($result) > 0) {
         $datas = $DB->fetch_assoc($result);
         return $datas["rank"] + 1;
      }
      return 0;
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
      global $DB, $LANG, $CFG_GLPI;


      $cID = $container->fields['id'];

      // Display existing Fields
      $tmp           = array('plugin_fields_containers_id' => $cID);
      $canadd        = $this->can(-1, 'w', $tmp);

      $query = "SELECT `id`, `label`
                FROM `".$this->getTable()."`
                WHERE `plugin_fields_containers_id` = '$cID'
                ORDER BY `ranking` ASC";
      $result = $DB->query($query);

      $rand = mt_rand();

      echo "<div id='viewField" . $cID . "$rand'></div>\n";
         echo "<script type='text/javascript' >\n";
         echo "function viewAddField" . $cID . "$rand() {\n";
         $params = array('type'                        => __CLASS__,
                         'parenttype'                  => 'PluginFieldsContainer',
                         'plugin_fields_containers_id' => $cID,
                         'id'                          => -1);
         Ajax::updateItemJsCode("viewField" . $cID . "$rand",
                                $CFG_GLPI["root_doc"]."/ajax/viewsubitem.php", $params);
         echo "};";
         echo "</script>\n";
         echo "<div class='center'>".
              "<a href='javascript:viewAddField".$container->fields['id']."$rand();'>";
         echo $LANG['fields']['field']['label']['add']."</a></div><br>\n";
      

      if ($DB->numrows($result) == 0) {
         echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
         echo "<th class='b'>".$LANG['fields']['field']['label']['no_fields']."</th></tr></table>";
      } else {
         echo "<table class='tab_cadre_fixehov'>";
         echo "<tr>";
         echo "<th>" . $LANG['common'][16] . "</th>";
         echo "<th>" . $LANG['mailing'][139] . "</th>";
         echo "<th>" . $LANG['common'][17] . "</th>";
         echo "<th>" . $LANG['common'][44] . "</th>";
         echo "</tr>\n";

         $fields_type = self::getTypes();

         while ($data = $DB->fetch_array($result)) {
            if ($this->getFromDB($data['id'])) {
               echo "<tr class='tab_bg_2' style='cursor:pointer' onClick=\"viewEditField$cID". 
                  $this->fields['id']."$rand();\">";

               echo "<td>";
               echo "\n<script type='text/javascript' >\n";
               echo "function viewEditField" . $cID . $this->fields["id"] . "$rand() {\n";
               $params = array('type'                        => __CLASS__,
                               'parenttype'                  => 'PluginFieldsContainer',
                               'plugin_fields_containers_id' => $cID,
                               'id'                          => $this->fields["id"]);
               Ajax::updateItemJsCode("viewField" . $cID . "$rand",
                                      $CFG_GLPI["root_doc"]."/ajax/viewsubitem.php", $params);
               echo "};";
               echo "</script>\n";
               echo $this->fields['name']."</td>";
               echo "<td>".$this->fields['label']."</td>";
               echo "<td>".$fields_type[$this->fields['type']]."</td>";
               echo "<td>".$this->fields['default_value']."</td>";
               echo "</tr>\n";
            }
         }
      }
   }


   function showForm($ID, $options=array()) {
      global $LANG;

      if (isset($options['parent']) && !empty($options['parent'])) {
         $container = $options['parent'];
      }

      if ($ID > 0) {
         $this->check($ID,'r');
      } else {
         // Create item
         $input = array('plugin_fields_containers_id' => $container->getField('id'));
         $this->check(-1,'w',$input);
      }

      $this->showFormHeader($options);

      echo "<tr>";
      echo "<td>".$LANG['common'][16]." : </td>";
      echo "<td>";
      echo "<input type='hidden' name='plugin_fields_containers_id' value='".
         $container->getField('id')."'>";
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
      echo "<td>".$LANG['common'][44]." : </td>";
      echo "<td>";
      Html::autocompletionTextField($this, 'default_value', 
                                    array('value' => $this->fields["default_value"]));
      echo "</td>";
      echo "</tr>";

      $this->showFormButtons($options);

   }

   static function showForTabContainer($c_id, $items_id) {
      global $CFG_GLPI, $LANG;

      $field_obj = new PluginFieldsField;
      $field_value_obj = new PluginFieldsValue;

      //get fields for this container
      $fields = $field_obj->find("plugin_fields_containers_id = $c_id", "ranking");
      $odd = 0;
      echo "<form method='POST' action='".$CFG_GLPI["root_doc"].
         "/plugins/fields/front/container.form.php'>";
      echo "<input type='hidden' name='plugin_fields_containers_id' value='$c_id'>";
      echo "<input type='hidden' name='items_id' value='$items_id'>";
      echo "<table class='tab_cadre_fixe'>";
      foreach($fields as $field) {
         if ($field['type'] === 'header') {
            echo "<tr class='tab_bg_2'>";
            echo "<th colspan='4'>".$field['label']."</td>";
            echo "</tr>";
            $odd = 0;
         } else {
            //get value
            $value = "";
            $found_v = $field_value_obj->find(
            "`plugin_fields_fields_id` = ".$field['id']." AND `items_id` = '".$items_id."'");
            if (count($found_v) > 0) {
               $tmp_v = array_shift($found_v);
               $value = $tmp_v['value'];
            }

            //show field
            if ($odd%2 == 0)  echo "<tr class='tab_bg_2'>";
            echo "<td>".$field['label']." : </td>";
            echo "<td>";
            switch ($field['type']) {
               case 'number':
               case 'text':
                  $value = Html::cleanInputText($value);
                  echo "<input type='text' name='".$field['name']."' value=\"$value\" />";
                  break;
               case 'textarea':
                  echo "<textarea name='".$field['name']."'>$value</textarea>";
                  break;
               case 'dropdown':

                  break;
               case 'yesno':
                  Dropdown::showYesNo($field['name'], $value);
                  break;
               case 'date':
                  Html::showDateTimeFormItem($field['name'], $value);
                  break;

            }
            echo "</td>";
            if ($odd%2 == 1)  echo "</tr>";
            $odd++;
         }         
      }
      if ($odd%2 == 0)  echo "</tr>";
      echo "<tr><td class='tab_bg_2 center' colspan='4'>";
      echo "<input type='submit' name='update_fields_values' value=\"".
         $LANG['buttons'][7]."\" class='submit'>";
      echo "</td></tr>";
      echo "</table></form>";

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