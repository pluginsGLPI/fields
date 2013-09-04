<?php
// Init the hooks of the plugins -Needed
function plugin_init_fields() {
   global $PLUGIN_HOOKS, $LANG;

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
      $PLUGIN_HOOKS['config_page']['fields'] = $menu_entry;

      $PLUGIN_HOOKS['submenu_entry']['fields']['options']['container'] = array(
         'title' => $LANG['fields']['config']['containers'],
         'page'  => "/plugins/fields/$menu_entry",
         'links' => array(
            'search' => "/plugins/fields/$menu_entry",
            'add'    => "/plugins/fields/front/container.form.php"
      ));

      //include js and css
      $PLUGIN_HOOKS['add_css']['fields'][]           = 'fields.css';
      $PLUGIN_HOOKS['add_javascript']['fields'][]    = 'fields.js.php';

      // Massive Action definition
      //$PLUGIN_HOOKS['use_massive_action']['fields'] = 1;


      //Retrieve dom container 
      $itemtypes = PluginFieldsContainer::getEntries('all');
      foreach ($itemtypes as $itemtype) {
         $PLUGIN_HOOKS['pre_item_update']['fields'][$itemtype] = array("PluginFieldsContainer", 
                                                                       "preItemUpdate");
         $PLUGIN_HOOKS['pre_item_purge'] ['fields'][$itemtype] = array("PluginFieldsContainer", 
                                                                       "preItemPurge");
         $PLUGIN_HOOKS['item_add']['fields'][$itemtype]        = array("PluginFieldsContainer", 
                                                                       "preItemUpdate");
      }
   }
}


// Get the name and the version of the plugin - Needed
function plugin_version_fields() {
   global $LANG;
   return array ('name'           => $LANG["fields"]["title"][1],
                 'version'        => '2.0',
                 'author'         => 'Alexandre Delaunay & Walid Nouh',
                 'homepage'       => 'teclib.com',
                 'license'        => 'restricted',
                 'minGlpiVersion' => '0.83.3');
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_fields_check_prerequisites() {
   if (version_compare(GLPI_VERSION,'0.83.3','lt') || version_compare(GLPI_VERSION,'0.84','ge')) {
      echo "This plugin requires GLPI 0.83.3";
      return false;
   }
   if (version_compare(PHP_VERSION, '5.3.0', 'lt')) {
      echo "PHP 5.3.0 or higher is required";
      return false;
   }
   return true;
}

// Check configuration process for plugin : need to return true if succeeded
// Can display a message only if failure and $verbose is true
function plugin_fields_check_config($verbose = false) {
   global $LANG;

   if (true) { // Your configuration check
      return true;
   }
   if ($verbose) {
      echo $LANG['plugins'][2];
   }
   return false;
}