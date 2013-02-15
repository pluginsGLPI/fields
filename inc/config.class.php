<?php

class PluginFieldsConfig {
   
   static function show() {
      global $LANG;

      echo "<div class='custom_center'><ul class='custom_config'>";
      echo "<li onclick='location.href=\"field.php\"'>
         <img src='../pics/field.png' />
         <p><a>".$LANG['fields']['config']['fields']."</a></p></li>";
      echo "<li onclick='location.href=\"container.php\"'>
         <img src='../pics/container.png' />
         <p><a>".$LANG['fields']['config']['containers']."</a></p></li>";
      echo "</ul><div class='custom_clear'></div></div>";
   }
}