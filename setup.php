<?php
// Init the hooks of the plugins -Needed
function plugin_init_fields() {
   global $PLUGIN_HOOKS, $LANG;

   $menu_entry   = "front/config.php";
   if ((!isset($_SESSION['glpiactiveprofile']['config']) 
      || $_SESSION['glpiactiveprofile']['config'] != "w")
   ) $menu_entry  = false;

   $PLUGIN_HOOKS['menu_entry']['fields']  = $menu_entry;
   $PLUGIN_HOOKS['config_page']['fields'] = $menu_entry;

   $PLUGIN_HOOKS['submenu_entry']['fields']['options']['config'] = array(
      'title' => $LANG['fields']['title'][2],
      'page'  =>'/plugins/fields/front/config.php',
   );

   $PLUGIN_HOOKS['submenu_entry']['fields']['options']['field'] = array(
      'title' => $LANG['fields']['config']['fields'],
      'page'  =>'/plugins/fields/front/field.php',
      'links' => array(
         'search' => '/plugins/fields/front/field.php',
         'add'    =>'/plugins/fields/front/field.form.php',
         'config'    =>'/plugins/fields/front/config.php'
   ));

   $PLUGIN_HOOKS['submenu_entry']['fields']['options']['container'] = array(
      'title' => $LANG['fields']['config']['containers'],
      'page'  =>'/plugins/fields/front/container.php',
      'links' => array(
         'search' => '/plugins/fields/front/container.php',
         'add'    =>'/plugins/fields/front/container.form.php',
         'config'    =>'/plugins/fields/front/config.php'
   ));

   $PLUGIN_HOOKS['add_css']['fields'][] = 'fields.css';
}


// Get the name and the version of the plugin - Needed
function plugin_version_fields() {
   global $LANG;
   return array ('name'           => $LANG["fields"]["title"][1],
                 'version'        => '1.0',
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