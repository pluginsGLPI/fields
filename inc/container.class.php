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
                  `label`        VARCHAR(255)   DEFAULT NULL,
                  `itemtype`     VARCHAR(255)   DEFAULT NULL,
                  `type`         VARCHAR(255)   DEFAULT NULL,
                  `entities_id`  INT(11)        NOT NULL DEFAULT '0',
                  `is_recursive` TINYINT(1)     NOT NULL DEFAULT '0',
                  `is_active`    TINYINT(1)     NOT NULL DEFAULT '0',
                  PRIMARY KEY    (`id`),
                  KEY            `entities_id`  (`entities_id`)
               ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;"; 
         $DB->query($query) or die ($DB->error());
      }

      //add display preferences for this class
      $d_pref = new DisplayPreference;
      $found = $d_pref->find("itemtype = '".__CLASS__."'");
      if (count($found) == 0) {
         for ($i = 2; $i <= 5; $i++) {
            $DB->query("INSERT INTO glpi_displaypreferences VALUES 
               ('', '".__CLASS__."', $i, ".($i-1).", 0)");
         }
      }

      return true;
   }

   
   static function uninstall() {
      global $DB;

      $obj = new self();
      $DB->query("DROP TABLE IF EXISTS `".$obj->getTable()."`");

      //delete display preferences for this item
      $DB->query("DELETE FROM glpi_displaypreferences WHERE `itemtype` = '".__CLASS__."'");

      return true;
   }

   function getSearchOptions() {
      global $LANG;

      $tab = array();

      $tab[1]['table']         = $this->getTable();
      $tab[1]['field']         = 'name';
      $tab[1]['name']          = $LANG['common'][16];
      $tab[1]['datatype']      = 'itemlink';
      $tab[1]['itemlink_type'] = $this->getType();
      $tab[1]['massiveaction'] = false;

      $tab[2]['table']         = $this->getTable();
      $tab[2]['field']         = 'label';
      $tab[2]['name']          = $LANG['mailing'][139];
      $tab[2]['massiveaction'] = false;

      $tab[3]['table']         = $this->getTable();
      $tab[3]['field']         = 'itemtype';
      $tab[3]['name']          = $LANG['common'][90];
      $tab[3]['datatype']       = 'itemtypename';

      $tab[4]['table']         = $this->getTable();
      $tab[4]['field']         = 'type';
      $tab[4]['name']          = $LANG['common'][17];
      $tab[4]['searchtype']    = 'equals';

      $tab[5]['table']         = $this->getTable();
      $tab[5]['field']         = 'is_active';
      $tab[5]['name']          = $LANG['common'][60];
      $tab[5]['datatype']      = 'bool';

      $tab[6]['table']         = 'glpi_entities';
      $tab[6]['field']         = 'completename';
      $tab[6]['name']          = $LANG['entity'][0];
      $tab[6]['massiveaction'] = false;
      $tab[6]['datatype']      = 'dropdown';

      $tab[7]['table']         = $this->getTable();
      $tab[7]['field']         = 'is_recursive';
      $tab[7]['name']          = $LANG['entity'][9];
      $tab[6]['massiveaction'] = false;
      $tab[7]['datatype']      = 'bool';

      return $tab;
   }

   static function getSpecificValueToDisplay($field, $values, $options=array()) {
      if (!is_array($values)) {
         $values = array($field => $values);
      }
      switch ($field) {
         case 'type':
            $types = self::getTypes();
            return $types[$values[$field]];
            break;
      }
   }

   function defineTabs($options=array()) {
      global $LANG, $CFG_GLPI;

      $ong = array();
      $this->addStandardTab('PluginFieldsField', $ong, $options);
      $this->addStandardTab('PluginFieldsProfile', $ong, $options);

      return $ong;
   }

   function prepareInputForAdd($input) {
      global $LANG;
      
      if ($input['type'] === "dom") {
         //check for already exist dom container with this itemtype
         $found = $this->find("`type`='dom' AND `itemtype` = '".$input['itemtype']."'");
         if (!empty($found)) {
            Session::AddMessageAfterRedirect($LANG['fields']['error']['dom_not_unique']);
            return false;
         }
      }

      //contruct field name by processing label (remove non alphanumeric char)
      $input['name'] = strtolower(preg_replace("/[^\da-z]/i", "", $input['label']));

      return $input;
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

      $this->showTabs($options);
      $this->showFormHeader($options);

      echo "<tr>";
      /*echo "<td>".$LANG['common'][16]." : </td>";
      echo "<td>";
      Html::autocompletionTextField($this, 'name', array('value' => $this->fields["name"]));
      echo "</td>";*/
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
      echo "<td>".$LANG['common'][90]." : </td>";
      echo "<td>";
      Dropdown::showFromArray('itemtype', self::getItemtypes(), 
         array('value' => $this->fields["itemtype"]));
      echo "</td>";
      echo "</tr>";

      echo "<tr>";
      echo "<td>".$LANG['common'][60]." : </td>";
      echo "<td>";
      Dropdown::showYesNo("is_active", $this->fields["is_active"]);
      echo "</td>";
      echo "</tr>";

      $this->showFormButtons($options);
      $this->addDivForTabs();

      return true;
   }


   static function getItemtypes() {
      global $LANG;

      return array(
         'Computer'           => $LANG['Menu'][0],
         'Networkequipment'   => $LANG['Menu'][1],
         'Printer'            => $LANG['Menu'][2],
         'Monitor'            => $LANG['Menu'][3],
         'Software'           => $LANG['Menu'][4],
         'Ticket'             => $LANG['Menu'][5],
         'User'               => $LANG['Menu'][14],
         'Cartridgeitem'      => $LANG['Menu'][21],
         'Contact'            => $LANG['Menu'][22],
         'Supplier'           => $LANG['Menu'][23],
         'Contract'           => $LANG['Menu'][25],
         'Document'           => $LANG['Menu'][27],
         'State'              => $LANG['Menu'][28],
         'Consumableitem'     => $LANG['Menu'][32],
         'Phone'              => $LANG['Menu'][34],
         'Profile'            => $LANG['Menu'][35],
         'Group'              => $LANG['Menu'][36],
         'Entity'             => $LANG['Menu'][37]
      );
   }

   static function getTypes() {
      global $LANG;

      return array(
         'tab' => $LANG['fields']['container']['type']['tab'],
         'dom' => $LANG['fields']['container']['type']['dom']
      );
   }

   static function getEntries($type = 'tab', $full = false) {
      $sql_type = "1=1";
      if ($type !== "all") {
         $sql_type = "`type` = '$type'";
      }

      $itemtypes = array();
      $container = new self;
      $found = $container->find("$sql_type AND is_active = 1", "`label`");
      foreach($found as $item) {
         //entities restriction
         if (!in_array($item['entities_id'], $_SESSION['glpiactiveentities'])) {
            if ($item['is_recursive'] == 1) {
               $entities = getSonsOf("glpi_entities", $item['entities_id']);
               if (count(array_intersect($entities, $_SESSION['glpiactiveentities'])) == 0) {
                  continue;
               }
            } else {
               continue;
            }
         }

         if ($full) {
            $itemtypes[$item['itemtype']][$item['name']] = $item['label'];
         } else {
            $itemtypes[] = $item['itemtype'];
         }
      }
      return $itemtypes;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;
      
      $itemtypes = self::getEntries('tab', true);
      if (isset($itemtypes[$item->getType()])) {
         $tabs_entries = array();
         foreach($itemtypes[$item->getType()] as $tab_name => $tab_label) { 
            $tabs_entries[$tab_name] = $tab_label;
         }
         return $tabs_entries;
      }
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      //retrieve container for current tab
      $container = new self;
      $found_c = $container->find("`itemtype` = '".get_class($item)."' 
                                  AND `type` = 'tab' AND `name` = '$tabnum'");
      $tmp = array_shift($found_c);
      $c_id = $tmp['id'];

      return PluginFieldsField::showForTabContainer($c_id, $item->fields['id']);
   }

   function updateFieldsValues($datas) {
      global $DB;

      $c_id     = $datas['plugin_fields_containers_id'];
      $items_id = $datas['items_id'];

      //get itemtype
      $container = new self;
      $container->getFromDB($c_id);
      $itemtype = $container->fields['itemtype'];

      //unset unused datas
      unset(
         $datas['plugin_fields_containers_id'], 
         $datas['items_id'], 
         $datas['update_fields_values']
      );

      $field_obj = new PluginFieldsField;
      $field_value_obj = new PluginFieldsValue;
      foreach($datas as $field => $value) {
         //parse name for dropdown
         if (strpos($field, "dropdown") !== false) {
            $field = str_replace("plugin_fields_", "", $field);
            $field = str_replace("dropdowns_id", "", $field);
         }

         //find field
         $found_f = $field_obj->find(
            "`plugin_fields_containers_id` = $c_id AND `name` = '".$field."'");
         $tmp_f = array_shift($found_f);
         $fields_id = $tmp_f['id'];

         //find existing values
         $found_v = $field_value_obj->find(
            "`plugin_fields_fields_id` = $fields_id AND `items_id` = '".$items_id."'");

         $value_field = 'value_varchar';
         switch ($tmp_f['type']) {
            case 'dropdown':
               $value_field = 'value_int';
               break;
            case 'yesno':
               $value_field = 'value_int';
               break;
            case 'textarea':
               $value_field = 'value_text';
         }

         if (count($found_v) > 0) {
            //update
            $tmp_v = array_shift($found_v);
            $values_id = $tmp_v['id'];
            $field_value_obj->update(array(
               'id'         => $values_id,
               $value_field => $value
            ));
         } else {
            // add
            $field_value_obj->add(array(
               'items_id'                    => $items_id,
               'itemtype'                    => $itemtype,
               $value_field                  => $value,
               'plugin_fields_containers_id' => $c_id,
               'plugin_fields_fields_id'     => $fields_id
            ));
         }
      }

      return true;
   }


   static function findContainer($itemtype, $items_id, $type='tab') {
      $container = new PluginFieldsContainer;
      $found_c = $container->find("`type` = '$type' AND `itemtype` = '$itemtype'");

      if (count($found_c) == 0) return false;
      
      if ($type == "dom") {
         $tmp = array_shift($found_c);
         $id = $tmp['id'];
      } else {
         $id = array_keys($found_c);
      }

      return $id;
   }


   static function preItemUpdate(CommonDBTM $item) {
      //Html::printCleanArray($item);exit;

      //find container (if not exist, do nothing)
      $c_id = self::findContainer(get_Class($item), $item->fields['id'], "dom");
      if ($c_id === false) return false;

      //find fields associated to found container
      $field_obj = new PluginFieldsField;
      $fields = $field_obj->find("plugin_fields_containers_id = $c_id AND type != 'header'", 
                                 "ranking");

      //prepare datas to update
      $datas = array(
         'plugin_fields_containers_id' => $c_id,
         'items_id'                    =>  $item->fields['id']
      );
      foreach($fields as $field) {
         if (isset($item->input[$field['name']])) {
            //standard field
            $input = $field['name'];
         } else {
            //dropdown field
            $input = "plugin_fields_".$field['name']."dropdowns_id";
         }
         $datas[$field['name']] = $item->input[$input];
      }

      //update datas
      $container = new self;
      return $container->updateFieldsValues($datas);
   }
   
   static function preItemPurge(CommonDBTM $item) {
      global $DB;

      $values = new PluginFieldsValue;

      //get all value associated to this item
      $query = "SELECT glpi_plugin_fields_values.id as values_id
      FROM glpi_plugin_fields_containers
      INNER JOIN glpi_plugin_fields_values
         ON glpi_plugin_fields_values.plugin_fields_containers_id = glpi_plugin_fields_containers.id
      WHERE glpi_plugin_fields_containers.itemtype = '".get_Class($item)."'
         AND glpi_plugin_fields_values.items_id = ".$item->fields['id'];
      $res = $DB->query($query);
      while ($data = $DB->fetch_assoc($res)) {
         $values_id = $data['values_id'];

         //remove associated values
         $values->delete(array(
            'id' => $values_id
         ), 1);
      }
   }

   static function getAddSearchOptions($itemtype) {
      global $DB;

      $opt = array();

      $i = 76665;
      $query = "SELECT fields.name, fields.label, fields.type
         FROM glpi_plugin_fields_containers containers
         INNER JOIN glpi_plugin_fields_fields fields
            ON containers.id = fields.plugin_fields_containers_id
         WHERE containers.itemtype = '$itemtype'
            AND fields.type != 'header'
            ORDER BY fields.id ASC";
      $res = $DB->query($query);
      while ($datas = $DB->fetch_assoc($res)) {
         $opt[$i]['table']         = 'glpi_plugin_fields_values';
         $opt[$i]['field']         = 'value_varchar';
         $opt[$i]['name']          = $datas['label'];
         $opt[$i]['condition']     = "glpi_plugin_fields_fields.name = '".$datas['name']."'";
         $opt[$i]['massiveaction'] = false;

         if ($datas['type'] === "dropdown") {
            $opt[$i]['table']      = 'glpi_plugin_fields_'.$datas['name'].'dropdowns';
            $opt[$i]['field']      = 'name';
            $opt[$i]['searchtype'] = 'equals';
            unset($opt[$i]['condition']); 
         }

         switch ($datas['type']) {
            case 'dropdown':
               $opt[$i]['datatype'] = "dropdown";
               break;
            case 'yesno':
               $opt[$i]['datatype'] = "bool";
               $opt[$i]['field']    = 'value_int';
               break;
            case 'textarea':
               $opt[$i]['datatype'] = "text";
               $opt[$i]['field']    = 'value_text';
               break;
            case 'date':
            case 'datetime':
               $opt[$i]['datatype'] = $datas['type'];
               break;
            default:
               $opt[$i]['datatype'] = "string";
          } 

          //massive action searchoption
          $opt[$i+100000]                  = $opt[$i];
          $opt[$i+100000]['linkfield']     = $datas['name'];
          $opt[$i+100000]['massiveaction'] = true;
          $opt[$i+100000]['nosearch'] = true;


         $i++;
      }

      return $opt;
   }

}