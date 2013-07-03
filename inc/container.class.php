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
               (NULL, '".__CLASS__."', $i, ".($i-1).", 0)");
         }
      }

      return true;
   }

   
   static function uninstall() {
      global $DB;

      //uninstall container table and class
      $obj = new self;
      $containers = $obj->find();
      foreach ($containers as $containers_id => $container) {
         $obj->delete(array('id'=>$containers_id));
      }

      //drop global container table
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

   function post_addItem() {
      //create profiles associated to this container
      PluginFieldsProfile::createForContainer($this);

      //create class file
      $classname = "PluginFields".ucfirst($this->fields['itemtype'].
                                          preg_replace('/s$/', '', $this->fields['name']));
      $template_class = file_get_contents(GLPI_ROOT.
                                          "/plugins/fields/templates/container.class.tpl");
      $template_class = str_replace("%%CLASSNAME%%", $classname, $template_class);
      $template_class = str_replace("%%ITEMTYPE%%", $this->fields['itemtype'], $template_class);
      $template_class = str_replace("%%CONTAINER%%", $this->fields['id'], $template_class);
      $class_filename = strtolower($this->fields['itemtype'].
                                   preg_replace('/s$/', '', $this->fields['name']).".class.php");
      if (file_put_contents(GLPI_ROOT."/plugins/fields/inc/$class_filename", 
                            $template_class) === false) return false;

      //install table for receive field
      $classname::install();
   }

   function pre_deleteItem() {
      $_SESSION['delete_container'] = true;
      $classname = "PluginFields".ucfirst(strtolower($this->fields['itemtype'].
                                          preg_replace('/s$/', '', $this->fields['name'])));
      $class_filename = strtolower($this->fields['itemtype'].
                                   preg_replace('/s$/', '', $this->fields['name'])).".class.php";

      //delete fields
      $field_obj = new PluginFieldsField;
      $fields = $field_obj->find("plugin_fields_containers_id = ".$this->fields['id']);
      foreach ($fields as $fields_id => $field) {
         $field_obj->delete(array('id' => $fields_id));
      }

      //delete profiles
      $profile_obj = new PluginFieldsProfile;
      $profiles = $profile_obj->find("plugin_fields_containers_id = ".$this->fields['id']);
      foreach ($profiles as $profiles_id => $profile) {
         $profile_obj->delete(array('id' => $profiles_id));
      }

      //delete table
      $classname::uninstall();

      //remove file
      if (file_exists(GLPI_ROOT."/plugins/fields/inc/$class_filename")) {
         return unlink(GLPI_ROOT."/plugins/fields/inc/$class_filename");
      }

      unset($_SESSION['delete_container']);

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

      if (!TableExists("glpi_plugin_fields_containers")) {
         return false;
      }

      $itemtypes = array();
      $container = new self;
      $profile = new PluginFieldsProfile;
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

         //profiles restriction
         $found = $profile->find("`profiles_id` = '".$_SESSION['glpiactiveprofile']['id']."' 
                                 AND `plugin_fields_containers_id` = '".$item['id']."'");
         $first_found = array_shift($found);
         if ($first_found['right'] == NULL) continue;

         //show more info or not
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
                                  AND `type` = 'tab' AND `name` = '$tabnum' AND is_active = 1");
      $tmp = array_shift($found_c);
      $c_id = $tmp['id'];

      return PluginFieldsField::showForTabContainer($c_id, $item->fields['id']);
   }

   /**
    * Insert values submited by fields container
    * @param  array $datas datas posted 
    * @return boolean
    */
   function updateFieldsValues($datas) {
      global $DB;

      if (self::validateValues($datas) === false) return false;

      //insert datas in new table
      $container_obj = new PluginFieldsContainer;
      $container_obj->getFromDB($datas['plugin_fields_containers_id']);

      $items_id = $datas['items_id'];
      $itemtype = $container_obj->fields['itemtype'];

      $classname = "PluginFields".ucfirst($itemtype.
                                          preg_replace('/s$/', '', $container_obj->fields['name']));
      $obj = new $classname;
      //check if datas already inserted
      $found = $obj->find("items_id = $items_id");
      if (empty($found)) {
         $obj->add($datas);

         //construct history on itemtype object (Historical tab)
         self::constructHistory($datas['plugin_fields_containers_id'], $items_id, 
                            $itemtype, $datas);
      } else {
         $first_found = array_pop($found);
         $datas['id'] = $first_found['id'];
         $obj->update($datas);

         //construct history on itemtype object (Historical tab)
         self::constructHistory($datas['plugin_fields_containers_id'], $items_id, 
                            $itemtype, $datas, $first_found);
      }

      return true;
   }

   /**
    * Add log in "itemtype" object on fields values update
    * @param  int    $containers_id : 
    * @param  int    $items_id      : 
    * @param  string $itemtype      : 
    * @param  array  $datas         : values send by update form
    * @param  array  $old_values    : old values, if empty -> values add
    * @return nothing
    */
   static function constructHistory($containers_id, $items_id, $itemtype, $datas, 
                                $old_values = array()) {
      //get searchoptions
      $searchoptions = self::getAddSearchOptions($itemtype, $containers_id);

      //add/update values condition
      if (empty($old_values)) {
         // -- add new item --

         //remove non-datas keys
         $blacklist_k = array('plugin_fields_containers_id' => 0, 'items_id' => 0, 
                              'update_fields_values' => 0);
         $datas = array_diff_key($datas, $blacklist_k);
         
         foreach ($datas as $key => $value) {
            //log only not empty values
            if (!empty($value)) {
               //prepare log
               $changes = array(0, "", $value);

               //find searchoption
               foreach ($searchoptions as $id_search_option => $searchoption) {
                  if ($searchoption['field'] == $key) {
                     $changes[0] = $id_search_option;
                     break;
                  }
               }

               //add log
               Log::history($items_id, $itemtype, $changes);
            }
         }
      } else {
         // -- update existing item --

         //find changes
         $updates = array();
         foreach ($old_values as $key => $old_value) {
            if (!isset($datas[$key])) {
               continue;
            }
            if ($old_value !== '' && $datas[$key] == 'NULL') {
               continue;
            }
            if ($datas[$key] !== $old_value) {
               $updates[$key] = array(0, $old_value, $datas[$key]);
            }
         }

         //for all change find searchoption
         foreach ($updates as $key => $changes) {
            foreach ($searchoptions as $id_search_option => $searchoption) {
               if ($searchoption['linkfield'] == $key) {
                  $changes[0] = $id_search_option;
                  break;
               }
            }   

            //add log
            Log::history($items_id, $itemtype, $changes);
         }
      }      
   }

   /**
    * check datas inserted (only nuber for the moment)
    * display a message when not ok
    * @param  array $datas : datas send by form
    * @return boolean
    */
   static function validateValues($datas) {
      global $LANG;

      $field_obj = new PluginFieldsField;
      $fields = $field_obj->find("plugin_fields_containers_id = ".
                                 $datas['plugin_fields_containers_id']." AND type = 'number'");

      unset($datas['plugin_fields_containers_id']);
      unset($datas['items_id']);
      unset($datas['update_fields_values']);
      $datas_keys = array_keys($datas);

      $fields_error = array();
      foreach ($fields as $fields_id => $field) {
         if (empty($datas[$field['name']])) continue;
         if (!preg_match("/[-+]?[0-9]*\.?[0-9]+/", $datas[$field['name']])) {
            $fields_error[] = $field['label'];
         }
      }

      if (!empty($fields_error)) {
         Session::AddMessageAfterRedirect($LANG['fields']['error']['no_numeric_value'].
                                          " : (".implode(", ", $fields_error).")");
         $_SESSION['plugin']['fields']['values_sent'] = $datas;
         return false;
      } else return true;
   }


   static function findContainer($itemtype, $items_id, $type='tab') {
      $container = new PluginFieldsContainer;
      $sql_type = "1=1";
      if ($type === 'tab' || $type === 'dom') {
         $sql_type = "`type` = '$type'";
      }
      $found_c = $container->find("$sql_type AND `itemtype` = '$itemtype' AND is_active = 1");

      if (count($found_c) == 0) return false;
      
      if ($type == "dom") {
         $tmp = array_shift($found_c);
         $id = $tmp['id'];
      } else {
         $id = array_keys($found_c);
         if (count($id) == 1) {
            $id = array_shift($id);
         }
      }

      return $id;
   }


   static function preItemUpdate(CommonDBTM $item) {
      //find container (if not exist, do nothing)
      if (isset($_REQUEST['c_id'])) {
         $c_id = $_REQUEST['c_id'];
      } else {
         $c_id = self::findContainer(get_Class($item), $item->fields['id'], "all");
         if ($c_id === false) return false;
      }

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
         if (isset($item->input[$input])) {
            $datas[$input] = $item->input[$input];
         }
      }

      //update datas
      $container = new self;
      return $container->updateFieldsValues($datas);
   }

   static function getAddSearchOptions($itemtype, $containers_id = false) {
      global $DB;

      $opt = array();

      $where = "";
      if ($containers_id !== false) {
         $where = "AND containers.id = $containers_id";
      }

      $i = 76665;
      $query = "SELECT fields.name, fields.label, fields.type, 
            containers.name as container_name, containers.label as container_label, 
            containers.itemtype
         FROM glpi_plugin_fields_containers containers
         INNER JOIN glpi_plugin_fields_fields fields
            ON containers.id = fields.plugin_fields_containers_id
            AND containers.is_active = 1
         WHERE containers.itemtype = '$itemtype'
            AND fields.type != 'header'
            $where
            ORDER BY fields.id ASC";
      $res = $DB->query($query);
      while ($datas = $DB->fetch_assoc($res)) {
         $tablename = "glpi_plugin_fields_".strtolower($datas['itemtype'].
                        getPlural(preg_replace('/s$/', '', $datas['container_name'])));
         $opt[$i]['table']         = $tablename;
         $opt[$i]['field']         = $datas['name'];
         $opt[$i]['name']          = $datas['container_label']." - ".$datas['label'];
         $opt[$i]['linkfield']     = $datas['name'];
         //$opt[$i]['condition']     = "glpi_plugin_fields_fields.name = '".$datas['name']."'";
         //$opt[$i]['massiveaction'] = false;
         $opt[$i]['joinparams']['jointype'] = "itemtype_item";
         $opt[$i]['pfields_type']  = $datas['type'];

         if ($datas['type'] === "dropdown") {
            $opt[$i]['table']      = 'glpi_plugin_fields_'.$datas['name'].'dropdowns';
            $opt[$i]['field']      = 'name';
            $opt[$i]['linkfield']     = "plugin_fields_".$datas['name']."dropdowns_id";
            $opt[$i]['searchtype'] = 'equals';
            $opt[$i]['joinparams']['jointype'] = "";
            $opt[$i]['joinparams']['beforejoin']['table'] = $tablename;
            $opt[$i]['joinparams']['beforejoin']['joinparams']['jointype'] = "itemtype_item";
         }

         switch ($datas['type']) {
            case 'dropdown':
               $opt[$i]['datatype'] = "dropdown";
               break;
            case 'yesno':
               $opt[$i]['datatype'] = "bool";
               break;
            case 'textarea':
               $opt[$i]['datatype'] = "text";
               break;
            case 'number':
               $opt[$i]['datatype'] = "number";
               break;
            case 'date':
            case 'datetime':
               $opt[$i]['datatype'] = $datas['type'];
               break;
            default:
               $opt[$i]['datatype'] = "string";
          } 

          //massive action searchoption
         /*$opt[$i+100000]                  = $opt[$i];
         $opt[$i+100000]['linkfield']     = $datas['name'];
         if ($datas['type'] === "dropdown") {
            $opt[$i+100000]['linkfield']     = "plugin_fields_".$datas['name']."dropdowns_id";
         }
         $opt[$i+100000]['massiveaction'] = true;
         $opt[$i+100000]['nosort']        = true;
         $opt[$i+100000]['nosearch']      = true;
         $opt[$i+100000]['datatype']      = "";*/

         $i++;
      }

      return $opt;
   }

}