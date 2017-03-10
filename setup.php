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

define ('PLUGIN_FIELDS_VERSION', '1.6.1');

if (!defined("PLUGINFIELDS_DIR")) {
   define("PLUGINFIELDS_DIR", GLPI_ROOT . "/plugins/fields");
}

if (!defined("PLUGINFIELDS_DOC_DIR")) {
   define("PLUGINFIELDS_DOC_DIR", GLPI_PLUGIN_DOC_DIR . "/fields");
   if (!file_exists(PLUGINFIELDS_DOC_DIR)) {
      mkdir(PLUGINFIELDS_DOC_DIR);
   }
}

if (!defined("PLUGINFIELDS_CLASS_PATH")) {
   define("PLUGINFIELDS_CLASS_PATH", PLUGINFIELDS_DOC_DIR . "/inc");
   if (!file_exists(PLUGINFIELDS_CLASS_PATH)) {
      mkdir(PLUGINFIELDS_CLASS_PATH);
   }
}

if (!defined("PLUGINFIELDS_FRONT_PATH")) {
   define("PLUGINFIELDS_FRONT_PATH", PLUGINFIELDS_DOC_DIR."/front");
   if (!file_exists(PLUGINFIELDS_FRONT_PATH)) {
      mkdir(PLUGINFIELDS_FRONT_PATH);
   }
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

   include_once(PLUGINFIELDS_DIR . "/vendor/autoload.php");
   include_once(PLUGINFIELDS_DIR . "/inc/autoload.php");

   $options = array(
      PLUGINFIELDS_CLASS_PATH
   );
   $pluginfields_autoloader = new PluginFieldsAutoloader($options);
   $pluginfields_autoloader->register();

   $plugin = new Plugin();
   if ($plugin->isInstalled('fields')
       && $plugin->isActivated('fields')
       && Session::getLoginUserID() ) {

      // Init hook about itemtype(s) for plugin fields
      $PLUGIN_HOOKS['plugin_fields'] = array();

      // When a Category is changed during ticket creation
      if (isset($_POST) && !empty($_POST) && isset($_POST['_plugin_fields_type'])) {
         if ($_SERVER['REQUEST_URI'] == Ticket::getFormURL()) {
            //$_SESSION['plugin_fields']['Ticket'] = $_POST;
            foreach ($_POST as $key => $value) {
               if (! is_array($value)) {
                  $_SESSION['plugin']['fields']['values_sent'][$key] = stripcslashes($value);
               }
            }
         }
      }

      // complete rule engine
      $PLUGIN_HOOKS['use_rules']['fields']    = array('PluginFusioninventoryTaskpostactionRule');
      $PLUGIN_HOOKS['rule_matched']['fields'] = 'plugin_fields_rule_matched';

      if (isset($_SESSION['glpiactiveentities'])) {

         $PLUGIN_HOOKS['config_page']['fields'] = 'front/container.php';

         // add entry to configuration menu
         $PLUGIN_HOOKS["menu_toadd"]['fields'] = array('config'  => 'PluginFieldsMenu');

         // add tabs to itemtypes
         Plugin::registerClass('PluginFieldsContainer',
                               array('addtabon' => array_unique(PluginFieldsContainer::getEntries())));

         //include js and css
         $debug = (isset($_SESSION['glpi_use_mode']) && $_SESSION['glpi_use_mode'] == Session::DEBUG_MODE ? true : false);
         if (!$debug && file_exists(__DIR__ . '/css/fields.min.css')) {
            $PLUGIN_HOOKS['add_css']['fields'][]           = 'css/fields.min.css';
         } else {
            $PLUGIN_HOOKS['add_css']['fields'][]           = 'css/fields.css';
         }

         // Add/delete profiles to automaticaly to container
         $PLUGIN_HOOKS['item_add']['fields']['Profile']       = array("PluginFieldsProfile",
                                                                       "addNewProfile");
         $PLUGIN_HOOKS['pre_item_purge']['fields']['Profile'] = array("PluginFieldsProfile",
                                                                       "deleteProfile");

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
         $PLUGIN_HOOKS['plugin_datainjection_populate']['fields'] = "plugin_datainjection_populate_fields";
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

      $PLUGIN_HOOKS['post_item_form']['fields'] = ['PluginFieldsField',
                                                   'showForTab'];

      // Check class and front files for existing containers and dropdown fields
      plugin_fields_checkFiles();

   }
}


/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_fields() {
   return array ('name'           => __("Additionnal fields", "fields"),
                 'version'        => PLUGIN_FIELDS_VERSION,
                 'author'         => 'Teclib\', Olivier Moron',
                 'homepage'       => 'teclib.com',
                 'license'        => 'GPLv2+',
                 'minGlpiVersion' => '9.1.2');
}

/**
 * Check pre-requisites before install
 * OPTIONNAL, but recommanded
 *
 * @return boolean
 */
function plugin_fields_check_prerequisites() {
   if (version_compare(GLPI_VERSION, '9.1.2', 'lt')) {
      if (method_exists('Plugin', 'messageIncompatible')) {
         echo Plugin::messageIncompatible('core', '9.1.2');
      } else {
         echo "This plugin requires GLPI 9.1.2";
      }
      return false;
   }

   return true;
}


function plugin_fields_checkFiles() {
   $plugin = new Plugin();

   if (isset($_SESSION['glpiactiveentities'])
      && $plugin->isInstalled('fields')
      && $plugin->isActivated('fields')
      && Session::getLoginUserID()) {

      Plugin::registerClass('PluginFieldsContainer');
      Plugin::registerClass('PluginFieldsDropdown');
      Plugin::registerClass('PluginFieldsField');

      if (TableExists("glpi_plugin_fields_containers")) {
         $container_obj = new PluginFieldsContainer();
         $containers    = $container_obj->find();

         foreach ($containers as $container) {
            $itemtypes = (count($container['itemtypes']) > 0) ? json_decode($container['itemtypes'], TRUE) : array();
            foreach ($itemtypes as $itemtype) {
               $classname = "PluginFields".ucfirst($itemtype.
                                        preg_replace('/s$/', '', $container['name']));
               if (!class_exists($classname)) {
                  PluginFieldsContainer::generateTemplate($container);
               }
            }
         }
      }

      if (TableExists("glpi_plugin_fields_fields")) {
         $fields_obj = new PluginFieldsField();
         $fields     = $fields_obj->find("`type` = 'dropdown'");
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
   if (true) { // Your configuration check
      return true;
   }
   if ($verbose) {
      echo __("Installed / not configured");
   }
   return false;
}
