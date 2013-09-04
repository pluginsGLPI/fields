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
      global $LANG;

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
            Session::AddMessageAfterRedirect($LANG['fields']['error']['dropdown_unique']);
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
         $classname = "PluginFields".ucfirst(strtolower($container_obj->fields['itemtype'].
                                       preg_replace('/s$/', '', $container_obj->fields['name'])));
         $classname::addField($input['name'], $input['type']);
      }

      if (isset($oldname)) $input['name'] = $oldname;

      return $input;
   }
   function prepareInputForUpdate($input) {
      //parse name
      $input['name'] = $this->prepareName($input);

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
         $classname = "PluginFields".ucfirst(strtolower($container_obj->fields['itemtype'].
                                       preg_replace('/s$/', '', $container_obj->fields['name'])));
         $classname::removeField($this->fields['name']);
      }
      
      if (isset($oldname)) $this->fields['name'] = $oldname;

      if ($this->fields['type'] === "dropdown") {
         return PluginFieldsDropdown::destroy($this->fields['name']);
      }
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
         $input['name'] = strtolower(preg_replace("/[^\da-z]/i", "", $input['label']));
      }

      //for dropdown, if already exist, link to it
      if ($input['type'] === "dropdown") {
         $found = $this->find("name = '".$input['name']."'");
         if (!empty($found)) return $input['name'];
      }

      //check if field name not already exist and not in conflict with itemtype fields name
      $containers_id = $input['plugin_fields_containers_id'];
      $container = new PluginFieldsContainer;
      $container->getFromDB($containers_id);
      $item = new $container->fields['itemtype'];
      $item->getEmpty();
      $field  = new self;
      $i = 2;
      $field_name = $input['name'];
      while (count($field->find("name = '$field_name'")) > 0 || isset($item->fields[$field_name])) {
         $field_name = $input['name'].$i;
         $i++;
      }
      return $field_name;
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
      $tmp    = array('plugin_fields_containers_id' => $cID);
      $canadd = $this->can(-1, 'w', $tmp);

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
         echo $LANG['fields']['field']['label']['add']."</a></div><br>\n";
      

      if ($DB->numrows($result) == 0) {
         echo "<table class='tab_cadre_fixe'><tr class='tab_bg_2'>";
         echo "<th class='b'>".$LANG['fields']['field']['label']['no_fields']."</th></tr></table>";
      } else {
         echo "<table class='tab_cadre_fixehov'>";
         echo "<tr>";
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
               echo $this->fields['label']."</td>";
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
         $edit = true;
      } else {
         // Create item
         $edit = false;
         $input = array('plugin_fields_containers_id' => $container->getField('id'));
         $this->check(-1,'w',$input);
      }

      $this->showFormHeader($options);

      echo "<tr>";
      echo "<td>".$LANG['mailing'][139]." : </td>";
      echo "<td>";
      echo "<input type='hidden' name='plugin_fields_containers_id' value='".
         $container->getField('id')."'>";
      Html::autocompletionTextField($this, 'label', array('value' => $this->fields["label"]));
      echo "</td>";
     
      if (!$edit) {
         echo "</tr>";
         echo "<tr>";
         echo "<td>".$LANG['common'][17]." : </td>";
         echo "<td>";
         Dropdown::showFromArray('type', self::getTypes(), 
            array('value' => $this->fields["type"]));
         echo "</td>";
      } 
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

      //profile restriction (for reading profile)
      $canedit = false;
      $profile = new PluginFieldsProfile;
      $found = $profile->find("`profiles_id` = '".$_SESSION['glpiactiveprofile']['id']."' 
                                 AND `plugin_fields_containers_id` = '$c_id'");
      $first_found = array_shift($found);
      if ($first_found['right'] == "w") {
         $canedit = true;
      }
      
      //get fields for this container
      $fields = $field_obj->find("plugin_fields_containers_id = $c_id", "ranking");
      echo "<form method='POST' action='".$CFG_GLPI["root_doc"].
         "/plugins/fields/front/container.form.php'>";
      echo "<input type='hidden' name='plugin_fields_containers_id' value='$c_id'>";
      echo "<input type='hidden' name='items_id' value='$items_id'>";
      echo "<table class='tab_cadre_fixe'>";
      echo self::prepareHtmlFields($fields, $items_id, $canedit);
      
      if ($canedit) {
         echo "<tr><td class='tab_bg_2 center' colspan='4'>";
         echo "<input type='submit' name='update_fields_values' value=\"".
            $LANG['buttons'][7]."\" class='submit'>";
         echo "</td></tr>";
      }

      echo "</table></form>";

      return true;
   }


   static function showForDomContainer() {

      //parse http_referer to get current url (this code is loaded by javacript)
      $current_url = $_SERVER['HTTP_REFERER'];
      if (strpos($current_url, ".form.php") === false
            && strpos($current_url, ".injector.php") === false
            && strpos($current_url, ".public.php") === false) {
         return false;
      }
      $expl_url = explode("?", $current_url);

      //if add item form, do nothing
      //if (!isset($expl_url[1]) || strpos($expl_url[1], "id=") === false) return false;

      //get current id
      if(isset($expl_url[1])) {
         parse_str($expl_url[1], $params);
         if(isset($params['id'])) {
            $items_id = $params['id'];
         } else {
            $items_id = 0;
         }
      } else {
         $items_id = 0;
      }

      //get itemtype
      $tmp = explode("/", $expl_url[0]);
      $script_name = array_pop($tmp);

      if(in_array($script_name, array("helpdesk.public.php","tracking.injector.php"))) {
         $current_itemtype = "Ticket";
      } else {
         $current_itemtype = ucfirst(str_replace(".form.php", "", $script_name));
      }

      //Retrieve dom container 
      $itemtypes = PluginFieldsContainer::getEntries('dom', true);

      //if no dom containers defined for this itemtype, do nothing
      if (!isset($itemtypes[$current_itemtype])) return false;


      echo "Ext.onReady(function() {\n
         Ext.select('#page form tr:last').each(function(el){
            el.insertHtml('beforeBegin', '<tr><td colspan=\"4\" id=\"dom_container\"></td></tr>');
            Ext.get('dom_container').load({
               url: '../plugins/fields/ajax/load_dom_fields.php',
               params: {
                  itemtype: '$current_itemtype',
                  items_id: '$items_id'
               }
            });
         });

         
      ";

      echo "});\n";
   }

   static function AjaxForDomContainer($itemtype, $items_id) {
      //retieve dom containers associated to this itemtype
      $c_id = PluginFieldsContainer::findContainer($itemtype, $items_id, "dom");

      //get fields for this container
      $field_obj = new self;
      $fields = $field_obj->find("plugin_fields_containers_id = $c_id", "ranking");
      echo "<table class='tab_cadre_fixe'>";
      echo $html_fields = str_replace("\n", "", self::prepareHtmlFields($fields, $items_id));
       echo "</table>";
   }

   

   static function prepareHtmlFields($fields, $items_id, $canedit = true, 
                                     $show_table = true, $massiveaction = false) {
      //get object associated with this fields
      $tmp = $fields;
      $first_field = array_shift($tmp);
      $container_obj = new PluginFieldsContainer;
      $container_obj->getFromDB($first_field['plugin_fields_containers_id']);
      $classname = "PluginFields".ucfirst($container_obj->fields['itemtype'].
                                  preg_replace('/s$/', '', $container_obj->fields['name']));
      $obj = new $classname;

      //find row for this object with the items_id
      $found_values = $obj->find("plugin_fields_containers_id = ".
                                 $first_field['plugin_fields_containers_id']." AND items_id = ".
                                 $items_id);
      $found_v = array_shift($found_values);

      //show all fields
      $html = "";
      $odd = 0;
      foreach($fields as $field) {
      
         if ($field['type'] === 'header') {
            $html.= "<tr class='tab_bg_2'>";
            $html.= "<th colspan='4'>".$field['label']."</td>";
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

            if (isset($_SESSION['plugin']['fields']['values_sent'])) {
               if ($field['type'] == "dropdown") {
                  $value = $_SESSION['plugin']['fields']['values_sent']["plugin_fields_".
                                                                        $field['name'].
                                                                        "dropdowns_id"];
               } else {
                  $value = $_SESSION['plugin']['fields']['values_sent'][$field['name']];
               }
            }

            //get default value
            if (empty($value) && !empty($field['default_value'])) {
               $value = $field['default_value'];
            }

            //show field
            if ($show_table) {
               if ($odd%2 == 0)  $html.= "<tr class='tab_bg_2'>";
               if ($container_obj->fields['itemtype'] == 'Ticket' 
                   && $container_obj->fields['type'] == 'dom') {
                  $html.= "<th width='13%'>".$field['label']." : </th>";
               } else {
                  $html.= "<td>".$field['label']." : </td>";
               }
               $html.= "<td>";
            }
            switch ($field['type']) {
               case 'number':
               case 'text':
                  $value = Html::cleanInputText($value);
                  if ($canedit) {
                     $html.= "<input type='text' name='".$field['name']."' value=\"$value\" />";
                  } else {
                     $html.= $value;
                  }
                  break;
               case 'textarea':
                  if ($massiveaction) continue;
                  if ($canedit) {
                     $html.= "<textarea cols='45' rows='4' name='".$field['name']."'>".
                        "$value</textarea>";
                  } else {
                     $html.= str_replace('\n', '<br />', $value);
                  }
                  break;
               case 'dropdown':
                  if ($canedit) {
                     ob_start();
                     if (strpos($field['name'], "dropdowns_id") !== false) {
                        $dropdown_itemtype = getItemTypeForTable(
                                             getTableNameForForeignKeyField($field['name']));
                     } else {
                        $dropdown_itemtype = PluginFieldsDropdown::getClassname($field['name']);
                     }
                     Dropdown::show($dropdown_itemtype, array('value' => $value));
                     $html.= ob_get_contents();
                     ob_end_clean();
                  } else {
                     $dropdown_table = "glpi_plugin_fields_".$field['name']."dropdowns";
                     $html.= Dropdown::getDropdownName($dropdown_table, $value);
                  }
                  break;
               case 'yesno':
                  //in massive action, we must skip display for yesno (possible bug in framework)
                  //otherwise double display of field
                  if ($massiveaction) continue;
                  
                  if ($canedit) {
                     ob_start();
                     Dropdown::showYesNo($field['name'], $value);
                     $html.= ob_get_contents();
                     ob_end_clean();
                  } else {
                     $html.= Dropdown::getYesNo($value);
                  }
                  break;
               case 'date':
                  if ($massiveaction) continue;
                  if ($canedit) {
                     ob_start();
                     Html::showDateFormItem($field['name'], $value);
                     $html.= ob_get_contents();
                     ob_end_clean();
                  } else {
                     $html.= Html::convDate($value);
                  }
                  break;
               case 'datetime':
                  if ($massiveaction) continue;
                  if ($canedit) {
                     ob_start();
                     Html::showDateTimeFormItem($field['name'], $value);
                     $html.= ob_get_contents();
                     ob_end_clean();
                  } else {
                     $html.= Html::convDateTime($value);
                  }
            }
            if ($show_table) {
               $html.= "</td>";
               if ($odd%2 == 1)  $html.= "</tr>";
               $odd++;
            }
         }         
      }
      if ($show_table && $odd%2 == 1)  $html.= "</tr>";

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
      $query_f = "SELECT fields.plugin_fields_containers_id
                FROM glpi_plugin_fields_fields fields
                LEFT JOIN glpi_plugin_fields_containers containers
                  ON containers.id = fields.plugin_fields_containers_id
                  AND containers.itemtype = '$itemtype'
               WHERE fields.name = '$cleaned_linkfield'";
      $res_f = $DB->query($query_f);
      if ($DB->numrows($res_f) == 0) return false;
      else {
         $row_f = $DB->fetch_assoc($res_f);
         $c_id = $row_f['plugin_fields_containers_id'];
      }

      //display an hidden post field to store container id
      echo "<input type='hidden' name='c_id' value='$c_id' />";

      //preapre arary for function prepareHtmlFields
      $fields = array(array(
         'id'    => 0,
         'type'  => $searchOption['pfields_type'],
         'plugin_fields_containers_id'  => $c_id,
         'name'  => $cleaned_linkfield
      ));

      //show field
      echo self::prepareHtmlFields($fields, 0, true, false, $massiveaction);
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
         'date'     => $LANG['fields']['field']['type']['date'],
         'datetime' => $LANG['fields']['field']['type']['datetime']
      );
   }

}