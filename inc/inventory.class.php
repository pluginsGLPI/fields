<?php

class PluginFieldsInventory extends CommonDBTM {


   static function updateInventory($params = []) {

      if (!empty($params)
         && isset($params['inventory_data']) && !empty($params['inventory_data'])) {

         $availaibleItemType = ["Computer","Printer","NetworkEquipment"];
         foreach($params['inventory_data'] as $itemtype => $inventories){

            if(in_array($itemtype,$availaibleItemType)){
               //retrive items id switch itemtype
               switch ($itemtype) {
                  case Computer::getType():
                     $items_id = $params['computers_id'];
                     $module = 'fusioninventorycomputer';
                     break;

                     case NetworkEquipment::getType():
                     $items_id = $params['networkequipments_id'];
                     $module = 'PluginFusioninventoryNetworkEquipment';
                     break;

                     case Printer::getType():
                     $items_id = $params['printers_id'];
                     $module = 'PluginFusioninventoryNetworkEquipment';
                     break;
               }



               //$serialized = gzcompress(serialize($params['inventory_data'][$module]['serialized_inventory']));

               //load XML
               $file = self::loadXMLFile($itemtype, $items_id);
               if($file !== false){

                  $arrayinventory = PluginFusioninventoryFormatconvert::XMLtoArray($file);
                  $dataContainers = [];
                  $customData = [];
                  $haveCustomField = false;


                  if($itemtype == Computer::getType()){
                     if(isset($arrayinventory['CONTENT']['CUSTOM'])){
                        $haveCustomField = true;
                        $customData = $arrayinventory['CONTENT']['CUSTOM'];
                     }
                  }else{
                     if(isset($arrayinventory['CUSTOM'])){
                        $haveCustomField = true;
                        $customData = $arrayinventory['CUSTOM'];
                     }
                  }

                  //manage custom fields
                  if($haveCustomField){
                     foreach ($customData as $key => $dataContainers) {

                        $container = new PluginFieldsContainer();
                        $container->getFromDB($dataContainers['ID']);

                        $data = [];
                        $data["items_id"] = $items_id;
                        $data["itemtype"] = $itemtype;
                        $data["plugin_fields_containers_id"] = $dataContainers['ID'];
                        foreach ($dataContainers['FIELDS'] as $key => $value) {
                           $data[strtolower($key)] = $value;
                        }
                        $container->updateFieldsValues($data, $itemtype, false);                        # code...
                     }
                  }
               }
            }
         }
      }
   }

   static function loadXMLFile($itemtype, $items_id){

      $pxml     = false;
      $folder = substr($items_id, 0, -1);
      if (empty($folder)) {
         $folder = '0';
      }

      //Check if the file exists with the .xml extension (new format)
      $file           = PLUGIN_FUSIONINVENTORY_XML_DIR;
      $filename       = $items_id.'.xml';
      $file_shortname = strtolower($itemtype)."/".$folder."/".$filename;
      $file          .= $file_shortname;
      if (!file_exists($file)) {
         //The file doesn't exists, check without the extension (old format)
         $file           = PLUGIN_FUSIONINVENTORY_XML_DIR;
         $filename       = $items_id;
         $file_shortname = strtolower($itemtype)."/".$folder."/".$filename;
         $file          .= $file_shortname;
         if (file_exists($file)) {
            $pxml = @simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA);
         }
      } else {
         $pxml = @simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA);
      }
      return $pxml;
   }

}
