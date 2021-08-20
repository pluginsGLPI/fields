<?php

class PluginFieldsContainer extends CommonDBTM {
   static $rightname = 'config';

   static function canCreate() {
      return self::canUpdate();
   }

   static function canPurge() {
      return self::canUpdate();
   }

   static function titleList() {
      echo "<div class='center'><a class='vsubmit' href='regenerate_files.php'><i class='pointer fa fa-refresh'></i>&nbsp;".
            __("Regenerate container files", "fields")."</a>&nbsp;&nbsp;<a class='vsubmit' href='export_to_yaml.php'><i class='pointer fa fa-refresh'></i>&nbsp;".
            __("Export to YAML", "fields")."</a></div><br>";

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

      $table = self::getTable();

      if (!$DB->tableExists($table)) {
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
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
         $DB->query($query) or die ($DB->error());
      }

      // multiple itemtype for one container
      if (!$DB->fieldExists($table, "itemtypes")) {
         $migration->changeField($table, 'itemtype', 'itemtypes', 'longtext');
         $migration->migrationOneTable($table);

         $query = "UPDATE `$table` SET `itemtypes` = CONCAT('[\"', `itemtypes`, '\"]')";
         $DB->query($query) or die ($DB->error());
      }

      //add display preferences for this class
      $d_pref = new DisplayPreference;
      $found  = $d_pref->find(['itemtype' => __CLASS__]);
      if (count($found) == 0) {
         for ($i = 2; $i <= 5; $i++) {
            $DB->query("REPLACE INTO glpi_displaypreferences VALUES
               (NULL, '".__CLASS__."', $i, ".($i-1).", 0)");
         }
      }

      if (!$DB->fieldExists($table, "subtype")) {
         $migration->addField($table, 'subtype', 'VARCHAR(255) DEFAULT NULL', ['after' => 'type']);
         $migration->migrationOneTable($table);
      }

      // Fix containers names that were generated prior to Fields 1.9.2.
      $glpi_version = preg_replace('/^((\d+\.?)+).*$/', '$1', GLPI_VERSION);
      $bad_named_containers = $DB->request(
         [
            'FROM' => self::getTable(),
            'WHERE' => [
               'name' => [
                  'REGEXP',
                  // Regex will be escaped by PDO in GLPI 10+, but has to be escaped for GLPI < 10
                  version_compare($glpi_version, '10.0', '>=') ? '\d+' : $DB->escape('\d+')
               ],
            ],
         ]
      );

      if ($bad_named_containers->count() > 0) {
         $migration->displayMessage(__("Fix container names", "fields"));

         $toolbox = new PluginFieldsToolbox();

         foreach ($bad_named_containers as $container) {
            $old_name = $container['name'];

            // Update container name
            $new_name = $toolbox->getSystemNameFromLabel($container['label']);
            foreach (json_decode($container['itemtypes']) as $itemtype) {
               while (strlen(getTableForItemType(self::getClassname($itemtype, $new_name))) > 64) {
                  // limit tables names to 64 chars (MySQL limit)
                  $new_name = substr($new_name, 0, -1);
               }
            }
            $container['name'] = $new_name;
            $container_obj = new PluginFieldsContainer();
            $container_obj->update(
               $container,
               false
            );

            // Rename container tables and itemtype if needed
            foreach (json_decode($container['itemtypes']) as $itemtype) {
               $migration->renameItemtype(
                  self::getClassname($itemtype, $old_name),
                  self::getClassname($itemtype, $new_name)
               );
            }
         }
      }

      //Computer OS tab is no longer part of computer object. Moving to main
      $ostab = self::findContainer(Computer::getType(), 'domtab', Computer::getType() . '$1');
      if ($ostab) {
         //check if we already have a container on Computer main tab
         $comptab = self::findContainer(Computer::getType(), 'dom');
         if ($comptab) {
            $oscontainer = new PluginFieldsContainer();
            $oscontainer->getFromDB($ostab);

            $compcontainer = new PluginFieldsContainer();
            $compcontainer->getFromDB($comptab);

            $fields = new PluginFieldsField();
            $fields = $fields->find(['plugin_fields_containers_id' => $ostab]);

            $classname = self::getClassname(Computer::getType(), $oscontainer->fields['name']);
            $osdata = new $classname;
            $classname = self::getClassname(Computer::getType(), $compcontainer->fields['name']);
            $compdata = new $classname;

            $fieldnames = [];
            //add fields to compcontainer
            foreach ($fields as $field) {
               $newname = $field['name'];
               $compfields = $fields->find(['plugin_fields_containers_id' => $comptab, 'name' => $newname]);
               if ($compfields) {
                  $newname = $newname . '_os';
                  $DB->query("UPDATE glpi_plugin_fields_fields SET name='$newname' WHERE name='{$field['name']}' AND plugin_fields_containers_id='$ostab'");
               }
               $compdata::addField($newname, $field['type']);
               $fieldnames[$field['name']] = $newname;
            }

            $sql = "UPDATE glpi_plugin_fields_fields SET plugin_fields_containers_id='$comptab' WHERE plugin_fields_containers_id='$ostab'";
            $DB->query($sql);
            $DB->query("DELETE FROM glpi_plugin_fields_containers WHERE id='$ostab'");

            //migrate existing data
            $existings = $osdata->find();
            foreach ($existings as $existing) {
               $data = [];
               foreach ($fieldnames as $oldname => $newname) {
                  $data[$newname] = $existing[$oldname];
               }
               $compdata->add($data);
            }

            //drop old table
            $DB->query("DROP TABLE " . $osdata::getTable());
         } else {
            $sql = "UPDATE glpi_plugin_fields_containers SET type='dom', subtype=NULL WHERE id='$ostab'";
            $comptab = $ostab;
            $DB->query($sql);
         }
      }

      $migration->displayMessage(__("Updating generated containers files", "fields"));
      // -> 0.90-1.3: generated class moved
      // OLD path: GLPI_ROOT."/plugins/fields/inc/$class_filename"
      // NEW path: PLUGINFIELDS_CLASS_PATH . "/$class_filename"
      $obj        = new self;
      $containers = $obj->find();
      foreach ($containers as $container) {
         //First, drop old fields from plugin directories
         $itemtypes = !empty($container['itemtypes'])
            ? json_decode($container['itemtypes'], true)
            : [];

         foreach ($itemtypes as $itemtype) {
            $sysname = self::getSystemName($itemtype, $container['name']);
            $class_filename = $sysname.".class.php";
            if (file_exists(PLUGINFIELDS_DIR."/inc/$class_filename")) {
               unlink(PLUGINFIELDS_DIR."/inc/$class_filename");
            }

            $injclass_filename = $sysname."injection.class.php";
            if (file_exists(PLUGINFIELDS_DIR."/inc/$injclass_filename")) {
               unlink(PLUGINFIELDS_DIR."/inc/$injclass_filename");
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
         $obj->delete(['id' => $containers_id]);
      }

      //drop global container table
      $DB->query("DROP TABLE IF EXISTS `".self::getTable()."`");

      //delete display preferences for this item
      $pref = new DisplayPreference;
      $pref->deleteByCriteria([
         'itemtype' => __CLASS__
      ]);

      return true;
   }

   function post_getEmpty() {
      $this->fields['is_active']    = 1;
      $this->fields['is_recursive'] = 1;
   }

   function rawSearchOptions() {
      $tab = [];

      $tab[] = [
         'id'            => 1,
         'table'         => self::getTable(),
         'field'         => 'name',
         'name'          => __("Name"),
         'datatype'      => 'itemlink',
         'itemlink_type' => self::getType(),
         'massiveaction' => false,
      ];

      $tab[] = [
         'id'            => 2,
         'table'         => self::getTable(),
         'field'         => 'label',
         'name'          => __("Label"),
         'massiveaction' => false,
         'autocomplete'  => true,
      ];

      $tab[] = [
         'id'            => 3,
         'table'         => self::getTable(),
         'field'         => 'itemtypes',
         'name'          => __("Associated item type"),
         'datatype'      => 'specific',
         'massiveaction' => false,
         'nosearch'      => true,
      ];

      $tab[] = [
         'id'            => 4,
         'table'         => self::getTable(),
         'field'         => 'type',
         'name'          => __("Type"),
         'searchtype'    => ['equals', 'notequals'],
         'massiveaction' => false,
      ];

      $tab[] = [
         'id'            => 5,
         'table'         => self::getTable(),
         'field'         => 'is_active',
         'name'          => __("Active"),
         'datatype'      => 'bool',
         'searchtype'    => ['equals', 'notequals'],
      ];

      $tab[] = [
         'id'            => 6,
         'table'         => 'glpi_entities',
         'field'         => 'completename',
         'name'          => __("Entity"),
         'massiveaction' => false,
         'datatype'      => 'dropdown',
      ];

      $tab[] = [
         'id'            => 7,
         'table'         => self::getTable(),
         'field'         => 'is_recursive',
         'name'          => __("Child entities"),
         'massiveaction' => false,
         'datatype'      => 'bool',
      ];

      $tab[] = [
         'id'            => 8,
         'table'         => self::getTable(),
         'field'         => 'id',
         'name'          => __("ID"),
         'datatype'      => 'number',
         'massiveaction' => false,
      ];

      return $tab;
   }

   static function getSpecificValueToDisplay($field, $values, array $options = []) {
      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case 'type':
            $types = self::getTypes();
            return $types[$values[$field]];
         case 'itemtypes' :
            $types = json_decode($values[$field]);
            $obj   = '';
            $count = count($types);
            $i     = 1;
            foreach ($types as $type) {
               // prevent usage of plugin class if not loaded
               if (!class_exists($type)) {
                  continue;
               }
               $name_type = getItemForItemtype($type);
               $obj .= $name_type->getTypeName(2);
               if ($count > $i) {
                  $obj .= ", ";
               }
               $i++;
            }
            return $obj;
      }
   }


   function getValueToSelect($field_id_or_search_options, $name = '', $values = '', $options = []) {

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

   function defineTabs($options = []) {
      $ong = [];
      $this->addDefaultFormTab($ong);
      $this->addStandardTab('PluginFieldsField', $ong, $options);
      $this->addStandardTab('PluginFieldsProfile', $ong, $options);
      $this->addStandardTab('PluginFieldsLabelTranslation', $ong, $options);

      return $ong;
   }

   function prepareInputForAdd($input) {
      if (!isset($input['itemtypes'])) {
         Session::AddMessageAfterRedirect(__("You cannot add block without associated element type",
                                             "fields"),
                                          false, ERROR);
         return false;
      }

      if (!is_array($input['itemtypes'])) {
         $input['itemtypes'] = [$input['itemtypes']];
      }

      if ($input['type'] === "dom") {
         //check for already exist dom container with this itemtype
         $found = $this->find(['type' => 'dom']);
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
         $found = $this->find(['type' => 'domtab', 'subtype' => $input['subtype']]);
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

      $toolbox = new PluginFieldsToolbox();
      $input['name'] = $toolbox->getSystemNameFromLabel($input['label']);

      //reject adding when container name is too long for mysql table name
      foreach ($input['itemtypes'] as $itemtype) {
         $tmp = getTableForItemType(self::getClassname($itemtype, $input['name']));
         if (strlen($tmp) > 64) {
            Session::AddMessageAfterRedirect(
               __("Container name is too long for database (digits in name are replaced by characters, try to remove them)", 'fields'),
               false,
               ERROR
            );
            return false;
         }
      }

      //check for already existing container with same name
      $found = $this->find(['name' => $input['name']]);
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

      $input['itemtypes'] = isset($input['itemtypes'])
                              ? json_encode($input['itemtypes'], true)
                              : null;

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
         $classname = self::getClassname($itemtype, $this->fields['name']);
         $classname::install();
      }
   }

   public static function generateTemplate($fields) {
      $itemtypes = strlen($fields['itemtypes']) > 0
                     ? json_decode($fields['itemtypes'], true)
                     : [];
      foreach ($itemtypes as $itemtype) {
         // prevent usage of plugin class if not loaded
         if (!class_exists($itemtype)) {
            continue;
         }

         $sysname   = self::getSystemName($itemtype, $fields['name']);
         $classname = self::getClassname($itemtype, $fields['name']);

         $template_class = file_get_contents(PLUGINFIELDS_DIR."/templates/container.class.tpl");
         $template_class = str_replace("%%CLASSNAME%%", $classname, $template_class);
         $template_class = str_replace("%%ITEMTYPE%%", $itemtype, $template_class);
         $template_class = str_replace("%%CONTAINER%%", $fields['id'], $template_class);
         $template_class = str_replace("%%ITEMTYPE_RIGHT%%", $itemtype::$rightname, $template_class);
         $class_filename = $sysname.".class.php";
         if (file_put_contents(PLUGINFIELDS_CLASS_PATH . "/$class_filename", $template_class) === false) {
            Toolbox::logDebug("Error : class file creation - $class_filename");
            return false;
         }

         // Generate Datainjection files
         $template_class = file_get_contents(PLUGINFIELDS_DIR."/templates/injection.class.tpl");
         $template_class = str_replace("%%CLASSNAME%%", $classname, $template_class);
         $template_class = str_replace("%%ITEMTYPE%%", $itemtype, $template_class);
         $template_class = str_replace("%%CONTAINER_ID%%", $fields['id'], $template_class);
         $template_class = str_replace("%%CONTAINER_NAME%%", $fields['label'], $template_class);
         $class_filename = $sysname."injection.class.php";
         if (file_put_contents(PLUGINFIELDS_CLASS_PATH . "/$class_filename", $template_class) === false) {
            Toolbox::logDebug("Error : datainjection class file creation - $class_filename");
            return false;
         }
      }
      return true;
   }

   function pre_deleteItem() {
      global $DB;

      $_SESSION['delete_container'] = true;

      foreach (json_decode($this->fields['itemtypes']) as $itemtype) {
         $classname          = self::getClassname($itemtype, $this->fields['name']);
         $sysname          = self::getSystemName($itemtype, $this->fields['name']);
         $class_filename     = $sysname.".class.php";
         $injection_filename = $sysname."injection.class.php";

         //delete fields
         $field_obj = new PluginFieldsField;
         $field_obj->deleteByCriteria([
            'plugin_fields_containers_id' => $this->fields['id']
         ]);

         //delete profiles
         $profile_obj = new PluginFieldsProfile;
         $profile_obj->deleteByCriteria([
            'plugin_fields_containers_id' => $this->fields['id']
         ]);

         //delete label translations
         $translation_obj = new PluginFieldsLabelTranslation();
         $translation_obj->deleteByCriteria([
            'plugin_fields_itemtype' => self::getType(),
            'plugin_fields_items_id' => $this->fields['id']
         ]);

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
            $fields->deleteByCriteria(['items_id' => $item->fields['id']], true);
         }
      }
      return true;
   }

   static function getTypeName($nb = 0) {
      return __("Block", "fields");
   }

   public function showForm($ID, $options = []) {
      global $CFG_GLPI;

      echo "<div class='center'><a class='vsubmit' href='export_to_yaml.php?id=".$ID."'><i class='pointer fa fa-refresh'></i>&nbsp;".
      __("Export to YAML", "fields")."</a></div><br>";

      $this->initForm($ID, $options);
      $this->showFormHeader($options);
      $rand = mt_rand();

      echo "<tr>";
      echo "<td width='20%'>".__("Label")." : </td>";
      echo "<td width='30%'>";
      Html::autocompletionTextField($this, 'label', ['value' => $this->fields["label"]]);
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
                                 ['value' => $this->fields["type"],
                                  'rand'  => $rand]);
         Ajax::updateItemOnSelectEvent("dropdown_type$rand",
                                       "itemtypes_$rand",
                                       "../ajax/container_itemtypes_dropdown.php",
                                       ['type'     => '__VALUE__',
                                        'itemtype' => $this->fields["itemtypes"],
                                        'subtype'  => $this->fields['subtype'],
                                        'rand'     => $rand]);
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
            // prevent usage of plugin class if not loaded
            if (!class_exists($type)) {
               continue;
            }

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
         self::showFormItemtype(['rand'    => $rand,
                                 'subtype' => $this->fields['subtype']]);
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

   static function showFormItemtype($params = []) {
      global $CFG_GLPI;

      $is_domtab = isset($params['type']) && $params['type'] == 'domtab';

      $rand = $params['rand'];
      Dropdown::showFromArray("itemtypes", self::getItemtypes($is_domtab),
                              ['rand'                => $rand,
                               'multiple'            => !$is_domtab,
                               'width'               => 200,
                               'display_emptychoice' => $is_domtab]);

      if ($is_domtab) {
         Ajax::updateItemOnSelectEvent(["dropdown_type$rand", "dropdown_itemtypes$rand"],
                                       "subtype_$rand",
                                       "../ajax/container_subtype_dropdown.php",
                                       ['type'     => '__VALUE0__',
                                        'itemtype' => '__VALUE1__',
                                        'subtype'  => $params["subtype"],
                                        'rand'     => $rand]);
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
               $tabs_to_remove = ['Log$1', 'Document_Item$1'];
               foreach ($tabs_to_remove as $tab_to_remove) {
                  if (isset($tabs[$tab_to_remove])) {
                     unset($tabs[$tab_to_remove]);
                  }
               }

               // For delete <sup class='tab_nb'>number</sup> :
               foreach ($tabs as $key => &$value) {
                  $results = [];
                  if (preg_match_all('#<sup.*>(.+)</sup>#', $value, $results)) {
                     $value = str_replace($results[0][0], "", $value);
                  }
               }

               if (!isset($params['subtype'])) {
                  $params['subtype'] = null;
               }

               $out .= Dropdown::showFromArray('subtype', $tabs,
                                               ['value'   => $params['subtype'],
                                                'width'   => '100%',
                                                'display' => false]);
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

      $tabs[__('Assets')] = [
         'Computer'           => Computer::getTypeName(2),
         'Monitor'            => Monitor::getTypeName(2),
         'Software'           => Software::getTypeName(2),
         'NetworkEquipment'   => NetworkEquipment::getTypeName(2),
         'Peripheral'         => Peripheral::getTypeName(2),
         'Printer'            => Printer::getTypeName(2),
         'CartridgeItem'      => CartridgeItem::getTypeName(2),
         'ConsumableItem'     => ConsumableItem::getTypeName(2),
         'Phone'              => Phone::getTypeName(2),
         'Rack'               => Rack::getTypeName(2),
         'Enclosure'          => Enclosure::getTypeName(2),
         'PDU'                => PDU::getTypeName(2),
         'PassiveDCEquipment' => PassiveDCEquipment::getTypeName(2),
      ];

      $tabs[__('Assistance')] = [
         'Ticket'          => Ticket::getTypeName(2),
         'Problem'         => Problem::getTypeName(2),
         'Change'          => Change::getTypeName(2),
         'TicketRecurrent' => TicketRecurrent::getTypeName(2),
      ];

      $tabs[__('Management')] = [
         'SoftwareLicense' => SoftwareLicense::getTypeName(2),
         'Budget'          => Budget::getTypeName(2),
         'Supplier'        => Supplier::getTypeName(2),
         'Contact'         => Contact::getTypeName(2),
         'Contract'        => Contract::getTypeName(2),
         'Document'        => Document::getTypeName(2),
         'Line'            => Line::getTypeName(2),
         'Certificate'     => Certificate::getTypeName(2),
         'Datacenter'      => Datacenter::getTypeName(2),
         'Cluster'         => Cluster::getTypeName(2),
         'Domain'          => Domain::getTypeName(2),
         'Appliance'       => Appliance::getTypeName(2),
      ];

      $tabs[__('Tools')] = [
         'Project'     => Project::getTypeName(2),
         'ProjectTask' => ProjectTask::getTypeName(2),
         'Reminder'    => Reminder::getTypeName(2),
         'RSSFeed'     => RSSFeed::getTypeName(2),
      ];

      $tabs[__('Administration')] = [
         'User'    => User::getTypeName(2),
         'Group'   => Group::getTypeName(2),
         'Entity'  => Entity::getTypeName(2),
         'Profile' => Profile::getTypeName(2)
      ];

      foreach ($PLUGIN_HOOKS['plugin_fields'] as $itemtype) {
         $isPlugin = isPluginItemType($itemtype);
         if ($isPlugin) {
            $plugin_name = Plugin::getInfo($isPlugin['plugin'], 'name');

            $tabs[__("Plugins")][$itemtype] = $plugin_name.' - '.$itemtype::getTypeName(2);
         }
      }

      $dropdowns = [];
      // flatten dropdows
      $raw_dropdowns = Dropdown::getStandardDropdownItemTypes();
      array_walk_recursive($raw_dropdowns, function($val, $key) use (&$dropdowns) {
         $dropdowns[$key] = $val;
      });
      $tabs[__('Dropdowns')] = $dropdowns;

      $tabs[__('Other')] = [
         'NetworkPort'          => NetworkPort::getTypeName(2),
         'Notification'         => Notification::getTypeName(2),
         'NotificationTemplate' => NotificationTemplate::getTypeName(2),
      ];

      if ($is_domtab) {
         // Filter items that do not have tab handled
         foreach ($tabs as $group => $items) {
            $tabs[$group] = array_filter(
               $items,
               function ($item) {
                  return count(self::getSubtypes($item)) > 0;
               },
               ARRAY_FILTER_USE_KEY
            );
         }

         // Filter groupts that do not have items handled
         $tabs = array_filter(
            $tabs,
            function ($items) {
               return count($items) > 0;
            }
         );
      }

      return $tabs;
   }

   static function getTypes() {
      return [
         'tab'    => __("Add tab", "fields"),
         'dom'    => __("Insertion in the form (before save button)", "fields"),
         'domtab' => __("Insertion in the form of a specific tab (before save button)", "fields")
      ];
   }

   static function getEntries($type = 'tab', $full = false) {
      global $DB;

      $condition = [
         'is_active' => 1,
      ];
      if ($type !== "all") {
         $condition[] = ['type' => $type];
      }

      if (!$DB->tableExists(self::getTable())) {
         return false;
      }

      $itemtypes = [];
      $container = new self;
      $profile   = new PluginFieldsProfile;
      $found     = $container->find($condition, 'label');
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
         $found = $profile->find(['profiles_id' => $_SESSION['glpiactiveprofile']['id'],
                                  'plugin_fields_containers_id' => $item['id'],
                                  'right' => ['>=', READ]]);
         $first_found = array_shift($found);
         if (!$first_found || $first_found['right'] == null || $first_found['right'] == 0) {
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
      $itemtypes = [];
      $where = $type == 'all'
                  ? '1=1'
                  : 'type = "'.$type.'"';
      if ($must_be_active) {
         $where .= ' AND is_active = 1';
      }

      $query = 'SELECT DISTINCT `itemtypes`
                FROM `glpi_plugin_fields_containers`
                WHERE '.$where;
      $result = $DB->query($query);
      while (list($data) = $DB->fetchArray($result)) {
         $jsonitemtype = json_decode($data);
         $itemtypes    = array_merge($itemtypes, $jsonitemtype);
      }

      return $itemtypes;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      $itemtypes = self::getEntries('tab', true);
      if (isset($itemtypes[$item->getType()])) {
         $tabs_entries = [];
         $container    = new self;
         foreach ($itemtypes[$item->getType()] as $tab_name => $tab_label) {
            // needs to check if entity of item is in hierachy of $tab_name
            foreach ($container->find(['is_active' => 1, 'name' => $tab_name]) as $data) {
               $dataitemtypes = json_decode($data['itemtypes']);
               if (in_array(get_class($item), $dataitemtypes) != false) {
                  $entities = [$data['entities_id']];
                  if ($data['is_recursive']) {
                     $entities = getSonsOf(getTableForItemType('Entity'), $data['entities_id']);
                  }

                  if (!$item->isEntityAssign() || in_array($item->fields['entities_id'], $entities)) {
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
      $found_c   = $container->find(['type' => 'tab', 'name' => $tabnum, 'is_active' => 1]);
      foreach ($found_c as $data) {
         $dataitemtypes = json_decode($data['itemtypes']);
         if (in_array(get_class($item), $dataitemtypes) != false) {
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

      $items_id  = $data['items_id'];
      $classname = self::getClassname($itemtype, $container_obj->fields['name']);

      //check if data already inserted
      $obj   = new $classname;
      $found = $obj->find(['items_id' => $items_id]);
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
                                    $old_values = []) {
      // Don't log few itemtypes
      $obj = new $itemtype();
      if ($obj->dohistory == false) {
         return;
      }

      //get searchoptions
      $searchoptions = self::getAddSearchOptions($itemtype, $containers_id);

      //define non-data keys
      $blacklist_k = [
         'plugin_fields_containers_id' => 0,
         'items_id'                    => 0,
         'itemtype'                    => $itemtype,
         'update_fields_values'        => 0,
         '_glpi_csrf_token'            => 0
      ];

      //remove non-data keys
      $data = array_diff_key($data, $blacklist_k);

      //add/update values condition
      if (empty($old_values)) {
         // -- add new item --

         foreach ($data as $key => $value) {
            //log only not empty values
            if (!empty($value)) {
               //prepare log
               $changes = [0, "N/A", $value];

               //find searchoption
               foreach ($searchoptions as $id_search_option => $searchoption) {
                  if ($searchoption['linkfield'] == $key) {
                     $changes[0] = $id_search_option;

                     //manage dropdown values
                     if ($searchoption['datatype'] === 'dropdown') {
                        $changes = [$id_search_option,
                                    "",
                                    Dropdown::getDropdownName($searchoption['table'], $value)];
                     }

                     //manage bool dropdown values
                     if ($searchoption['datatype'] === 'bool') {
                        $changes = [$id_search_option, "", Dropdown::getYesNo($value)];
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
         $updates = [];
         foreach ($old_values as $key => $old_value) {
            if (!isset($data[$key])
                || empty($old_value) && empty($data[$key])
                || $old_value !== '' && $data[$key] == 'NULL'
                ) {
               continue;
            }

            if ($data[$key] !== $old_value) {
               $updates[$key] = [0, $old_value, $data[$key]];
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

      $valid         = true;
      $empty_errors  = [];
      $number_errors = [];

      $container = new self();
      $container->getFromDB($data['plugin_fields_containers_id']);

      $field_obj = new PluginFieldsField();
      $fields = $field_obj->find([
         'plugin_fields_containers_id' => $data['plugin_fields_containers_id']
      ]);

      foreach ($fields as $fields_id => $field) {
         if ($field['type'] == "yesno" || $field['type'] == "header") {
            continue;
         }

         $name  = $field['name'];
         if (isset($data[$name])) {
            $value = $data[$name];
         } else if (isset($data['plugin_fields_'.$name.'dropdowns_id'])) {
            $value = $data['plugin_fields_'.$name.'dropdowns_id'];
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
               $db_result = $DB->fetchAssoc($result);
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
         if ($field['mandatory'] == 1
             && ($value == ""
                 || in_array($field['type'], ['dropdown', 'dropdownuser', 'dropdownoperatingsystems'])
                 && $value == 0
                 || in_array($field['type'], ['date', 'datetime'])
                 && $value == 'NULL')) {
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
         Session::AddMessageAfterRedirect(__("Some mandatory fields are empty", "fields").
                                          " : ".implode(', ', $empty_errors), false, ERROR);
      }

      if (!empty($number_errors)) {
         Session::AddMessageAfterRedirect(__("Some numeric fields contains non numeric values", "fields").
                                          " : ".implode(', ', $number_errors), false, ERROR);
      }

      if (!empty($url_errors)) {
         Session::AddMessageAfterRedirect(__("Some URL fields contains invalid links", "fields").
                                          " : ".implode(', ', $url_errors), false, ERROR);
      }

      return $valid;
   }


   static function findContainer($itemtype, $type = 'tab', $subtype = '') {

      $condition = [
         'is_active' => 1,
         ['type' => $type],
      ];

      $entity = isset($_SESSION['glpiactiveentities'])
                  ? $_SESSION['glpiactiveentities']
                  : 0;
      $condition += getEntitiesRestrictCriteria("", "", $entity, true, true);

      if ($subtype != '') {
         if ($subtype == $itemtype.'$main') {
            $condition[] = ['type' => 'dom'];
         } else {
            $condition[] = ['type' => ['!=', 'dom']];
            $condition['subtype'] = $subtype;
         }
      }

      $container = new PluginFieldsContainer();
      $itemtypes = $container->find($condition);
      $id = 0;
      if (count($itemtypes) < 1) {
         return false;
      }

      foreach ($itemtypes as $data) {
         $dataitemtypes = json_decode($data['itemtypes']);
         if (in_array($itemtype, $dataitemtypes) != false) {
            $id = $data['id'];
         }
      }

      //profiles restriction
      if (isset($_SESSION['glpiactiveprofile']['id'])) {
         $profile = new PluginFieldsProfile();
         if (isset($id)) {
            $found = $profile->find(['profiles_id' => $_SESSION['glpiactiveprofile']['id'],
                                     'plugin_fields_containers_id' => $id]);
            $first_found = array_shift($found);
            if ($first_found === null || $first_found['right'] == null || $first_found['right'] == 0) {
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
         return $item->input = [];
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
         if (count($data) == 0
             || $container->updateFieldsValues($data, $item->getType(), isset($_REQUEST['massiveaction']))) {
            return true;
         }
         return $item->input = [];
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
         if (false === ($c_id = self::findContainer(get_Class($item), $type, $subtype))) {
            // tries for 'tab'
            if (false === ($c_id = self::findContainer(get_Class($item)))) {
               return false;
            }
         }
      }

      //need to check if container is usable on this object entity
      $loc_c = new PluginFieldsContainer;
      $loc_c->getFromDB($c_id);
      $entities = [$loc_c->fields['entities_id']];
      if ($loc_c->fields['is_recursive']) {
         $entities = getSonsOf(getTableForItemType('Entity'), $loc_c->fields['entities_id']);
      }

      //workaround: when a ticket is created from readdonly profile,
      //it is not initialized; see https://github.com/glpi-project/glpi/issues/1438
      if (!isset($item->fields) || count($item->fields) == 0) {
         $item->fields = $item->input;
      }

      $current_entity = $item::getType() == Entity::getType()
                           ? $item->getID()
                           : $item->fields['entities_id'];
      if ($item->isEntityAssign() && !in_array($current_entity, $entities)) {
         return false;
      }

      if (false !== ($data = self::populateData($c_id, $item))) {
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
      $fields = $field_obj->find(
         [
            'plugin_fields_containers_id' => $c_id,
            'type' => ['!=', 'header']
         ],
         "ranking"
      );

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

      $opt = [];

      $i = 76665;

      $query = "SELECT DISTINCT fields.id, fields.name, fields.label, fields.type, fields.is_readonly,
            containers.name as container_name, containers.label as container_label,
            containers.itemtypes, containers.id as container_id, fields.id as field_id
         FROM glpi_plugin_fields_containers containers
         INNER JOIN glpi_plugin_fields_profiles profiles
            ON containers.id = profiles.plugin_fields_containers_id
            AND profiles.right > 0
            AND profiles.profiles_id = ".(int) $_SESSION['glpiactiveprofile']['id']."
         INNER JOIN glpi_plugin_fields_fields fields
            ON containers.id = fields.plugin_fields_containers_id
            AND containers.is_active = 1
         WHERE containers.itemtypes LIKE '%$itemtype%'
            AND fields.type != 'header'
            ORDER BY fields.id ASC";
      $res = $DB->query($query);
      while ($data = $DB->fetchAssoc($res)) {

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

         if ($data['type'] === "dropdownoperatingsystems") {
            $opt[$i]['table']      = 'glpi_operatingsystems';
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
            case 'dropdownoperatingsystems':
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
            case 'url':
               $opt[$i]['datatype'] = 'weblink';
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
            //Toolbox::logDebug('Item type ' . $item::getType() . ' does not have any preconfigured subtypes!');
            /* For debug purposes
            $tabs = $item->defineTabs();
            list($id, ) = each($tabs);
            // delete first element of array ($main)
            unset($tabs[$id]);*/
            break;
      }

      return $tabs;
   }

   /**
    * Retrieve the classname for a label (raw_name) & an itemtype
    * @param  string $itemtype the name of associated CommonDBTM class
    * @param  string $raw_name the label of container
    * @return string the classname
    */
   static function getClassname($itemtype = "", $raw_name = "") {
      return "PluginFields".ucfirst(self::getSystemName($itemtype, $raw_name));
   }

   /**
    * Retrieve the systemname for a label (raw_name) & an itemtype
    * Used to generate class files
    * @param  string $itemtype the name of associated CommonDBTM class
    * @param  string $raw_name the label of container
    * @return string the classname
    */
   static function getSystemName($itemtype = "", $raw_name = "") {
      return strtolower($itemtype.preg_replace('/s$/', '', $raw_name));
   }


   static function getIcon() {
      return "fas fa-tasks";
   }
}
