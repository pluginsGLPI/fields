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

   static function getItemtypes() {
      global $LANG;

      return array(
         'computer'           => $LANG['Menu'][0],
         'networkequipment'   => $LANG['Menu'][1],
         'printer'            => $LANG['Menu'][2],
         'monitor'            => $LANG['Menu'][3],
         'software'           => $LANG['Menu'][4],
         'ticket'             => $LANG['Menu'][5],
         'user'               => $LANG['Menu'][14],
         'cartridgeitem'      => $LANG['Menu'][21],
         'contact'            => $LANG['Menu'][22],
         'supplier'           => $LANG['Menu'][23],
         'contract'           => $LANG['Menu'][25],
         'document'           => $LANG['Menu'][27],
         'state'              => $LANG['Menu'][28],
         'consumableitem'     => $LANG['Menu'][32],
         'phone'              => $LANG['Menu'][34],
         'profile'            => $LANG['Menu'][35],
         'group'              => $LANG['Menu'][36],
         'entity'             => $LANG['Menu'][37]
      );
   }
}