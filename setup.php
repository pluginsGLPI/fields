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

define ('PLUGIN_FIELDS_VERSION', '1.9.1');

// Minimal GLPI version, inclusive
define("PLUGIN_FIELDS_MIN_GLPI", "9.4");
// Maximum GLPI version, exclusive
define("PLUGIN_FIELDS_MAX_GLPI", "9.5");

if (!defined("PLUGINFIELDS_DIR")) {
   define("PLUGINFIELDS_DIR", GLPI_ROOT . "/plugins/fields");
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
   $pluginfields_autoloader = new PluginFieldsAutoloader([PLUGINFIELDS_CLASS_PATH]);
   $pluginfields_autoloader->register();

   $plugin = new Plugin();
   if ($plugin->isInstalled('fields')
       && $plugin->isActivated('fields')
       && Session::getLoginUserID() ) {

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
      'homepage'       => 'teclib.com',
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

   //Version check is not done by core in GLPI < 9.2 but has to be delegated to core in GLPI >= 9.2.
   if (!method_exists('Plugin', 'checkGlpiVersion')) {
      $version = preg_replace('/^((\d+\.?)+).*$/', '$1', GLPI_VERSION);
      $matchMinGlpiReq = version_compare($version, PLUGIN_FIELDS_MIN_GLPI, '>=');
      $matchMaxGlpiReq = version_compare($version, PLUGIN_FIELDS_MAX_GLPI, '<');

      if (!$matchMinGlpiReq || !$matchMaxGlpiReq) {
         echo vsprintf(
            'This plugin requires GLPI >= %1$s and < %2$s.',
            [
               PLUGIN_FIELDS_MIN_GLPI,
               PLUGIN_FIELDS_MAX_GLPI,
            ]
         );
         return false;
      }
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

/**
 * Check configuration process
 *
 * @param boolean $verbose Whether to display message on failure. Defaults to false
 *
 * @return boolean
 */
function plugin_fields_check_config($verbose = false) {
   return true;
}
