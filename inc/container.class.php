<?php

class PluginFieldsContainer extends CommonDBTM {
   static $rightname = 'config';

   static function titleList() {
      echo "<center><input type='button' class='submit' value='&nbsp;".
            __("Regenerate container files", "fields")."&nbsp;'
            onclick='location.href=\"regenerate_files.php\"' /></center>";
   }

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
                  `subtype`      VARCHAR(255) DEFAULT NULL,
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

      if (!FieldExists($table, "subtype")) {
         $migration->addField($table, 'subtype', 'VARCHAR(255) DEFAULT NULL',array('after' => 'type'));
         $migration->migrationOneTable($table);
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
      $tab = array();

      $tab[1]['table']         = $this->getTable();
      $tab[1]['field']         = 'name';
      $tab[1]['name']          = __("Name");
      $tab[1]['datatype']      = 'itemlink';
      $tab[1]['itemlink_type'] = $this->getType();
      $tab[1]['massiveaction'] = false;

      $tab[2]['table']         = $this->getTable();
      $tab[2]['field']         = 'label';
      $tab[2]['name']          = __("Label");
      $tab[2]['massiveaction'] = false;

      $tab[3]['table']         = $this->getTable();
      $tab[3]['field']         = 'itemtype';
      $tab[3]['name']          = __("Associated item type");
      $tab[3]['datatype']       = 'itemtypename';
      $tab[3]['massiveaction'] = false;

      $tab[4]['table']         = $this->getTable();
      $tab[4]['field']         = 'type';
      $tab[4]['name']          = __("Type");
      $tab[4]['searchtype']    = 'equals';
      $tab[4]['massiveaction'] = false;

      $tab[5]['table']         = $this->getTable();
      $tab[5]['field']         = 'is_active';
      $tab[5]['name']          = __("Active");
      $tab[5]['datatype']      = 'bool';

      $tab[6]['table']         = 'glpi_entities';
      $tab[6]['field']         = 'completename';
      $tab[6]['name']          = __("Entity");
      $tab[6]['massiveaction'] = false;
      $tab[6]['datatype']      = 'dropdown';

      $tab[7]['table']         = $this->getTable();
      $tab[7]['field']         = 'is_recursive';
      $tab[7]['name']          = __("Child entities");
      $tab[7]['massiveaction'] = false;
      $tab[7]['datatype']      = 'bool';

      $tab[8]['table']         = $this->getTable();
      $tab[8]['field']         = 'id';
      $tab[8]['name']          = __("ID");
      $tab[8]['datatype']      = 'number';
      $tab[8]['massiveaction'] = false;

      return $tab;
   }

   static function getSpecificValueToDisplay($field, $values, array $options=array()) {
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
      $ong = array();
      $this->addDefaultFormTab($ong);
      $this->addStandardTab('PluginFieldsField', $ong, $options);
      $this->addStandardTab('PluginFieldsProfile', $ong, $options);

      return $ong;
   }

   function prepareInputForAdd($input) {
      if ($input['type'] === "dom") {
         //check for already exist dom container with this itemtype
         $found = $this->find("`type`='dom' AND `itemtype` = '".$input['itemtype']."'");
         if (!empty($found)) {
            Session::AddMessageAfterRedirect(__("You cannot add several blocs with type 'Insertion in the form' on same object", "fields"), false, ERROR);
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
      if(!self::generateTemplate($this->fields)) {
         return false;
      }

      //install table for receive field
      $classname = "PluginFields".ucfirst($this->fields['itemtype'].
                                          preg_replace('/s$/', '', $this->fields['name']));
      $classname::install();
   }

   public static function generateTemplate($fields) {
      $classname = "PluginFields".ucfirst($fields['itemtype'].
                                          preg_replace('/s$/', '', $fields['name']));

      $itemtype = $fields['itemtype'];

      $template_class = file_get_contents(GLPI_ROOT.
                                          "/plugins/fields/templates/container.class.tpl");
      $template_class = str_replace("%%CLASSNAME%%", $classname, $template_class);
      $template_class = str_replace("%%ITEMTYPE%%", $fields['itemtype'], $template_class);
      $template_class = str_replace("%%CONTAINER%%", $fields['id'], $template_class);
      $template_class = str_replace("%%ITEMTYPE_RIGHT%%", $itemtype::$rightname, $template_class);
      $class_filename = strtolower($fields['itemtype'].
                                   preg_replace('/s$/', '', $fields['name']).".class.php");
      if (file_put_contents(GLPI_ROOT."/plugins/fields/inc/$class_filename",
                            $template_class) === false) {
         Toolbox::logDebug("Error : class file creation - $class_filename");
         return false;
      }

      // Generate Datainjection files
      $template_class = file_get_contents(GLPI_ROOT.
                                          "/plugins/fields/templates/injection.class.tpl");
      $template_class = str_replace("%%CLASSNAME%%", $classname, $template_class);
      $template_class = str_replace("%%ITEMTYPE%%", $fields['itemtype'], $template_class);
      $template_class = str_replace("%%CONTAINER_ID%%", $fields['id'], $template_class);
      $template_class = str_replace("%%CONTAINER_NAME%%", $fields['label'], $template_class);
      $class_filename = strtolower($fields['itemtype'].
                                   preg_replace('/s$/', '', $fields['name'])."injection.class.php");
      if (file_put_contents(GLPI_ROOT."/plugins/fields/inc/$class_filename",
                            $template_class) === false) {
         Toolbox::logDebug("Error : datainjection class file creation - $class_filename");
         return false;
      }

      return true;
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

      //clean session
      unset($_SESSION['delete_container']);

      //remove file
      if (file_exists(GLPI_ROOT."/plugins/fields/inc/$class_filename")) {
         return unlink(GLPI_ROOT."/plugins/fields/inc/$class_filename");
      }


      return true;
   }

   static function preItemPurge($item) {
      $itemtype = get_class($item);
      $containers = new self();
      $founded_containers = $containers->find('itemtype = "' . $itemtype . '"');
      foreach($founded_containers as $container) {
         $classname = 'PluginFields' . $itemtype . getSingular($container['name']);
         $fields = new $classname();
         $fields->deleteByCriteria(array('items_id' => $item->fields['id']));
      }
      return true;
   }

   static function getTypeName($nb = 0) {
      return __("Bloc", "fields");
   }

   public function showForm($ID, $options=array()) {
   	global $CFG_GLPI;

      $this->initForm($ID, $options);
      $this->showFormHeader($options);
      $rand = mt_rand();

      echo "<tr>";
      echo "<td width='20%'>".__("Label")." : </td>";
      echo "<td width='30%'>";
      Html::autocompletionTextField($this, 'label', array('value' => $this->fields["label"]));
      echo "</td>";
      echo "<td width='20%'>&nbsp;</td>";
      echo "<td width='30%'>&nbsp;</td>";
      echo "</tr>";

      echo "<tr>";
      echo "<td>".__("Type")." : </td>";
      echo "<td>";
      if($ID > 0) {
         $types = self::getTypes();
         echo $types[$this->fields["type"]];
      } else {
         Dropdown::showFromArray('type',
         	                     self::getTypes(),
                                 array('value' => $this->fields["type"],
                                 	   'rand'  => $rand));
      }
      echo "</td>";
      echo "<td>".__("Associated item type")." : </td>";
      echo "<td>";
      if($ID > 0) {
         $obj = getItemForItemtype($this->fields["itemtype"]);
         echo $obj->getTypeName(1);
      } else {
         Dropdown::showFromArray('itemtype',
         	                     self::getItemtypes(),
            							array('value' => $this->fields["itemtype"],
            								   'rand'  => $rand));
      }
      echo "</td>";
      echo "</tr>";

      $display = "style='display:none'";
      if (!empty($this->fields["subtype"])) {
         $display = "";
      }
      echo "<tr id='tab_tr' $display>";
      echo "<td colspan='2'></td>";
      echo "<td>".__("Tab", "fields")." : </td>";
      echo "<td>";
      echo "&nbsp;<span id='subtype_$rand'></span>";
      if($ID > 0 && !empty($this->fields["subtype"])) {
         $item = new $this->fields["itemtype"];
         $item->getEmpty();
         $tabs = $item->defineTabs();
         echo $tabs[$this->fields["subtype"]];
      } else {
         $params = array('type'     => '__VALUE0__',
                         'itemtype' => '__VALUE1__',
                         'subtype'  => $this->fields["subtype"],
                         'rand'     => $rand);
         Ajax::updateItemOnSelectEvent(array("dropdown_type$rand", "dropdown_itemtype$rand"),
                                       "subtype_$rand",
                                       $CFG_GLPI["root_doc"].
                                       	"/plugins/fields/ajax/container_subtype_dropdown.php",
                                       $params);
      }
      echo "</td>";
      echo "</tr>";

      echo "<tr>";
      echo "<td>".__("Active")." : </td>";
      echo "<td>";
      Dropdown::showYesNo("is_active", $this->fields["is_active"]);
      echo "</td>";
      echo "</tr>";

      $this->showFormButtons($options);

      return true;
   }

   static function showFormSubtype($params) {
      echo "<script type='text/javascript'>jQuery('#tab_tr').hide();</script>";
      if (isset($params['type']) && $params['type'] == "domtab") {
         if (class_exists($params['itemtype'])) {
            $item = new $params['itemtype'];
            $item->getEmpty();
            $tabs = $item->defineTabs();

            list($id, ) = each($tabs);
            // delete first element of array
            unset($tabs[$id]);

            // delete Log of array (don't work with this tab)
            $tabs_to_remove = array('Log$1', 'TicketFollowup$1', 'TicketTask$1', 'Document_Item$1');
            foreach ($tabs_to_remove as $tab_to_remove) {
               if (isset($tabs[$tab_to_remove])) {
                  unset($tabs[$tab_to_remove]);
               }
            }

            // For delete <sup class='tab_nb'>number</sup> :
            foreach ($tabs as $key => &$value) {
               $results = array();
               if (preg_match_all('#<sup.*>(.+)</sup>#', $value, $results)) {
                  $value = str_replace($results[0][0], "", $value);
               }
            }

            Dropdown::showFromArray('subtype', $tabs, array('value' => $params['subtype'], 'width' => '100%'));
            echo "<script type='text/javascript'>jQuery('#tab_tr').show();</script>";
         }
      }
   }


   static function getItemtypes() {
      return array(
         __("Assets") => array(
            'Computer'           => _n("Computer", "Computers", 2),
            'Monitor'            => _n("Monitor", "Monitors", 2),
            'Software'           => _n("Software", "Software", 2),
            'NetworkEquipment'   => _n("Network", "Networks", 2),
            'Peripheral'         => _n("Device", "Devices", 2),
            'Printer'            => _n("Printer", "Printers", 2),
            'CartridgeItem'      => _n("Cartridge", "Cartridges", 2),
            'ConsumableItem'     => _n("Consumable", "Consumables", 2),
            'Phone'              => _n("Phone", "Phones", 2)),
         __("Assistance") => array(
            'Ticket'             => _n("Ticket", "Tickets", 2),
            'Problem'            => _n("Problem", "Problems", 2),
            'TicketRecurrent'    => __("Recurrent tickets")),
         __("Management") => array(
            'Budget'             => _n("Budget", "Budgets", 2),
            'Supplier'           => _n("Supplier", "Suppliers", 2),
            'Contact'            => _n("Contact", "Contacts", 2),
            'Contract'           => _n("Contract", "Contracts", 2),
            'Document'           => _n("Document", "Documents", 2)),
         __("Tools") => array(
            'Project'            => __("Project"),
            'ProjectTask'        => _n("Project task", "Project tasks", 2),
            'Reminder'           => _n("Note", "Notes", 2),
            'RSSFeed'            => __("RSS feed")),
         __("Administration") => array(
            'User'               => _n("User", "Users", 2),
            'Group'              => _n("Group", "Groups", 2),
            'Entity'             => _n("Entity", "Entities", 2),
            'Profile'            => _n("Profile", "Profiles", 2))
      );
   }

   static function getTypes() {
      return array(
         'tab'    => __("Add tab", "fields"),
         'dom'    => __("Insertion in the form (before save button)", "fields"),
         'domtab' => __("Insertion in the form of a specific tab (before save button)", "fields")
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

         if (Session::isCron() || !isset($_SESSION['glpiactiveprofile']['id'])) {
            continue;
         }
         //profiles restriction
         $found = $profile->find("`profiles_id` = '".$_SESSION['glpiactiveprofile']['id']."'
                                 AND `plugin_fields_containers_id` = '".$item['id']."'
                                 AND `right` >= ".READ);
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

   static function getUsedItemtypes($type = 'all', $must_be_active = false) {
      global $DB;
      $itemtypes = array();
      $where = ($type == 'all') ? '1=1' : 'type = "' . $type . '"';
      if($must_be_active)
         $where .= ' AND is_active = 1';

      $query = 'SELECT DISTINCT `itemtype`
                FROM `glpi_plugin_fields_containers`
                WHERE ' . $where;
      $result = $DB->query($query);
      while(list($itemtype) = $DB->fetch_array($result)) {
         $itemtypes[] = $itemtype;
      }

      return $itemtypes;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
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
   function updateFieldsValues($datas, $massiveaction = false) {
      global $DB;

      if (self::validateValues($datas, $massiveaction) === false) return false;

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
         // add fields datas
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
      // Don't log few itemtypes
      $obj = new $itemtype();
      if ($obj->dohistory == false) {
         return;
      }

      //get searchoptions
      $searchoptions = self::getAddSearchOptions($itemtype, $containers_id);

      //define non-datas keys
      $blacklist_k = array('plugin_fields_containers_id' => 0, 'items_id' => 0,
                              'update_fields_values' => 0, '_glpi_csrf_token' => 0);

      //remove non-datas keys
      $datas = array_diff_key($datas, $blacklist_k);

      //add/update values condition
      if (empty($old_values)) {
         // -- add new item --

         foreach ($datas as $key => $value) {
            //log only not empty values
            if (!empty($value)) {
               //prepare log
               $changes = array(0, "N/A", $value);

               //find searchoption
               foreach ($searchoptions as $id_search_option => $searchoption) {
                  if ($searchoption['linkfield'] == $key) {
                     $changes[0] = $id_search_option;

                     //manage dropdown values
                     if ($searchoption['datatype'] === 'dropdown') {
                        $changes = array($id_search_option, "",
                                         Dropdown::getDropdownName($searchoption['table'],$value));
                     }
                  }

                  if ($searchoption['datatype'] === 'bool') {
                     $changes = array($id_search_option, "", Dropdown::getYesNo($value));
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
            if (!isset($datas[$key])
                || empty($old_value) && empty($datas[$key])
                || $old_value !== '' && $datas[$key] == 'NULL'
                ) {
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

                  //manage dropdown values
                  if ($searchoption['datatype'] === 'dropdown') {
                     $changes[1] = Dropdown::getDropdownName($searchoption['table'],$changes[1]);
                     $changes[2] = Dropdown::getDropdownName($searchoption['table'],$changes[2]);
                  }
                  if ($searchoption['datatype'] === 'bool') {
                     $changes[1] = Dropdown::getYesNo($changes[1]);
                     $changes[2] = Dropdown::getYesNo($changes[2]);
                  }
               }
            }

            //add log
            Log::history($items_id, $itemtype, $changes);
         }
      }
   }

   /**
    * check datas inserted
    * display a message when not ok
    * @param  array $datas : datas send by form
    * @return boolean
    */
   static function validateValues($datas, $massiveaction) {
      $valid = true;
      $empty_errors  = array();
      $number_errors = array();

      $field_obj = new PluginFieldsField();
      $fields = $field_obj->find("plugin_fields_containers_id = ".
                                 $datas['plugin_fields_containers_id']);

      foreach ($fields as $fields_id => $field) {
         if ($field['type'] == "yesno") continue;
         if ($field['type'] == "header") continue;

         $name  = $field['name'];
         if(isset($datas[$name])) {
            $value = $datas[$name];
         } elseif(isset($datas['plugin_fields_' . $name . 'dropdowns_id'])) {
            $value = $datas['plugin_fields_' . $name . 'dropdowns_id'];
         } else {
            if ($massiveaction) continue;
            $value = '';
         }

         // Check mandatory fields
         if (($field['mandatory'] == 1)
             && (empty($value)
               || (in_array($field['type'], array('date', 'datetime')) && $value == 'NULL'))) {
            $empty_errors[] = $field['label'];
            $valid = false;

         // Check number fields
         } elseif($field['type'] == 'number' && !empty($value) && !is_numeric($value)) {
            $number_errors[] = $field['label'];
            $valid = false;
         }
      }

      if(!empty($empty_errors)) {
         Session::AddMessageAfterRedirect(__("Some mandatory fields are empty", "fields")
            . " : " . implode(', ', $empty_errors), false, ERROR);
      }

      if(!empty($number_errors)) {
         Session::AddMessageAfterRedirect(__("Some numeric fields contains non numeric values", "fields")
            . " : " . implode(', ', $number_errors), false, ERROR);
      }

      return $valid;
   }


   static function findContainer($itemtype, $items_id, $type = 'tab', $subtype = '') {
      $sql_type = "`type` = '$type'";
      $entity = isset($_SESSION['glpiactive_entity']) ? $_SESSION['glpiactive_entity'] : 0;
      $sql_entity = getEntitiesRestrictRequest("AND", "", "", $entity, true, true);

      $sql_subtype = '';
      if ($subtype != '') {
         if ($subtype == $itemtype.'$main') {
            $sql_subtype = " AND type = 'dom' ";
         } else {
            $sql_subtype = " AND type != 'dom' AND subtype = '$subtype' ";
         }
      }

      $container = new PluginFieldsContainer();
      $found_c = $container->find($sql_type." AND `itemtype` = '$itemtype' AND is_active = 1 ".$sql_entity.$sql_subtype);
      if (empty($found_c)) {
         return false;
      }

      if ($type == "dom") {
         $tmp = array_shift($found_c);
         $id = $tmp['id'];
      } else {
         $id = array_keys($found_c);
         if (count($id) == 1) {
            $id = array_shift($id);
         }
      }

      //profiles restriction
      if (isset($_SESSION['glpiactiveprofile']['id'])) {
         $profile = new PluginFieldsProfile();
         if (is_array($id)) {
            $condition = "`plugin_fields_containers_id` IN (".implode(", ", $id).")";
         } else {
            $condition = "`plugin_fields_containers_id` = '$id'";
         }
         $found = $profile->find("`profiles_id` = '".$_SESSION['glpiactiveprofile']['id']."'
                                 AND $condition");
         $first_found = array_shift($found);
         if ($first_found['right'] == NULL) {
            return false;
         }
      }

      return $id;
   }


   static function preItemUpdate(CommonDBTM $item) {
      //find container (if not exist, do nothing)
      if (isset($_REQUEST['c_id'])) {
         $c_id = $_REQUEST['c_id'];
      } else {
         $c_id = self::findContainer(get_Class($item), $item->fields['id'], "dom");
         if ($c_id === false) return false;
      }

      //find fields associated to found container
      $field_obj = new PluginFieldsField();
      $fields = $field_obj->find("plugin_fields_containers_id = $c_id AND type != 'header'", "ranking");

      //prepare datas to update
      $datas = array('plugin_fields_containers_id' => $c_id,
                     'items_id'                    =>  $item->fields['id']);

      foreach($fields as $field) {
         if (isset($item->input[$field['name']])) {
            //standard field
            $input = $field['name'];
         } else {
            //dropdown field
            $input = "plugin_fields_".$field['name']."dropdowns_id";
         }
         if (isset($item->input[$input])) {
            // Before is_number check, help user to have a number correct, during a massive action of a number field
            if ($field['type'] == 'number') {
               $item->input[$input] = str_replace(",", ".", $item->input[$input]);
            }
            $datas[$input] = $item->input[$input];
         }
      }

      //update datas
      $container = new self();
      if ($container->updateFieldsValues($datas, isset($_REQUEST['massiveaction']))) {
         return true;
      }
      return $item->input = array();
   }

   static function getAddSearchOptions($itemtype, $containers_id = false) {
      global $DB;

      $opt = array();

      $where = "";
      if ($containers_id !== false) {
         $where = "AND containers.id = $containers_id";
      }

      $i = 76665;

      $query = "SELECT fields.name, fields.label, fields.type, fields.is_readonly,
            containers.name as container_name, containers.label as container_label,
            containers.itemtype
         FROM glpi_plugin_fields_containers containers
         INNER JOIN glpi_plugin_fields_fields fields
            ON containers.id = fields.plugin_fields_containers_id
            AND containers.is_active = 1
         WHERE containers.itemtype = '$itemtype'
            AND fields.type != 'header'
            $where
            ORDER BY fields.ranking ASC, fields.id ASC";
      $res = $DB->query($query);
      while ($datas = $DB->fetch_assoc($res)) {
         $tablename = "glpi_plugin_fields_".strtolower($datas['itemtype'].
                        getPlural(preg_replace('/s$/', '', $datas['container_name'])));

         $opt[$i]['table']         = $tablename;
         $opt[$i]['field']         = $datas['name'];
         $opt[$i]['name']          = $datas['container_label']." - ".$datas['label'];
         $opt[$i]['linkfield']     = $datas['name'];
         $opt[$i]['joinparams']['jointype'] = "itemtype_item";
         $opt[$i]['pfields_type']  = $datas['type'];

         // No massive action for this field is the field is readonly
         if( $datas['is_readonly'] ) {
             $opt[$i]['massiveaction'] = false;
         }

         if ($datas['type'] === "dropdown") {
            $opt[$i]['table']      = 'glpi_plugin_fields_'.$datas['name'].'dropdowns';
            $opt[$i]['field']      = 'name';
            $opt[$i]['linkfield']  = "plugin_fields_".$datas['name']."dropdowns_id";
            $opt[$i]['searchtype'] = 'equals';

            $opt[$i]['forcegroupby'] = true;

            $opt[$i]['joinparams']['jointype'] = "";
            $opt[$i]['joinparams']['beforejoin']['table'] = $tablename;
            $opt[$i]['joinparams']['beforejoin']['joinparams']['jointype'] = "itemtype_item";
         }
         if ($datas['type'] === "dropdownuser") {
            $opt[$i]['table']      = 'glpi_users';
            $opt[$i]['field']      = 'name';
            $opt[$i]['linkfield']  = $datas['name'];
            $opt[$i]['right'] = 'all';

            $opt[$i]['forcegroupby'] = true;

            $opt[$i]['joinparams']['jointype'] = "";
            $opt[$i]['joinparams']['beforejoin']['table'] = $tablename;
            $opt[$i]['joinparams']['beforejoin']['joinparams']['jointype'] = "itemtype_item";

            // Quick fix
            $opt[$i]['massiveaction'] = false;
         }

         switch ($datas['type']) {
             case 'dropdown':
             case 'dropdownuser':
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

         $i++;
      }

      return $opt;
   }

}
