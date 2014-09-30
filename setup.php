<?php
// Init the hooks of the plugins -Needed
function plugin_init_fields() {
   global $PLUGIN_HOOKS;

   $PLUGIN_HOOKS['csrf_compliant']['fields'] = true;

   $plugin = new Plugin();
   if (isset($_SESSION['glpiactiveentities'])
      && $plugin->isInstalled('fields')
      && $plugin->isActivated('fields')) {

      Plugin::registerClass('PluginFieldsContainer',
                            array('addtabon' => PluginFieldsContainer::getEntries()));

      $menu_entry = "front/container.php";
      if ((!isset($_SESSION['glpiactiveprofile']['config'])
         || $_SESSION['glpiactiveprofile']['config'] != "w")
      ) $menu_entry = false;

      $PLUGIN_HOOKS['menu_entry']['fields']  = $menu_entry;
      // $PLUGIN_HOOKS['config_page']['fields'] = $menu_entry;
      $PLUGIN_HOOKS['config_page']['fields'] = 'front/container.php';

      $PLUGIN_HOOKS['submenu_entry']['fields']['options']['container'] = array(
         'title' => __("Configurate the blocs", "fields"),
         'page'  => "/plugins/fields/$menu_entry",
         'links' => array(
            'search' => "/plugins/fields/$menu_entry",
            'add'    => "/plugins/fields/front/container.form.php"
      ));

      //include js and css
      $PLUGIN_HOOKS['add_css']['fields'][]           = 'fields.css';
      $pages_to_ban = array(
         "bollorelogisticsticket/front/ticket.form.php"
      );
      if (isset($_SERVER['REQUEST_URI'])) {
         $add_javascript = true;
         foreach ($pages_to_ban as $page) {
            if (strpos($_SERVER['REQUEST_URI'], $page) !== false) {
               $add_javascript = false;
               break;
            }
         }
         if ($add_javascript) {
            $PLUGIN_HOOKS['add_javascript']['fields'][] = 'fields.js.php';
         }
      }

      // Add/delete profiles to automaticaly to container
      $PLUGIN_HOOKS['item_add']['fields']['Profile']       = array("PluginFieldsProfile",
                                                                    "addNewProfile");
      $PLUGIN_HOOKS['pre_item_purge']['fields']['Profile'] = array("PluginFieldsProfile",
                                                                    "deleteProfile");

      //Retrieve dom container
      $itemtypes = PluginFieldsContainer::getUsedItemtypes();
      if ($itemtypes !== false) {
         foreach ($itemtypes as $itemtype) {
            $PLUGIN_HOOKS['post_prepareadd']['fields'][$itemtype] = array("PluginFieldsContainer",
                                                                          "preItemAdd");
            $PLUGIN_HOOKS['item_add']['fields'][$itemtype]        = array("PluginFieldsContainer",
                                                                          "postItemAdd");
            $PLUGIN_HOOKS['pre_item_update']['fields'][$itemtype]     = array("PluginFieldsContainer",
                                                                          "preItemUpdate");
            $PLUGIN_HOOKS['pre_item_purge']['fields'][$itemtype]  = array("PluginFieldsContainer",
                                                                          "preItemPurge");
         }
      }
   }
}


// Get the name and the version of the plugin - Needed
function plugin_version_fields() {
   return array ('name'           => __("Additionnal fields", "fields"),
                 'version'        => '0.84-1.6',
                 'author'         => 'Alexandre Delaunay & Walid Nouh',
                 'homepage'       => 'teclib.com',
                 'license'        => 'restricted',
                 'minGlpiVersion' => '0.84');
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_fields_check_prerequisites() {
   if (version_compare(GLPI_VERSION,'0.84','lt') || version_compare(GLPI_VERSION,'0.85','ge')) {
      echo "This plugin requires GLPI 0.84";
      return false;
   }
   if (version_compare(PHP_VERSION, '5.3.0', 'lt')) {
      echo "PHP 5.3.0 or higher is required";
      return false;
   }

   // Check class and front files for existing containers and dropdown fields
   plugin_fields_checkFiles();

   return true;
}

function plugin_fields_checkFiles() {
   $plugin = new Plugin();

   if (isset($_SESSION['glpiactiveentities'])
      && $plugin->isInstalled('fields')
      && $plugin->isActivated('fields')) {

      Plugin::registerClass('PluginFieldsContainer');
      Plugin::registerClass('PluginFieldsDropdown');
      Plugin::registerClass('PluginFieldsField');

      if (TableExists("glpi_plugin_fields_containers")) {
         $container_obj = new PluginFieldsContainer();
         $containers    = $container_obj->find();

         foreach ($containers as $container) {
            $classname = "PluginFields".ucfirst($container['itemtype'].
                                        preg_replace('/s$/', '', $container['name']));
            if(!class_exists($classname)) {
               PluginFieldsContainer::generateTemplate($container);
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

// Check configuration process for plugin : need to return true if succeeded
// Can display a message only if failure and $verbose is true
function plugin_fields_check_config($verbose = false) {
   if (true) { // Your configuration check
      return true;
   }
   if ($verbose) {
      echo __("Installed / not configured");
   }
   return false;
}
