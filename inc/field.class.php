<?php

class PluginFieldsField extends CommonDBTM {
   static $rightname = 'config';

   static function canCreate() {
      return self::canUpdate();
   }

   /**
    * Install or update fields
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
                  `id`                                INT(11)        NOT NULL auto_increment,
                  `name`                              VARCHAR(255)   DEFAULT NULL,
                  `label`                             VARCHAR(255)   DEFAULT NULL,
                  `type`                              VARCHAR(25)    DEFAULT NULL,
                  `plugin_fields_containers_id`       INT(11)        NOT NULL DEFAULT '0',
                  `ranking`                           INT(11)        NOT NULL DEFAULT '0',
                  `default_value`                     VARCHAR(255)   DEFAULT NULL,
                  `is_active`                         TINYINT(1)     NOT NULL DEFAULT '1',
                  `is_readonly`                       TINYINT(1)     NOT NULL DEFAULT '1',
                  `mandatory`                         TINYINT(1)     NOT NULL DEFAULT '0',
                  PRIMARY KEY                         (`id`),
                  KEY `plugin_fields_containers_id`   (`plugin_fields_containers_id`),
                  KEY `is_active`                     (`is_active`),
                  KEY `is_readonly`                   (`is_readonly`)
               ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
            $DB->query($query) or die ($DB->error());
      }

      $migration->displayMessage("Updating $table");

      if (!FieldExists($table, 'is_active')) {
         $migration->addField($table, 'is_active', 'bool', array('value' => 1));
         $migration->addKey($table, 'is_active', 'is_active');
      }
      if (!FieldExists($table, 'is_readonly')) {
         $migration->addField( $table, 'is_readonly', 'bool', array('default' => false));
         $migration->addKey($table, 'is_readonly', 'is_readonly');
      }
      if (!FieldExists($table, 'mandatory')) {
         $migration->addField($table, 'mandatory', 'bool', array('value' => 0));
      }
      $migration->executeMigration();

      return true;
   }

   static function uninstall() {
      global $DB;

      $obj = new self();
      $DB->query("DROP TABLE IF EXISTS `".$obj->getTable()."`");

      return true;
   }

   static function getTypeName($nb = 0) {
      return __("Field", "fields");
   }


   function prepareInputForAdd($input) {
      global $DB;
      //parse name
      $input['name'] = $this->prepareName($input);

      //dropdowns : create files
      if ($input['type'] === "dropdown") {
         //search if dropdown already exist in this container
         $found = $this->find("name = '".$input['name']."'
                              AND plugin_fields_containers_id = '".
                                 $input['plugin_fields_containers_id']."'");

         //reject adding for same dropdown on same bloc
         if (!empty($found)) {
            Session::AddMessageAfterRedirect(__("You cannot add same field 'dropdown' on same bloc", 'fields', false, ERROR));
            return false;
         }

         //search if dropdown already exist in other container
         $found = $this->find("name = '".$input['name']."'");
         //for dropdown, if already exist, don't create files
         if (empty($found)) {
            PluginFieldsDropdown::create($input);
         }

         $oldname = $input['name'];
         $input['name'] = getForeignKeyFieldForItemType(
            PluginFieldsDropdown::getClassname($input['name']));
      }

      // Before adding, add the ranking of the new field
      if (empty($input["ranking"])) {
         $input["ranking"] = $this->getNextRanking();
      }

      //add field to container table
      if ($input['type'] !== "header") {
         $container_obj = new PluginFieldsContainer;
         $container_obj->getFromDB($input['plugin_fields_containers_id']);
         foreach (json_decode($container_obj->fields['itemtypes']) as $itemtype) {
            $classname = "PluginFields" . ucfirst(strtolower($itemtype .
                     preg_replace('/s$/', '', $container_obj->fields['name'])));
            $classname::addField($input['name'], $input['type']);
         }
      }

      if (isset($oldname)) {
         $input['name'] = $oldname;
      }

      return $input;
   }

   function pre_deleteItem() {
      global $DB;

      //remove field in container table
      if ($this->fields['type'] !== "header" && !isset($_SESSION['uninstall_fields'])
            && !isset($_SESSION['delete_container'])) {

         if ($this->fields['type'] === "dropdown") {
            $oldname = $this->fields['name'];
            $this->fields['name'] = getForeignKeyFieldForItemType(
               PluginFieldsDropdown::getClassname($this->fields['name']));
         }

         $container_obj = new PluginFieldsContainer;
         $container_obj->getFromDB($this->fields['plugin_fields_containers_id']);
         foreach (json_decode($container_obj->fields['itemtypes']) as $itemtype) {
            $classname = "PluginFields" . ucfirst(strtolower($itemtype .
                     preg_replace('/s$/', '', $container_obj->fields['name'])));
            $classname::removeField($this->fields['name']);
         }
         $classname::removeField($this->fields['name']);
      }

      //delete label translations
      $translation_obj = new PluginFieldsLabelTranslation();
      $translations = $translation_obj->find("plugin_fields_itemtype = '" . self::getType() .
                                             "' AND plugin_fields_items_id = ". $this->fields['id']);
      foreach ($translations as $translation_id => $translation) {
         $translation_obj->delete(['id' => $translation_id]);
      }

      if (isset($oldname)) {
         $this->fields['name'] = $oldname;
      }

      if ($this->fields['type'] === "dropdown") {
         return PluginFieldsDropdown::destroy($this->fields['name']);
      }
      return true;
   }

   function post_purgeItem() {
      global $DB;

      $table         = getTableForItemType(__CLASS__);
      $old_container = $this->fields['plugin_fields_containers_id'];
      $old_ranking   = $this->fields['ranking'];

      $query = "UPDATE $table SET
                ranking = ranking-1
                WHERE plugin_fields_containers_id = $old_container
                AND ranking > $old_ranking";
      $DB->query($query);

      return true;
   }


   /**
    * parse name for avoid non alphanumeric char in it and conflict with other fields
    * @param  array $input the field form input
    * @return string  the parsed name
    */
   function prepareName($input) {
      //contruct field name by processing label (remove non alphanumeric char)
      if (empty($input['name'])) {
         $input['name'] = strtolower(preg_replace("/[^\da-z]/i", "", $input['label']))."field";
      }

      //for dropdown, if already exist, link to it
      if (isset($input['type']) && $input['type'] === "dropdown") {
         $found = $this->find("name = '".$input['name']."'");
         if (!empty($found)) {
            return $input['name'];
         }
      }

      //check if field name not already exist and not in conflict with itemtype fields name
      $container = new PluginFieldsContainer;
      $container->getFromDB($input['plugin_fields_containers_id']);

      $field  = new self;

      $field_name = $input['name'];
      $i = 2;
      while (count($field->find("name = '$field_name'")) > 0) {
         $field_name = $input['name'].$i;
         $i++;
      }

      return $field_name;
   }

   /**
    * Get the next ranking for a specified field
    *
    * @return integer
   **/
   function getNextRanking() {
      global $DB;

      $sql = "SELECT max(`ranking`) AS rank
              FROM `".$this->getTable()."`
              WHERE `plugin_fields_containers_id` = '".
                  $this->fields['plugin_fields_containers_id']."'";
      $result = $DB->query($sql);

      if ($DB->numrows($result) > 0) {
         $data = $DB->fetch_assoc($result);
         return $data["rank"] + 1;
      }
      return 0;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      if (!$withtemplate) {
         $nb = 0;
         switch ($item->getType()) {
            case __CLASS__ :
               $ong[1] = $this->getTypeName(1);
               return $ong;
         }
      }

      return self::createTabEntry(__("Fields", "fields"),
                   countElementsInTable($this->getTable(),
                                        "`plugin_fields_containers_id` = '".$item->getID()."'"));
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      $fup = new self();
      $fup->showSummary($item);
      return true;
   }

   function defineTabs($options=array()) {
      $ong = array();
      $this->addDefaultFormTab($ong);
      $this->addStandardTab('PluginFieldsLabelTranslation', $ong, $options);

      return $ong;
   }

   function showSummary($container) {
      global $DB, $CFG_GLPI;

      $cID = $container->fields['id'];

      // Display existing Fields
      $tmp    = array('plugin_fields_containers_id' => $cID);
      $canadd = $this->can(-1, CREATE, $tmp);

      $query  = "SELECT `id`, `label`
                FROM `".$this->getTable()."`
                WHERE `plugin_fields_containers_id` = '$cID'
                ORDER BY `ranking` ASC";
      $result = $DB->query($query);

      $rand   = mt_rand();

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
      echo __("Add a new field", "fields")."</a></div><br>\n";

      if ($DB->numrows($result) == 0) {
         echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
         echo "<th class='b'>".__("No field for this block", "fields")."</th></tr></table>";
      } else {
         echo '<div id="drag">';
         echo '<input type="hidden" name="_plugin_fields_containers_id"
                  id="plugin_fields_containers_id" value="' . $cID . '" />';
         echo "<table class='tab_cadre_fixehov'>";
         echo "<tr>";
         echo "<th>" . __("Label")               . "</th>";
         echo "<th>" . __("Type")                . "</th>";
         echo "<th>" . __("Default values")      . "</th>";
         echo "<th>" . __("Mandatory field")     . "</th>";
         echo "<th>" . __("Active")              . "</th>";
         echo "<th>" . __("Read only", "fields") . "</th>";
         echo "<th width='16'>&nbsp;</th>";
         echo "</tr>\n";

         $fields_type = self::getTypes();

         Session::initNavigateListItems('PluginFieldsField', __('Fields list'));

         while ($data = $DB->fetch_array($result)) {
            if ($this->getFromDB($data['id'])) {
               echo "<tr class='tab_bg_2' style='cursor:pointer'>";

               echo "<td>";
               echo "<a href='" . $CFG_GLPI["root_doc"] . "/plugins/fields/front/field.form.php?id={$this->getID()}'>{$this->fields['label']}</a>";
               echo "</td>";
               echo "<td>".$fields_type[$this->fields['type']]."</td>";
               echo "<td>".$this->fields['default_value']."</td>";
               echo "<td align='center'>".Dropdown::getYesNo($this->fields["mandatory"])."</td>";
               echo "<td align='center'>";
               echo ($this->fields['is_active'] == 1)
                     ? __('Yes')
                     : '<b class="red">' . __('No') . '</b>';
               echo "</td>";

               echo "<td>";
               echo Dropdown::getYesNo($this->fields["is_readonly"]);
               echo "</td>";

               echo '<td class="rowhandler control center">';
               echo '<div class="drag row" style="cursor:move;border:none !important;">';
               echo '<img src="../pics/drag.png" alt="#" title="' . __('Move') .'" width="16" height="16" />';
               echo '</div>';
               echo '</td>';
               echo "</tr>\n";
            }
         }
      }
      echo '</table>';
      echo '</div>';
      echo Html::scriptBlock('redipsInit()');
   }


   function showForm($ID, $options=array()) {
      if (isset($options['parent_id']) && !empty($options['parent_id'])) {
         $container = new PluginFieldsContainer;
         $container->getFromDB($options['parent_id']);
      } else if (isset($options['parent'])
                 && $options['parent'] instanceof CommonDBTM) {
         $container = $options['parent'];
      }

      if ($ID > 0) {
         $edit = true;
      } else {
         // Create item
         $edit = false;
         $_SESSION['saveInput'] = array('plugin_fields_containers_id' => $container->getField('id'));
      }

      $this->initForm($ID, $options);
      $this->showFormHeader($ID, $options);

      echo "<tr>";
      echo "<td>".__("Label")." : </td>";
      echo "<td>";
      echo "<input type='hidden' name='plugin_fields_containers_id' value='".
         $container->getField('id')."'>";
      Html::autocompletionTextField($this, 'label', array('value' => $this->fields["label"]));
      echo "</td>";

      if (!$edit) {
         echo "</tr>";
         echo "<tr>";
         echo "<td>".__("Type")." : </td>";
         echo "<td>";
         Dropdown::showFromArray('type', self::getTypes(),
            array('value' => $this->fields["type"]));
         echo "</td>";
      }
      echo "<td>".__("Default values")." : </td>";
      echo "<td>";
      Html::autocompletionTextField($this, 'default_value',
                                    array('value' => $this->fields["default_value"]));
      if ($this->fields["type"] == "dropdown") {
         echo '<a href="'.$GLOBALS['CFG_GLPI']['root_doc'].'/plugins/fields/front/commondropdown.php?ddtype=' . $this->fields['name'] .'dropdown">
                  <img src="'.$GLOBALS['CFG_GLPI']['root_doc'].'/pics/options_search.png" class="pointer"
                     alt="'.__('Configure', 'fields').'" title="'.__('Configure fields values', 'fields').'" /></a>';
      }
      echo "</td>";

      echo "</tr>";

      echo "<tr>";
      echo "<td>" . __('Active') . " :</td>";
      echo "<td>";
      Dropdown::showYesNo('is_active', $this->fields["is_active"]);
      echo "</td>";
      echo "<td>".__("Mandatory field")." : </td>";
      echo "<td>";
      Dropdown::showYesNo("mandatory", $this->fields["mandatory"]);
      echo "</td>";
      echo "</tr>";

      echo "<tr>";
      echo "<td>".__("Read only", "fields")." :</td>";
      echo "<td>";
      Dropdown::showYesNo("is_readonly", $this->fields["is_readonly"]);
      echo "</td>";
      echo "</tr>";

      $this->showFormButtons($options);

   }

   static function showForTabContainer($c_id, $items_id, $itemtype) {
      global $CFG_GLPI;

      //profile restriction (for reading profile)
      $profile = new PluginFieldsProfile;
      $found = $profile->find("`profiles_id` = '".$_SESSION['glpiactiveprofile']['id']."'
                                 AND `plugin_fields_containers_id` = '$c_id'");
      $first_found = array_shift($found);
      $canedit = ($first_found['right'] == CREATE);

      //get fields for this container
      $field_obj = new self();
      $fields = $field_obj->find("plugin_fields_containers_id = $c_id AND is_active = 1", "ranking");
      echo "<form method='POST' action='".$CFG_GLPI["root_doc"].
         "/plugins/fields/front/container.form.php'>";
      echo "<input type='hidden' name='plugin_fields_containers_id' value='$c_id'>";
      echo "<input type='hidden' name='items_id' value='$items_id'>";
      echo "<input type='hidden' name='itemtype' value='$itemtype'>";
      echo "<table class='tab_cadre_fixe'>";
      echo self::prepareHtmlFields($fields, $items_id, $itemtype, $canedit);

      if ($canedit) {
         echo "<tr><td class='tab_bg_2 center' colspan='4'>";
         echo "<input type='submit' name='update_fields_values' value=\"".
            _sx("button", "Save")."\" class='submit'>";
         echo "</td></tr>";
      }

      echo "</table>";
      Html::closeForm();

      return true;
   }

   /**
    * Display dom container
    *
    * @param integer $c_id     Container's ID
    * @param string  $itemtype Item type
    * @param integer $items_id Item ID
    * @param string  $type     Type (either 'dom' or 'domtab'
    * @param string  $subtype  Requested subtype (used for domtab only)
    *
    * @return void
    */
   static private function showDomContainer($c_id, $itemtype, $items_id, $type = "dom", $subtype = "") {
      if (is_array($c_id)) {
         $condition = "plugin_fields_containers_id IN (".implode(", ", $c_id).")";
      } else {
         $condition = "plugin_fields_containers_id = $c_id";
      }

      if ($c_id === false) {
         $condition = "1=0";
      }

      //get fields for this container
      $field_obj = new self();
      $fields = $field_obj->find($condition." AND is_active = 1", "ranking");

      echo "<input type='hidden' name='_plugin_fields_type' value='$type' />";
      echo "<input type='hidden' name='_plugin_fields_subtype' value='$subtype' />";
      echo self::prepareHtmlFields($fields, $items_id, $itemtype);
   }

   /**
    * Display field sin any existing tab
    *
    * @param array $params [item, options]
    *
    * @return void
    */
   static function showForTab($params) {
      global $CFG_GLPI;

      $item = $params['item'];
      $options = $params['options'];

      if (!isset($_SESSION['glpi_tabs'][strtolower($item::getType())])) {
         return;
      };

      $subtype = $_SESSION['glpi_tabs'][strtolower($item::getType())];
      $type = (substr($subtype, -strlen('$main')) === '$main' ? 'dom' : 'domtab');

      //find container (if not exist, do nothing)
      if (isset($_REQUEST['c_id'])) {
         $c_id = $_REQUEST['c_id'];
      } else {
         $c_id = PluginFieldsContainer::findContainer(get_Class($item), $type, $subtype);
         if ($c_id === false) {
            $c_id = PluginFieldsContainer::findContainer(get_Class($item)); //tries for 'tab'
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

      $current_entity = ($item::getType() == Entity::getType() ? $item->getID() : $item->fields['entities_id']);
      if (!in_array($current_entity, $entities)) {
         return false;
      }

      //parse REQUEST_URI
      if (!isset($_SERVER['REQUEST_URI'])) {
         return false;
      }
      $current_url = $_SERVER['REQUEST_URI'];
      if (strpos($current_url, ".form.php") === false
            && strpos($current_url, ".injector.php") === false
            && strpos($current_url, ".public.php") === false) {
         return false;
      }

      //Retrieve dom container
      $itemtypes = PluginFieldsContainer::getUsedItemtypes($type, true);

      //if no dom containers defined for this itemtype, do nothing (in_array case insensitive)
      if (!in_array(strtolower($item::getType()), array_map('strtolower', $itemtypes))) {
         return false;
      }

      self::showDomContainer(
         $c_id,
         $item::getType(),
         $item->getID(),
         $type,
         $subtype
      );
   }

   static function prepareHtmlFields($fields, $items_id, $itemtype, $canedit = true,
                                     $show_table = true, $massiveaction = false) {

      if (empty($fields)) {
         return false;
      }

      //get object associated with this fields
      $tmp = $fields;
      $first_field = array_shift($tmp);
      $container_obj = new PluginFieldsContainer;
      $container_obj->getFromDB($first_field['plugin_fields_containers_id']);
      $classname = "PluginFields".$itemtype.
                                 preg_replace('/s$/', '', $container_obj->fields['name']);
      $obj = new $classname;

      //find row for this object with the items_id
      $found_values = $obj->find("plugin_fields_containers_id = ".
                                 $first_field['plugin_fields_containers_id']." AND items_id = ".
                                 $items_id);
      $found_v = array_shift($found_values);

      // find profiles (to check if current profile can edit fields)
      $fprofile = new PluginFieldsProfile;
      $found_p = $fprofile->find("`profiles_id` = '".$_SESSION['glpiactiveprofile']['id']."'
                                  AND `plugin_fields_containers_id` = '".$first_field['plugin_fields_containers_id']."'");
      $first_found_p = array_shift($found_p);

      // test status for "CommonITILObject" objects
      if (is_subclass_of($itemtype, "CommonITILObject")) {
         $items_obj = new $itemtype();
         if ($items_id > 0) {
            $items_obj->getFromDB($items_id);
         } else {
            $items_obj->getEmpty();
         }

         if (in_array($items_obj->fields['status'], $items_obj->getClosedStatusArray())
               || in_array($items_obj->fields['status'], $items_obj->getSolvedStatusArray())
               || $first_found_p['right'] != CREATE) {
            $canedit = false;
         }
      } else {
         if ($first_found_p['right'] != CREATE) {
            $canedit = false;
         }
      }

      //show all fields
      $html = "";
      $odd = 0;
      foreach ($fields as $field) {

         if ($field['type'] === 'header') {
            $html.= "<tr class='tab_bg_2'>";
            $field['itemtype'] = self::getType();
            $txt_label = PluginFieldsLabelTranslation::getLabelFor($field);
            $html.= "<th colspan='4'>$txt_label</th>";
            $html.= "</tr>";
            $odd = 0;
         } else {
            //get value
            $value = "";
            if (is_array($found_v)) {
               if ($field['type'] == "dropdown") {
                  $value = $found_v["plugin_fields_".$field['name']."dropdowns_id"];
               } else {
                  $value = $found_v[$field['name']];
               }
            }

            if (! $field['is_readonly']) {
               if ($field['type'] == "dropdown") {
                  if (isset($_SESSION['plugin']['fields']['values_sent']["plugin_fields_".
                                                                        $field['name'].
                                                                        "dropdowns_id"])) {
                     $value = $_SESSION['plugin']['fields']['values_sent']["plugin_fields_".
                                                                           $field['name'].
                                                                           "dropdowns_id"];
                  }
               } else if (isset($_SESSION['plugin']['fields']['values_sent'][$field['name']])) {
                  $value = $_SESSION['plugin']['fields']['values_sent'][$field['name']];
               }
            }

            //get default value
            if (empty($value) && !empty($field['default_value'])) {
               $value = $field['default_value'];
            }

            //show field
            if ($show_table) {
               if ($odd%2 == 0) {
                  $html.= "<tr class='tab_bg_2'>";
               }

               $required = ($field['mandatory'] == 1) ? "<span class='red'>*</span>" : '';

               $field['itemtype'] = self::getType();
               $txt_label = PluginFieldsLabelTranslation::getLabelFor($field);
               $label =" <label for='{$field['name']}'>{$txt_label} $required</label>";

               if (stristr($container_obj->fields['itemtypes'], 'Ticket') !== false
                   && $container_obj->fields['type'] == 'dom'
                   && strpos($_SERVER['HTTP_REFERER'], ".injector.php") === false
                   && strpos($_SERVER['HTTP_REFERER'], ".public.php") === false) {
                   $html.= "<th width='13%'>$label</th>";
               } else {
                  $html.= "<td>$label</td>";
               }
               $html.= "<td>";
            }

            $readonly = $field['is_readonly'];
            switch ($field['type']) {
               case 'number':
               case 'text':
                  $value = Html::cleanInputText($value);
                  if ($canedit && !$readonly) {
                     $html.= "<input type='text' name='".$field['name']."' value=\"$value\" />";
                  } else {
                     $html.= $value;
                  }
                  break;
               case 'url':
                  $value = Html::cleanInputText($value);
                  if ($canedit && !$readonly) {
                     $html.= "<input type='text' name='".$field['name']."' value=\"$value\" />";
                     if ($value != '') {
                        $html .= "<a target=\"_blank\" href=\"$value\">" . __('show', 'fields') . "</a>";
                     }
                  } else {
                     $html .= "<a target=\"_blank\" href=\"$value\">$value</a>";
                  }
                  break;
               case 'textarea':
                  if ($canedit && !$readonly) {
                     $html.= "<textarea cols='45' rows='4' name='".$field['name']."'>".
                        "$value</textarea>";
                  } else {
                     $html.= nl2br($value);
                  }
                  break;
               case 'dropdown':
                  if ($canedit && !$readonly) {
                     //find entity on current object
                     $obj = new $itemtype;
                     $obj->getFromDB($items_id);

                     ob_start();
                     if (strpos($field['name'], "dropdowns_id") !== false) {
                        $dropdown_itemtype = getItemTypeForTable(
                                             getTableNameForForeignKeyField($field['name']));
                     } else {
                        $dropdown_itemtype = PluginFieldsDropdown::getClassname($field['name']);
                     }
                     Dropdown::show($dropdown_itemtype, array('value'  => $value,
                                                              'entity' => $obj->getEntityID()));
                     $html.= ob_get_contents();
                     ob_end_clean();
                  } else {
                     $dropdown_table = "glpi_plugin_fields_".$field['name']."dropdowns";
                     $html.= Dropdown::getDropdownName($dropdown_table, $value);
                  }
                  break;
               case 'yesno':
                  if ($canedit && !$readonly) {
                     ob_start();
                     Dropdown::showYesNo($field['name'], $value);
                     $html.= ob_get_contents();
                     ob_end_clean();
                  } else {
                     $html.= Dropdown::getYesNo($value);
                  }
                  break;
               case 'date':
                  if ($canedit && !$readonly) {
                     ob_start();
                     Html::showDateFormItem($field['name'], $value);
                     $html.= ob_get_contents();
                     ob_end_clean();
                  } else {
                     $html.= Html::convDate($value);
                  }
                  break;
               case 'datetime':
                  if ($canedit && !$readonly) {
                     ob_start();
                     Html::showDateTimeFormItem($field['name'], $value);
                     $html.= ob_get_contents();
                     ob_end_clean();
                  } else {
                     $html.= Html::convDateTime($value);
                  }
                  break;
               case 'dropdownuser':
                  if ($massiveaction) {
                     continue;
                  }
                  if ($canedit && !$readonly) {
                     ob_start();
                     User::dropdown(array('name'   => $field['name'],
                                          'value'  => $value,
                                          'entity' => -1,
                                          'right'  => 'all',
                                          'condition' => 'is_active=1 && is_deleted=0'));
                     $html.= ob_get_contents();
                     ob_end_clean();
                  } else {
                     $showuserlink = 0;
                     if (Session::haveRight('user', 'r')) {
                        $showuserlink = 1;
                     }
                     $html.= getUserName($value, $showuserlink);
                  }
            }
            if ($show_table) {
               $html.= "</td>";
               if ($odd%2 == 1) {
                  $html.= "</tr>";
               }
               $odd++;
            }
         }
      }
      if ($show_table && $odd%2 == 1) {
         $html.= "</tr>";
      }

      unset($_SESSION['plugin']['fields']['values_sent']);

      return $html;
   }

   static function showSingle($itemtype, $searchOption, $massiveaction = false) {
      global $DB;

      //find container for field in massive action
      $field_obj = new self;

      //clean dropdown [pre/su]fix if exists
      $cleaned_linkfield = preg_replace("/plugin_fields_(.*)dropdowns_id/", "$1",
                                        $searchOption['linkfield']);

      //find field
      $query = "SELECT fields.plugin_fields_containers_id, fields.is_readonly
                FROM glpi_plugin_fields_fields fields
                LEFT JOIN glpi_plugin_fields_containers containers
                  ON containers.id = fields.plugin_fields_containers_id
                  AND containers.itemtypes LIKE '%$itemtype%'
               WHERE fields.name = '$cleaned_linkfield'";
      $res = $DB->query($query);
      if ($DB->numrows($res) == 0) {
         return false;
      }

      $data = $DB->fetch_assoc($res);

      //display an hidden post field to store container id
      echo "<input type='hidden' name='c_id' value='".$data['plugin_fields_containers_id']."' />";

      //prepare array for function prepareHtmlFields
      $fields = array(array(
         'id'    => 0,
         'type'  => $searchOption['pfields_type'],
         'plugin_fields_containers_id'  => $data['plugin_fields_containers_id'],
         'name'  => $cleaned_linkfield,
         'is_readonly' => $data['is_readonly']
      ));

      //show field
      echo self::prepareHtmlFields($fields, 0, $itemtype, true, false, $massiveaction);

      return true;
   }

   static function getTypes() {
      return array(
         'header'       => __("Header", "fields"),
         'text'         => __("Text (single line)", "fields"),
         'textarea'     => __("Text (multiples lines)", "fields"),
         'number'       => __("Number", "fields"),
         'url'          => __("URL", "fields"),
         'dropdown'     => __("Dropdown", "fields"),
         'yesno'        => __("Yes/No", "fields"),
         'date'         => __("Date", "fields"),
         'datetime'     => __("Date & time", "fields"),
         'dropdownuser' => _n("User", "Users", 2)
      );
   }

   function post_addItem() {
      //Create label translation
      PluginFieldsLabelTranslation::createForItem($this);
   }
}
