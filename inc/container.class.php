<?php

class PluginFieldsContainer extends CommonDBTM {
   static $rightname = 'config';

   static function canCreate() {
      return self::canUpdate();
   }

   static function titleList() {
      echo "<center><input type='button' class='submit' value='&nbsp;".
            __("Regenerate container files", "fields")."&nbsp;'
            onclick='location.href=\"regenerate_files.php\"' /></center>";
   }

   /**
    * Install or update containers
    *
    * @param Migration $migration Migration instance
    * @param string    $version   Plugin current version
    *
    * @return boolean
    */
   static function install(Migration $migration, $version) {
      global $DB;

      $obj = new self();
      $table = $obj->getTable();

      if (!TableExists($table)) {
         $migration->displayMessage(sprintf(__("Installing %s"), $table));

         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`           INT(11)        NOT NULL auto_increment,
                  `name`         VARCHAR(255)   DEFAULT NULL,
                  `label`        VARCHAR(255)   DEFAULT NULL,
                  `itemtypes`     LONGTEXT   DEFAULT NULL,
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

      // multiple itemtype for one container
      if (!FieldExists($table, "itemtypes")) {
         $migration->changeField($table, 'itemtype', 'itemtypes', 'longtext');
         $migration->migrationOneTable($table);

         $query = "UPDATE `$table` SET `itemtypes` = CONCAT('[\"', `itemtypes`, '\"]')";
         $DB->query($query) or die ($DB->error());
      }

      //add display preferences for this class
      $d_pref = new DisplayPreference;
      $found = $d_pref->find("itemtype = '".__CLASS__."'");
      if (count($found) == 0) {
         for ($i = 2; $i <= 5; $i++) {
            $DB->query("REPLACE INTO glpi_displaypreferences VALUES
               (NULL, '".__CLASS__."', $i, ".($i-1).", 0)");
         }
      }

      if (!FieldExists($table, "subtype")) {
         $migration->addField($table, 'subtype', 'VARCHAR(255) DEFAULT NULL', array('after' => 'type'));
         $migration->migrationOneTable($table);
      }

      $migration->displayMessage(__("Updating generated containers files", "fields"));
      // -> 0.90-1.3: generated class moved
      // OLD path: GLPI_ROOT."/plugins/fields/inc/$class_filename"
      // NEW path: PLUGINFIELDS_CLASS_PATH . "/$class_filename"
      $obj = new self;
      $containers = $obj->find();
      foreach ($containers as $container) {
         //First, drop old fields from plugin directories
         $itemtypes = (count($container['itemtypes']) > 0) ? json_decode($container['itemtypes'], true) : array();
         foreach ($itemtypes as $itemtype) {
            $class_filename = strtolower($itemtype .
               preg_replace('/s$/', '', $container['name']) . ".class.php");
            if (file_exists(GLPI_ROOT."/plugins/fields/inc/$class_filename")) {
               unlink(GLPI_ROOT."/plugins/fields/inc/$class_filename");
            }

            $injclass_filename = strtolower($itemtype .
               preg_replace('/s$/', '', $container['name']) . "injection.class.php");
            if (file_exists(GLPI_ROOT."/plugins/fields/inc/$injclass_filename")) {
               unlink(GLPI_ROOT."/plugins/fields/inc/$injclass_filename");
            }
         }

         //Second, create new files
         self::generateTemplate($container);
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
      $tab[3]['field']         = 'itemtypes';
      $tab[3]['name']          = __("Associated item type");
      $tab[3]['datatype']       = 'specific';
      $tab[3]['massiveaction'] = false;
      $tab[3]['nosearch']      = true;

      $tab[4]['table']         = $this->getTable();
      $tab[4]['field']         = 'type';
      $tab[4]['name']          = __("Type");
      $tab[4]['searchtype']    = array('equals', 'notequals');
      $tab[4]['massiveaction'] = false;

      $tab[5]['table']         = $this->getTable();
      $tab[5]['field']         = 'is_active';
      $tab[5]['name']          = __("Active");
      $tab[5]['datatype']      = 'bool';
      $tab[5]['searchtype']    = array('equals', 'notequals');

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
         case 'itemtypes' :
            $types = json_decode($values[$field]);
            $obj   = '';
            $count = count($types);
            $i     = 1;
            foreach ($types as $type) {
               $name_type = getItemForItemtype($type);
               $obj .= $name_type->getTypeName(2);
               if ($count > $i) {
                  $obj .= ", ";
               }
               $i++;
            }
            return $obj;
            break;
      }
   }


   function getValueToSelect($field_id_or_search_options, $name = '', $values = '', $options = array()) {

      switch ($field_id_or_search_options['table'].'.'.$field_id_or_search_options['field']) {
         // For searchoption "Type"
         case $this->getTable().'.type':
            $options['display'] = false;
            return Dropdown::showFromArray($name, self::getTypes(), $options);
         case $this->getTable().'.itemtypes':
            $options['display'] = false;
            return Dropdown::showFromArray($name, self::getItemtypes(), $options);
      }

      return parent::getValueToSelect($field_id_or_search_options, $name, $values, $options);
   }

   function defineTabs($options=array()) {
      $ong = array();
      $this->addDefaultFormTab($ong);
      $this->addStandardTab('PluginFieldsField', $ong, $options);
      $this->addStandardTab('PluginFieldsProfile', $ong, $options);
      $this->addStandardTab('PluginFieldsLabelTranslation', $ong, $options);

      return $ong;
   }

   function prepareInputForAdd($input) {
      if (!isset($input['itemtypes'])) {
         Session::AddMessageAfterRedirect(__("You cannot add block without associated element type", "fields"), false, ERROR);
         return false;
      }

      if (!is_array($input['itemtypes'])) {
         $input['itemtypes'] = array($input['itemtypes']);
      }

      if ($input['type'] === "dom") {
         //check for already exist dom container with this itemtype
         $found = $this->find("`type`='dom'");
         if (count($found) > 0) {
            foreach (array_column($found, 'itemtypes') as $founditemtypes) {
               foreach (json_decode($founditemtypes) as $founditemtype) {
                  if (in_array( $founditemtype, $input['itemtypes'])) {
                     Session::AddMessageAfterRedirect(__("You cannot add several blocks with type 'Insertion in the form' on same object", "fields"), false, ERROR);
                     return false;
                  }
               }
            }
         }
      }

      if ($input['type'] === "domtab") {
         //check for already exist domtab container with this itemtype on this tab
         $found = $this->find("`type`='domtab' AND `subtype`='{$input['subtype']}'");
         if (count($found) > 0) {
            foreach (array_column( $found, 'itemtypes' ) as $founditemtypes) {
               foreach (json_decode( $founditemtypes ) as $founditemtype) {
                  if (in_array( $founditemtype, $input['itemtypes'])) {
                     Session::AddMessageAfterRedirect(__("You cannot add several blocks with type 'Insertion in the form of a specific tab' on same object tab", "fields"), false, ERROR);
                     return false;
                  }
               }
            }
         }
      }

      //construct field name by processing label (remove non alphanumeric char and any trailing s)
      $input['name'] = strtolower(preg_replace("/[^\da-z]/i", "", preg_replace('/s*$/', '', $input['label'])));
      // if empty, uses a random number
      if (strlen( $input['name'] ) == 0) {
         $input['name'] = rand();
      }

      //check for already existing container with same name
      $found = $this->find("`name`='".$input['name']."'");
      if (count($found) > 0) {
         foreach (array_column($found, 'itemtypes') as $founditemtypes) {
            foreach (json_decode($founditemtypes) as $founditemtype) {
               if (in_array($founditemtype, $input['itemtypes'])) {
                  Session::AddMessageAfterRedirect(__("You cannot add several blocs with identical name on same object", "fields"), false, ERROR);
                  return false;
               }
            }
         }
      }

      $input['itemtypes'] = (isset($input['itemtypes'])) ? json_encode($input['itemtypes'], TRUE): NULL;

      return $input;
   }

   function post_addItem() {
      //create profiles associated to this container
      PluginFieldsProfile::createForContainer($this);
      //Create label translation
      PluginFieldsLabelTranslation::createForItem($this);

      //create class file
      if (!self::generateTemplate($this->fields)) {
         return false;
      }
      foreach (json_decode($this->fields['itemtypes']) as $itemtype) {
         //install table for receive field
         $classname = "PluginFields" . ucfirst($itemtype .
               preg_replace('/s$/', '', $this->fields['name']));
         $classname::install();
      }
   }

   public static function generateTemplate($fields) {
      $itemtypes = (strlen($fields['itemtypes']) > 0) ? json_decode($fields['itemtypes'], TRUE) : array();
      foreach ($itemtypes as $itemtype) {
         $classname = "PluginFields" . ucfirst($itemtype .
               preg_replace('/s$/', '', $fields['name']));

         $template_class = file_get_contents(GLPI_ROOT .
            "/plugins/fields/templates/container.class.tpl");
         $template_class = str_replace("%%CLASSNAME%%", $classname, $template_class);
         $template_class = str_replace("%%ITEMTYPE%%", $itemtype, $template_class);
         $template_class = str_replace("%%CONTAINER%%", $fields['id'], $template_class);
         $template_class = str_replace("%%ITEMTYPE_RIGHT%%", $itemtype::$rightname, $template_class);
         $class_filename = strtolower($itemtype .
            preg_replace('/s$/', '', $fields['name']) . ".class.php");
         if (file_put_contents(PLUGINFIELDS_CLASS_PATH . "/$class_filename", $template_class) === false) {
            Toolbox::logDebug("Error : class file creation - $class_filename");
            return false;
         }

         // Generate Datainjection files
         $template_class = file_get_contents(GLPI_ROOT .
            "/plugins/fields/templates/injection.class.tpl");
         $template_class = str_replace("%%CLASSNAME%%", $classname, $template_class);
         $template_class = str_replace("%%ITEMTYPE%%", $itemtype, $template_class);
         $template_class = str_replace("%%CONTAINER_ID%%", $fields['id'], $template_class);
         $template_class = str_replace("%%CONTAINER_NAME%%", $fields['label'], $template_class);
         $class_filename = strtolower($itemtype .
            preg_replace('/s$/', '', $fields['name']) . "injection.class.php");
         if (file_put_contents(PLUGINFIELDS_CLASS_PATH . "/$class_filename", $template_class) === false) {
            Toolbox::logDebug("Error : datainjection class file creation - $class_filename");
            return false;
         }
      }
      return true;
   }

   function pre_deleteItem() {
      $_SESSION['delete_container'] = true;

      foreach (json_decode($this->fields['itemtypes']) as $itemtype) {

         $classname = "PluginFields".ucfirst(strtolower($itemtype.
                                             preg_replace('/s$/', '', $this->fields['name'])));
         $class_filename = strtolower($itemtype.
                                      preg_replace('/s$/', '', $this->fields['name'])).".class.php";

         $injection_filename = strtolower($itemtype.
                                      preg_replace('/s$/', '', $this->fields['name']))."injection.class.php";

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

         //delete label translations
         $translation_obj = new PluginFieldsLabelTranslation();
         $translations = $translation_obj->find("plugin_fields_itemtype = '" . self::getType() .
                                                "' AND plugin_fields_items_id = ". $this->fields['id']);
         foreach ($translations as $translation_id => $translation) {
            $translation_obj->delete(['id' => $translation_id]);
         }

         //delete table
         if (class_exists($classname)) {
            $classname::uninstall();
         } else {
            //class does not exists; try to remove any existing table
            $tablename = "glpi_plugin_fields_" . strtolower(
               $itemtype . getPlural(preg_replace('/s$/', '', $this->fields['name']))
            );
            $DB->query("DROP TABLE IF EXISTS `$tablename`");
         }

         //clean session
         unset($_SESSION['delete_container']);

         //remove file
         if (file_exists(PLUGINFIELDS_CLASS_PATH . "/$class_filename")) {
            unlink(PLUGINFIELDS_CLASS_PATH . "/$class_filename");
         }

         if (file_exists(PLUGINFIELDS_CLASS_PATH . "/$injection_filename")) {
            unlink(PLUGINFIELDS_CLASS_PATH . "/$injection_filename");
         }
      }

      return true;
   }

   static function preItemPurge($item) {
      $itemtype = get_class($item);
      $containers = new self();
      $founded_containers = $containers->find();
      foreach ($founded_containers as $container) {
         $itemtypes = json_decode($container['itemtypes']);
         if (in_array($itemtype, $itemtypes)) {
            $classname = 'PluginFields' . $itemtype . getSingular($container['name']);
            $fields = new $classname();
            $fields->deleteByCriteria(array('items_id' => $item->fields['id']));
         }
      }
      return true;
   }

   static function getTypeName($nb = 0) {
      return __("Block", "fields");
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
      if ($ID > 0) {
         $types = self::getTypes();
         echo $types[$this->fields["type"]];
      } else {
         Dropdown::showFromArray('type',
                                 self::getTypes(),
                                 array('value' => $this->fields["type"],
                                       'rand'  => $rand));
         $params = array('type'     => '__VALUE__',
                         'itemtype' => $this->fields["itemtypes"],
                         'subtype'  => $this->fields['subtype'],
                         'rand'     => $rand);
         Ajax::updateItemOnSelectEvent("dropdown_type$rand",
                                       "itemtypes_$rand",
                                       "../ajax/container_itemtypes_dropdown.php",
                                       $params);
      }
      echo "</td>";
      echo "<td>".__("Associated item type")." : </td>";
      echo "<td>";
      if ($ID > 0) {
         $types = json_decode($this->fields['itemtypes']);
         $obj = '';
         $count = count($types);
         $i = 1;
         foreach ($types as $type) {
            $name_type = getItemForItemtype($type);
            $obj .= $name_type->getTypeName(2);
            if ($count > $i) {
               $obj .= ", ";
            }
            $i++;
         }
         echo $obj;

      } else {
         echo "&nbsp;<span id='itemtypes_$rand'>";
         self::showFormItemtype(array('rand'    => $rand,
                                      'subtype' => $this->fields['subtype']));
         echo "</span>";
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
      if ($ID > 0 && !empty($this->fields["subtype"])) {
         $itemtypes = json_decode($this->fields["itemtypes"], true);
         $itemtype = array_shift($itemtypes);
         $item = new $itemtype;
         $item->getEmpty();
         $tabs = self::getSubtypes($item);
         echo $tabs[$this->fields["subtype"]];
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

   static function showFormItemtype($params = array()) {
      global $CFG_GLPI;

      $is_domtab = isset($params['type']) && $params['type'] == 'domtab';

      $rand = $params['rand'];
      Dropdown::showFromArray("itemtypes", self::getItemtypes($is_domtab),
                              array('rand'                => $rand,
                                    'multiple'            => !$is_domtab,
                                    'width'               => 200,
                                    'display_emptychoice' => $is_domtab));

      if ($is_domtab) {
         $params = array('type'     => '__VALUE0__',
                         'itemtype' => '__VALUE1__',
                         'subtype'  => $params["subtype"],
                         'rand'     => $rand);
         Ajax::updateItemOnSelectEvent(array("dropdown_type$rand", "dropdown_itemtypes$rand"),
                                       "subtype_$rand",
                                       "../ajax/container_subtype_dropdown.php",
                                       $params);
      }
   }

   /**
    * Show subtype selection form
    *
    * @param array   $params  Parameters
    * @param boolean $display Whether to display or not; defaults to false
    *
    * @return string|void
    */
   static function showFormSubtype($params, $display = false) {
      $out = "<script type='text/javascript'>jQuery('#tab_tr').hide();</script>";
      if (isset($params['type']) && $params['type'] == "domtab") {
         if (class_exists($params['itemtype'])) {
            $item = new $params['itemtype'];
            $item->getEmpty();

            $tabs = self::getSubtypes($item);

            if (count($tabs)) {
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

               if (!isset($params['subtype'])) {
                  $params['subtype'] = null;
               }

               $out .= Dropdown::showFromArray('subtype', $tabs, array('value' => $params['subtype'], 'width' => '100%', 'display' => false));
               $out .= "<script type='text/javascript'>jQuery('#tab_tr').show();</script>";
            }
         }
      }
      if ($display === false) {
         return $out;
      } else {
         echo $out;
      }
   }

   /**
    * Get supported item types
    *
    * @param boolean $is_domtab Domtab or not
    *
    * @return array
    */
   static function getItemtypes($is_domtab) {
      global $PLUGIN_HOOKS;

      $tabs = [];

      $assets = ['Computer' => _n("Computer", "Computers", 2)];
      if (!$is_domtab) {
         $assets += [
            'Monitor'            => _n("Monitor", "Monitors", 2),
            'Software'           => _n("Software", "Software", 2),
            'NetworkEquipment'   => _n("Network", "Networks", 2),
            'Peripheral'         => _n("Device", "Devices", 2),
            'Printer'            => _n("Printer", "Printers", 2),
            'CartridgeItem'      => _n("Cartridge", "Cartridges", 2),
            'ConsumableItem'     => _n("Consumable", "Consumables", 2),
            'Phone'              => _n("Phone", "Phones", 2)
         ];
      }
      $tabs[__('Assets')] = $assets;

      $assistance = [
         'Ticket'  => _n("Ticket", "Tickets", 2),
         'Problem' => _n("Problem", "Problems", 2),
         'Change'  => _n("Change", "Changes", 2),
      ];
      if (!$is_domtab) {
         $assistance += [
            'TicketRecurrent'    => __("Recurrent tickets")
         ];
      }
      $tabs[__('Assistance')] = $assistance;

      if (!$is_domtab) {
         $tabs += [
            __("Management") => array(
               'SoftwareLicense'    => _n("License", "Licenses", 2),
               'Budget'             => _n("Budget", "Budgets", 2),
               'Supplier'           => _n("Supplier", "Suppliers", 2),
               'Contact'            => _n("Contact", "Contacts", 2),
               'Contract'           => _n("Contract", "Contracts", 2),
               'Document'           => _n("Document", "Documents", 2)),
            __("Tools") => array(
               'Project'            => __("Project"),
               'ProjectTask'        => _n("Project task", "Project tasks", 2),
               'Reminder'           => _n("Note", "Notes", 2),
               'RSSFeed'            => __("RSS feed"))
         ];
      }

      $administration = [];
      if (!$is_domtab) {
         $administration += [
            'User'               => _n("User", "Users", 2),
            'Group'              => _n("Group", "Groups", 2)
         ];
      }
      $administration['Entity'] = _n("Entity", "Entities", 2);
      if (!$is_domtab) {
         $administration += [
            'Profile'            => _n("Profile", "Profiles", 2)
         ];
      }
      $tabs[__('Administration')] = $administration;

      foreach ($PLUGIN_HOOKS['plugin_fields'] as $itemtype) {
         $isPlugin = isPluginItemType($itemtype);
         if ($isPlugin) {
            $plugin_name = Plugin::getInfo($isPlugin['plugin'], 'name');

            $tabs[__("Plugins")][$itemtype] = $plugin_name . ' - ' . $itemtype::getTypeName(Session::getPluralNumber());
         }
      }

      return $tabs;
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
      foreach ($found as $item) {
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
         if ($first_found['right'] == NULL || $first_found['right'] == 0) {
            continue;
         }

         $jsonitemtypes = json_decode($item['itemtypes']);
         //show more info or not
         foreach ($jsonitemtypes as $k => $v) {
            if ($full) {
               //check for translation
               $item['itemtype'] = self::getType();
               $label = PluginFieldsLabelTranslation::getLabelFor($item);
               $itemtypes[$v][$item['name']] = $label;
            } else {
               $itemtypes[] = $v;
            }
         }
      }
      return $itemtypes;
   }

   static function getUsedItemtypes($type = 'all', $must_be_active = false) {
      global $DB;
      $itemtypes = array();
      $where = ($type == 'all') ? '1=1' : 'type = "' . $type . '"';
      if ($must_be_active) {
         $where .= ' AND is_active = 1';
      }

      $query = 'SELECT DISTINCT `itemtypes`
                FROM `glpi_plugin_fields_containers`
                WHERE ' . $where;
      $result = $DB->query($query);
      while (list($data) = $DB->fetch_array($result)) {
         $jsonitemtype = json_decode($data);
         $itemtypes = array_merge($itemtypes, $jsonitemtype);
      }

      return $itemtypes;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      $itemtypes = self::getEntries('tab', true);
      if (isset($itemtypes[$item->getType()])) {
         $tabs_entries = array();
         $container = new self;
         foreach ($itemtypes[$item->getType()] as $tab_name => $tab_label) {
            // needs to check if entity of item is in hierachy of $tab_name
            foreach ($container->find("`is_active` = 1 AND `name` = '$tab_name'") as $data) {
               $dataitemtypes = json_decode($data['itemtypes']);
               if (in_array(get_class($item), $dataitemtypes) != FALSE) {
                  $entities = array( $data['entities_id'] );
                  if ($data['is_recursive']) {
                     $entities = getSonsOf( getTableForItemType( 'Entity' ), $data['entities_id']);
                  }
                  if (in_array($item->fields['entities_id'], $entities)) {
                     $tabs_entries[$tab_name] = $tab_label;
                  }
               }
            }
         }
         return $tabs_entries;
      }
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      //retrieve container for current tab
      $container = new self;
      $found_c   = $container->find("`type` = 'tab' AND `name` = '$tabnum' AND is_active = 1");
      foreach ($found_c as $data) {
         $dataitemtypes = json_decode($data['itemtypes']);
         if (in_array(get_class($item), $dataitemtypes) != FALSE) {
            return PluginFieldsField::showForTabContainer($data['id'], $item->fields['id'], get_class($item));
         }
      }
   }

   /**
    * Insert values submited by fields container
    *
    * @param array   $data          data posted
    * @param string  $itemtype      Item type
    * @param boolean $massiveaction Is a massive action
    *
    * @return boolean
    */
   function updateFieldsValues($data, $itemtype, $massiveaction = false) {
      global $DB;

      if (self::validateValues($data, $itemtype, $massiveaction) === false) {
          return false;
      }

      $container_obj = new PluginFieldsContainer;
      $container_obj->getFromDB($data['plugin_fields_containers_id']);

      $items_id = $data['items_id'];

      $classname = "PluginFields".ucfirst($itemtype.
                                          preg_replace('/s$/', '', $container_obj->fields['name']));
      $obj = new $classname;
      //check if data already inserted
      $found = $obj->find("items_id = $items_id");
      if (empty($found)) {
         // add fields data
         $obj->add($data);

         //construct history on itemtype object (Historical tab)
         self::constructHistory($data['plugin_fields_containers_id'], $items_id,
                            $itemtype, $data);

      } else {
         $first_found = array_pop($found);
         $data['id'] = $first_found['id'];
         $obj->update($data);

         //construct history on itemtype object (Historical tab)
         self::constructHistory($data['plugin_fields_containers_id'], $items_id,
                            $itemtype, $data, $first_found);
      }

      return true;
   }

   /**
    * Add log in "itemtype" object on fields values update
    * @param  int    $containers_id container id
    * @param  int    $items_id      item id
    * @param  string $itemtype      item type
    * @param  array  $data          values send by update form
    * @param  array  $old_values    old values, if empty -> values add
    * @return nothing
    */
   static function constructHistory($containers_id, $items_id, $itemtype, $data,
                                $old_values = array()) {
      // Don't log few itemtypes
      $obj = new $itemtype();
      if ($obj->dohistory == false) {
         return;
      }

      //get searchoptions
      $searchoptions = self::getAddSearchOptions($itemtype, $containers_id);

      //define non-data keys
      $blacklist_k = array('plugin_fields_containers_id' => 0, 'items_id' => 0, 'itemtype' => $itemtype,
                              'update_fields_values' => 0, '_glpi_csrf_token' => 0);

      //remove non-data keys
      $data = array_diff_key($data, $blacklist_k);

      //add/update values condition
      if (empty($old_values)) {
         // -- add new item --

         foreach ($data as $key => $value) {
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
                                         Dropdown::getDropdownName($searchoption['table'], $value));
                     }

                     //manage bool dropdown values
                     if ($searchoption['datatype'] === 'bool') {
                        $changes = array($id_search_option, "", Dropdown::getYesNo($value));
                     }
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
            if (!isset($data[$key])
                || empty($old_value) && empty($data[$key])
                || $old_value !== '' && $data[$key] == 'NULL'
                ) {
               continue;
            }

            if ($data[$key] !== $old_value) {
               $updates[$key] = array(0, $old_value, $data[$key]);
            }
         }

         //for all change find searchoption
         foreach ($updates as $key => $changes) {
            foreach ($searchoptions as $id_search_option => $searchoption) {
               if ($searchoption['linkfield'] == $key) {
                  $changes[0] = $id_search_option;

                  //manage dropdown values
                  if ($searchoption['datatype'] === 'dropdown') {
                     $changes[1] = Dropdown::getDropdownName($searchoption['table'], $changes[1]);
                     $changes[2] = Dropdown::getDropdownName($searchoption['table'], $changes[2]);
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
    * check data inserted
    * display a message when not ok
    *
    * @param array   $data          Data send by form
    * @param string  $itemtype      Item type
    * @param boolean $massiveaction ?
    *
    * @return boolean
    */
   static function validateValues($data, $itemtype, $massiveaction) {
      global $DB;

      $valid = true;
      $empty_errors  = array();
      $number_errors = array();

      $container = new self();
      $container->getFromDB($data['plugin_fields_containers_id']);

      $field_obj = new PluginFieldsField();
      $fields = $field_obj->find("plugin_fields_containers_id = ".
                                 $data['plugin_fields_containers_id']);

      foreach ($fields as $fields_id => $field) {
         if ($field['type'] == "yesno" ||$field['type'] == "header") {
            continue;
         }

         $name  = $field['name'];
         if (isset($data[$name])) {
            $value = $data[$name];
         } else if (isset($data['plugin_fields_' . $name . 'dropdowns_id'])) {
            $value = $data['plugin_fields_' . $name . 'dropdowns_id'];
         } else if ($field['mandatory'] == 1) {
            $tablename = "glpi_plugin_fields_" . strtolower(
               $itemtype . getPlural(preg_replace('/s$/', '', $container->fields['name']))
            );

            $query = "SELECT * FROM `$tablename` WHERE
               `itemtype`='$itemtype'
               AND `items_id`='{$data['items_id']}'
               AND `plugin_fields_containers_id`='{$data['plugin_fields_containers_id']}'";

            $db_result = [];
            if ($result = $DB->query($query)) {
               $db_result = $DB->fetch_assoc($result);
               if (isset($db_result[$name])) {
                  $value = $db_result[$name];
               }
            }

         } else {
            if ($massiveaction) {
               continue;
            }
            $value = '';
         }

         //translate label
         $field['itemtype'] = PluginFieldsField::getType();
         $field['label'] = PluginFieldsLabelTranslation::getLabelFor($field);

         // Check mandatory fields
         if (($field['mandatory'] == 1)
             && (empty($value)
               || (in_array($field['type'], array('date', 'datetime')) && $value == 'NULL'))) {
            $empty_errors[] = $field['label'];
            $valid = false;
         } else if ($field['type'] == 'number' && !empty($value) && !is_numeric($value)) {
            // Check number fields
            $number_errors[] = $field['label'];
            $valid = false;
         } else if ($field['type'] == 'url' && !empty($value)) {
            if (filter_var($value, FILTER_VALIDATE_URL) === false) {
               $url_errors[] = $field['label'];
               $valid = false;
            }
         }
      }

      if (!empty($empty_errors)) {
         Session::AddMessageAfterRedirect(__("Some mandatory fields are empty", "fields")
            . " : " . implode(', ', $empty_errors), false, ERROR);
      }

      if (!empty($number_errors)) {
         Session::AddMessageAfterRedirect(__("Some numeric fields contains non numeric values", "fields")
            . " : " . implode(', ', $number_errors), false, ERROR);
      }

      if (!empty($url_errors)) {
         Session::AddMessageAfterRedirect(__("Some URL fields contains invalid links", "fields")
            . " : " . implode(', ', $url_errors), false, ERROR);
      }

      return $valid;
   }


   static function findContainer($itemtype, $type = 'tab', $subtype = '') {
      $sql_type = "`type` = '$type'";
      $entity = isset($_SESSION['glpiactiveentities']) ? $_SESSION['glpiactiveentities'] : 0;
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
      $itemtypes = $container->find($sql_type." AND is_active = 1 ".$sql_entity.$sql_subtype);
      $id = 0;
      if (count($itemtypes) < 1) {
         return false;
      }

      foreach ($itemtypes as $data) {
         $dataitemtypes = json_decode($data['itemtypes']);
         $item = new $itemtype();
         if (in_array($item->getType(), $dataitemtypes) != FALSE) {
            $id = $data['id'];
         }
      }

      //profiles restriction
      if (isset($_SESSION['glpiactiveprofile']['id'])) {
         $profile = new PluginFieldsProfile();
         if (isset($id)) {
            if (is_array($id)) {
               $condition = "`plugin_fields_containers_id` IN (" . implode(", ", $id) . ")";
            } else {
               $condition = "`plugin_fields_containers_id` = '$id'";
            }
            $found       = $profile->find("`profiles_id` = '" . $_SESSION['glpiactiveprofile']['id'] . "'
                                 AND $condition");
            $first_found = array_shift($found);
            if ($first_found['right'] == NULL || $first_found['right'] == 0) {
               return false;
            }
         }
      }

      return $id;
   }

   /**
    * Post item hook for add
    * Do store data in db
    *
    * @param CommonDBTM $item Item instance
    *
    * @return CommonDBTM|true
    */
   static function postItemAdd(CommonDBTM $item) {
      if (property_exists($item, 'plugin_fields_data')) {
         $data = $item->plugin_fields_data;
         $data['items_id'] = $item->getID();
         //update data
         $container = new self();
         if ($container->updateFieldsValues($data, $item->getType(), isset($_REQUEST['massiveaction']))) {
            return true;
         }
         return $item->input = array();
      }
   }

   /**
    * Pre item hook for update
    * Do store data in db
    *
    * @param CommonDBTM $item Item instance
    *
    * @return boolean
    */
   static function preItemUpdate(CommonDBTM $item) {
      self::preItem($item);
      if (property_exists($item, 'plugin_fields_data')) {
         $data = $item->plugin_fields_data;
         //update data
         $container = new self();
         if (count($data) == 0 || $container->updateFieldsValues($data, $item->getType(), isset($_REQUEST['massiveaction']))) {
            return true;
         }
         return $item->input = array();
      }
   }


   /**
    * Pre item hook for add and update
    * Validates and store plugin data in item object
    *
    * @param CommonDBTM $item Item instance
    *
    * @return boolean
    */
   static function preItem(CommonDBTM $item) {
      //find container (if not exist, do nothing)
      if (isset($_REQUEST['c_id'])) {
         $c_id = $_REQUEST['c_id'];
      } else {
         $type = 'dom';
         if (isset($_REQUEST['_plugin_fields_type'])) {
            $type = $_REQUEST['_plugin_fields_type'];
         }
         $subtype = '';
         if ($type == 'domtab') {
            $subtype = $_REQUEST['_plugin_fields_subtype'];
         }
         $c_id = self::findContainer(get_Class($item), $type, $subtype);
         if ($c_id === false) {
            $c_id = self::findContainer(get_Class($item)); //tries for 'tab'
            if ($c_id === false) {
               return false;
            }
         }
      }

      //need to check if container is usable on this object entity
      $loc_c = new PluginFieldsContainer;
      $loc_c->getFromDB($c_id);
      $entities = array($loc_c->fields['entities_id']);
      if ($loc_c->fields['is_recursive']) {
         $entities = getSonsOf(getTableForItemType('Entity'), $loc_c->fields['entities_id']);
      }

      //workaround: when a ticket is created from readdonly profile,
      //it is not initialized; see https://github.com/glpi-project/glpi/issues/1438
      if (!isset($item->fields) || count($item->fields) == 0) {
         $item->fields = $item->input;
      }

      $current_entity = ($item::getType() == Entity::getType() ? $item->getID() : $item->fields['entities_id']);
      if (!in_array($current_entity, $entities)) {
         return false;
      }

      $data = self::populateData($c_id, $item);
      if ($data !== false) {
         if (self::validateValues($data, $item::getType(), isset($_REQUEST['massiveaction'])) === false) {
            return $item->input = [];
         }
         return $item->plugin_fields_data = $data;
      }

      return;
   }

   /**
    * Populates fields data from item
    *
    * @param integer    $c_id Container ID
    * @param CommonDBTM $item Item instance
    *
    * @return array|false
    */
   static private function populateData($c_id, CommonDBTM $item) {
      //find fields associated to found container
      $field_obj = new PluginFieldsField();
      $fields = $field_obj->find("plugin_fields_containers_id = $c_id AND type != 'header'", "ranking");

      //prepare data to update
      $data = ['plugin_fields_containers_id' => $c_id];
      if (!$item->isNewItem()) {
         //no ID yet while creating
         $data['items_id'] = $item->getID();
      }

      $has_fields = false;
      foreach ($fields as $field) {
         if (isset($item->input[$field['name']])) {
            //standard field
            $input = $field['name'];
         } else {
            //dropdown field
            $input = "plugin_fields_".$field['name']."dropdowns_id";
         }
         if (isset($item->input[$input])) {
            $has_fields = true;
            // Before is_number check, help user to have a number correct, during a massive action of a number field
            if ($field['type'] == 'number') {
               $item->input[$input] = str_replace(",", ".", $item->input[$input]);
            }
            $data[$input] = $item->input[$input];
         }
      }

      if ($has_fields === true) {
         return $data;
      } else {
         return false;
      }
   }

   static function getAddSearchOptions($itemtype, $containers_id = false) {
      global $DB;

      $opt = array();

      $i = 76665;

      $query = "SELECT fields.name, fields.label, fields.type, fields.is_readonly,
            containers.name as container_name, containers.label as container_label,
            containers.itemtypes, containers.id as container_id, fields.id as field_id
         FROM glpi_plugin_fields_containers containers
         INNER JOIN glpi_plugin_fields_fields fields
            ON containers.id = fields.plugin_fields_containers_id
            AND containers.is_active = 1
         WHERE containers.itemtypes LIKE '%$itemtype%'
            AND fields.type != 'header'
            ORDER BY fields.id ASC";
      $res = $DB->query($query);
      while ($data = $DB->fetch_assoc($res)) {

         if ($containers_id !== false) {
            // Filter by container (don't filter by SQL for have $i value with few containers for a itemtype)
            if ($data['container_id'] != $containers_id) {
               $i++;
               continue;
            }
         }

         $tablename = "glpi_plugin_fields_".strtolower($itemtype.
                        getPlural(preg_replace('/s$/', '', $data['container_name'])));

         //get translations
         $container = [
            'itemtype' => PluginFieldsContainer::getType(),
            'id'       => $data['container_id'],
            'label'    => $data['container_label']
         ];
         $data['container_label'] = PluginFieldsLabelTranslation::getLabelFor($container);

         $field = [
            'itemtype' => PluginFieldsField::getType(),
            'id'       => $data['field_id'],
            'label'    => $data['label']
         ];
         $data['label'] = PluginFieldsLabelTranslation::getLabelFor($field);

         $opt[$i]['table']         = $tablename;
         $opt[$i]['field']         = $data['name'];
         $opt[$i]['name']          = $data['container_label']." - ".$data['label'];
         $opt[$i]['linkfield']     = $data['name'];
         $opt[$i]['joinparams']['jointype'] = "itemtype_item";
         $opt[$i]['pfields_type']  = $data['type'];
         if ($data['is_readonly']) {
             $opt[$i]['massiveaction'] = false;
         }

         if ($data['type'] === "dropdown") {
            $opt[$i]['table']      = 'glpi_plugin_fields_'.$data['name'].'dropdowns';
            $opt[$i]['field']      = 'completename';
            $opt[$i]['linkfield']  = "plugin_fields_".$data['name']."dropdowns_id";

            $opt[$i]['forcegroupby'] = true;

            $opt[$i]['joinparams']['jointype'] = "";
            $opt[$i]['joinparams']['beforejoin']['table'] = $tablename;
            $opt[$i]['joinparams']['beforejoin']['joinparams']['jointype'] = "itemtype_item";
         }

         if ($data['type'] === "dropdownuser") {
            $opt[$i]['table']      = 'glpi_users';
            $opt[$i]['field']      = 'name';
            $opt[$i]['linkfield']  = $data['name'];
            $opt[$i]['right'] = 'all';

            $opt[$i]['forcegroupby'] = true;

            $opt[$i]['joinparams']['jointype'] = "";
            $opt[$i]['joinparams']['beforejoin']['table'] = $tablename;
            $opt[$i]['joinparams']['beforejoin']['joinparams']['jointype'] = "itemtype_item";
         }

         switch ($data['type']) {
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
               $opt[$i]['datatype'] = $data['type'];
               break;
            default:
               $opt[$i]['datatype'] = "string";
         }

         $i++;
      }

      return $opt;
   }

   /**
    * Get subtypes for specified itemtype.
    * Was previously retrieved using $item::defineTabs() but
    * this is not relevant with actual core.
    *
    * @param CommonDBTM $item Item instance
    *
    * @return array
    */
   private static function getSubtypes($item) {
      $tabs = [];
      switch ($item::getType()) {
         case Computer::getType():
            $tabs = [
               'Computer$1' => __('Operating system')
            ];
            break;
         case Ticket::getType():
         case Problem::getType():
            $tabs = [
               $item::getType() . '$2' => __('Solution')
            ];
            break;
         case Change::getType():
            $tabs = [
               'Change$1' => __('Analysis'),
               'Change$2' => __('Solution'),
               'Change$3' => __('Plans')
            ];
            break;
         case Entity::getType():
            $tabs = [
               'Entity$2' => __('Address'),
               'Entity$3' => __('Advanced information'),
               'Entity$4' => __('Notifications'),
               'Entity$5' => __('Assistance'),
               'Entity$6' => __('Assets')
            ];
            break;
         default:
            Toolbox::logDebug('Item type ' . $item::getType() . ' does not have any preconfigured subtypes!');
            /* For debug purposes
            $tabs = $item->defineTabs();
            list($id, ) = each($tabs);
            // delete first element of array ($main)
            unset($tabs[$id]);*/
            break;
      }

      return $tabs;
   }
}
