<?php
/*
 -------------------------------------------------------------------------
 Fields plugin for GLPI
 Copyright (C) 2016 by the Fields Development Team.

 https://github.com/pluginsGLPI/fields
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Fields.

 Fields is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Fields is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Fields. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

define ('PLUGIN_FIELDS_VERSION', '1.12.8');

// Minimal GLPI version, inclusive
define("PLUGIN_FIELDS_MIN_GLPI", "9.5");
// Maximum GLPI version, exclusive
define("PLUGIN_FIELDS_MAX_GLPI", "9.6");

if (!defined("PLUGINFIELDS_DIR")) {
   define("PLUGINFIELDS_DIR", Plugin::getPhpDir("fields"));
}
if (!defined("PLUGINFIELDS_WEB_DIR")) {
   define("PLUGINFIELDS_WEB_DIR", Plugin::getWebDir("fields"));
}

if (!defined("PLUGINFIELDS_DOC_DIR")) {
   define("PLUGINFIELDS_DOC_DIR", GLPI_PLUGIN_DOC_DIR . "/fields");
}
if (!file_exists(PLUGINFIELDS_DOC_DIR)) {
   mkdir(PLUGINFIELDS_DOC_DIR);
}

if (!defined("PLUGINFIELDS_CLASS_PATH")) {
   define("PLUGINFIELDS_CLASS_PATH", PLUGINFIELDS_DOC_DIR . "/inc");
}
if (!file_exists(PLUGINFIELDS_CLASS_PATH)) {
   mkdir(PLUGINFIELDS_CLASS_PATH);
}

if (!defined("PLUGINFIELDS_FRONT_PATH")) {
   define("PLUGINFIELDS_FRONT_PATH", PLUGINFIELDS_DOC_DIR."/front");
}
if (!file_exists(PLUGINFIELDS_FRONT_PATH)) {
   mkdir(PLUGINFIELDS_FRONT_PATH);
}

use Symfony\Component\Yaml\Yaml;

/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_fields() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['fields'] = true;

   // manage autoload of plugin custom classes
   include_once(PLUGINFIELDS_DIR . "/inc/autoload.php");

   // manage autoload of vendor classes
   include_once(PLUGINFIELDS_DIR . "/vendor/autoload.php");
   $pluginfields_autoloader = new PluginFieldsAutoloader([PLUGINFIELDS_CLASS_PATH]);
   $pluginfields_autoloader->register();

   $plugin = new Plugin();
   if ($plugin->isInstalled('fields')
       && $plugin->isActivated('fields')
       && Session::getLoginUserID()) {

      // Init hook about itemtype(s) for plugin fields
      if (!isset($PLUGIN_HOOKS['plugin_fields'])) {
         $PLUGIN_HOOKS['plugin_fields'] = [];
      }

      // When a Category is changed during ticket creation
      if (isset($_POST) && !empty($_POST)
          && isset($_POST['_plugin_fields_type'])
          && $_SERVER['REQUEST_URI'] == Ticket::getFormURL()) {
         foreach ($_POST as $key => $value) {
            if (!is_array($value)) {
               $_SESSION['plugin']['fields']['values_sent'][$key] = stripcslashes($value);
            }
         }
      }

      if ($plugin->isActivated('fusioninventory')) {
         $PLUGIN_HOOKS['fusioninventory_inventory']['fields']
            = ['PluginFieldsInventory', 'updateInventory'];
      }

      // complete rule engine
      $PLUGIN_HOOKS['use_rules']['fields']    = ['PluginFusioninventoryTaskpostactionRule'];
      $PLUGIN_HOOKS['rule_matched']['fields'] = 'plugin_fields_rule_matched';

      if (isset($_SESSION['glpiactiveentities'])) {

         // add link in plugin page
         $PLUGIN_HOOKS['config_page']['fields'] = 'front/container.php';

         // add entry to configuration menu
         $PLUGIN_HOOKS["menu_toadd"]['fields'] = ['config' => 'PluginFieldsMenu'];

         // add tabs to itemtypes
         Plugin::registerClass('PluginFieldsContainer',
                               ['addtabon' => array_unique(PluginFieldsContainer::getEntries())]);

         //include js and css
         $debug = (isset($_SESSION['glpi_use_mode'])
                   && $_SESSION['glpi_use_mode'] == Session::DEBUG_MODE ? true : false);
         if (!$debug && file_exists(__DIR__ . '/css/fields.min.css')) {
            $PLUGIN_HOOKS['add_css']['fields'][] = 'css/fields.min.css';
         } else {
            $PLUGIN_HOOKS['add_css']['fields'][] = 'css/fields.css';
         }

         // Add/delete profiles to automaticaly to container
         $PLUGIN_HOOKS['item_add']['fields']['Profile']
            = ["PluginFieldsProfile", "addNewProfile"];
         $PLUGIN_HOOKS['pre_item_purge']['fields']['Profile']
            = ["PluginFieldsProfile", "deleteProfile"];

         //load drag and drop javascript library on Package Interface
         $PLUGIN_HOOKS['add_javascript']['fields'][] = "js/redips-drag-min.js";
         if (!$debug && file_exists(__DIR__ . '/js/drag-field-row.min.js')) {
            $PLUGIN_HOOKS['add_javascript']['fields'][] = 'js/drag-field-row.min.js';
         } else {
            $PLUGIN_HOOKS['add_javascript']['fields'][] = 'js/drag-field-row.js';
         }
      }

      // Add Fields to Datainjection
      if ($plugin->isActivated('datainjection')) {
         $PLUGIN_HOOKS['plugin_datainjection_populate']['fields']
            = "plugin_datainjection_populate_fields";
      }

      //Retrieve dom container
      $itemtypes = PluginFieldsContainer::getUsedItemtypes();
      if ($itemtypes !== false) {
         foreach ($itemtypes as $itemtype) {
            $PLUGIN_HOOKS['pre_item_update']['fields'][$itemtype] = ["PluginFieldsContainer",
                                                                     "preItemUpdate"];
            $PLUGIN_HOOKS['pre_item_add']['fields'][$itemtype]    = ["PluginFieldsContainer",
                                                                     "preItem"];
            $PLUGIN_HOOKS['item_add']['fields'][$itemtype]        = ["PluginFieldsContainer",
                                                                     "postItemAdd"];
            $PLUGIN_HOOKS['pre_item_purge'] ['fields'][$itemtype] = ["PluginFieldsContainer",
                                                                     "preItemPurge"];
         }
      }

      // Display fields in any existing tab
      $PLUGIN_HOOKS['post_item_form']['fields'] = ['PluginFieldsField',
                                                   'showForTab'];
   }
}


/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_fields() {
   return [
      'name'           => __("Additionnal fields", "fields"),
      'version'        => PLUGIN_FIELDS_VERSION,
      'author'         => 'Teclib\', Olivier Moron',
      'homepage'       => 'https://github.com/pluginsGLPI/fields',
      'license'        => 'GPLv2+',
      'requirements'   => [
         'glpi' => [
            'min' => PLUGIN_FIELDS_MIN_GLPI,
            'max' => PLUGIN_FIELDS_MAX_GLPI,
            'dev' => true, //Required to allow 9.2-dev
         ]
      ]
   ];
}

/**
 * Check pre-requisites before install
 * OPTIONNAL, but recommanded
 *
 * @return boolean
 */
function plugin_fields_check_prerequisites() {
   if (!is_readable(__DIR__ . '/vendor/autoload.php') || !is_file(__DIR__ . '/vendor/autoload.php')) {
      echo "Run composer install --no-dev in the plugin directory<br>";
      return false;
   }

   return true;
}

/**
 * Check all stored containers files (classes & front) are present, or create they if needed
 *
 * @return void
 */
function plugin_fields_checkFiles($force = false) {
   global $DB;

   $plugin = new Plugin();

   if ($force) {
      //clean all existing files
      array_map('unlink', glob(PLUGINFIELDS_DOC_DIR.'/*/*'));
   }

   if (isset($_SESSION['glpiactiveentities'])
      && $plugin->isInstalled('fields')
      && $plugin->isActivated('fields')
      && Session::getLoginUserID()) {

      if ($DB->tableExists(PluginFieldsContainer::getTable())) {
         $container_obj = new PluginFieldsContainer();
         $containers    = $container_obj->find();

         foreach ($containers as $container) {
            $itemtypes = (strlen($container['itemtypes']) > 0)
               ? json_decode($container['itemtypes'], true)
               : [];
            foreach ($itemtypes as $itemtype) {
               $classname = PluginFieldsContainer::getClassname($itemtype, $container['name']);
               if (!class_exists($classname)) {
                  PluginFieldsContainer::generateTemplate($container);
               }

               // regenerate table (and fields) also
               $classname::install($container['id']);
            }
         }
      }

      if ($DB->tableExists(PluginFieldsField::getTable())) {
         $fields_obj = new PluginFieldsField();
         $fields     = $fields_obj->find(['type' => 'dropdown']);
         foreach ($fields as $field) {
            PluginFieldsDropdown::create($field);
         }
      }
   }
}

function plugin_fields_exportBlockAsYaml($container_id = null) {
   global $DB;

   $plugin = new Plugin();
   $yaml_conf = [
      'container' => [],
   ];

   if (isset($_SESSION['glpiactiveentities'])
      && $plugin->isInstalled('fields')
      && $plugin->isActivated('fields')
      && Session::getLoginUserID()) {

      if ($DB->tableExists(PluginFieldsContainer::getTable())) {
         $where = [];
         $where["is_active"] = true;
         if ($container_id != null) {
            $where["id"] = $container_id;
         }
         $container_obj = new PluginFieldsContainer();
         $containers    = $container_obj->find($where);

         foreach ($containers as $container) {
            $itemtypes = (strlen($container['itemtypes']) > 0)
            ? json_decode($container['itemtypes'], true)
            : [];

            foreach ($itemtypes as $itemtype) {
               $fields_obj = new PluginFieldsField();
               // to get translation
               $container["itemtype"] = PluginFieldsContainer::getType();
               $yaml_conf['container'][$container['id']."-".$itemtype] = [
                  "id"        => (int) $container['id'],
                  "name"      => PluginFieldsLabelTranslation::getLabelFor($container),
                  "itemtype"  => $itemtype,
                  "type"      => $container['type'],
                  "subtype"   => $container['subtype'],
                  "fields"    => [],
               ];
               $fields = $fields_obj->find(["plugin_fields_containers_id"  => $container['id'],
                                             "is_active"                   => true,
                                             "is_readonly"                 => false]);
               if (count($fields)) {
                  foreach ($fields as $field) {
                     $tmp_field = [];
                     $tmp_field['id'] = (int) $field['id'];

                     //to get translation
                     $field["itemtype"] = PluginFieldsField::getType();
                     $tmp_field['label'] = PluginFieldsLabelTranslation::getLabelFor($field);
                     $tmp_field['xml_node'] = strtoupper($field['name']);
                     $tmp_field['type']  = $field['type'];
                     $tmp_field['ranking'] = $field['ranking'];
                     $tmp_field['default_value'] = $field['default_value'];
                     $tmp_field['mandatory'] = $field['mandatory'];
                     $tmp_field['possible_value'] = "";

                     switch ($field['type']) {
                        case 'dropdown':
                           $obj = new $itemtype;
                           $obj->getEmpty();

                           $dropdown_itemtype = PluginFieldsDropdown::getClassname($field['name']);
                           $tmp_field['xml_node'] = strtoupper(getForeignKeyFieldForItemType($dropdown_itemtype));

                           $dropdown_obj = new $dropdown_itemtype();
                           $dropdown_datas = $dropdown_obj->find();
                           $datas = [];
                           foreach ($dropdown_datas as $key => $value) {
                              $items = [];
                              $items['id'] = (int)$value['id'];
                              $items['value'] = $value['name'];
                              $datas[] = $items;
                           }
                           $tmp_field['possible_value'] = $datas;
                           break;
                        case 'yesno':
                           $datas = [];
                           $datas["0"]['id'] = 0;
                           $datas["0"]['value'] = __('No');
                           $datas["1"]['id'] = 1;
                           $datas["1"]['value'] = __('Yes');
                           $tmp_field['possible_value'] = $datas;
                           break;
                        case 'dropdownuser':
                           $datas = Dropdown::getDropdownUsers(['is_active' => 1,'is_deleted' => 0], false);
                           $tmp_field['possible_value'] = $datas['results'];
                           break;
                     }
                     $yaml_conf['container'][$container['id']."-".$itemtype]["fields"][] = $tmp_field;
                  }
               }
            }
         }
      }
   }

   if (count($yaml_conf)) {
      $dump =   Yaml::dump($yaml_conf, 10);
      $filename = GLPI_TMP_DIR."/fields_conf.yaml";
      file_put_contents($filename, $dump);
      return true;
   }

   return false;
}
