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
                     break;

                     case NetworkEquipment::getType():
                     $items_id = $params['networkequipments_id'];
                     break;

                     case Printer::getType():
                     $items_id = $params['printers_id'];
                     break;
               }

               //load item
               $item = new $itemtype();
               $item->getFromDB($items_id);

               //load XML
               $file = self::loadXMLFile($itemtype, $items_id);
               if($file !== false){

                  $arrayinventory = PluginFusioninventoryFormatconvert::XMLtoArray($file);
                  $dataContainers = [];
                  $haveCustomField = false;

                  //retrieve custom field here $arrayinventory['CONTENT']['CUSTOM'] for Computer
                  if($itemtype == Computer::getType()){
                     if(isset($arrayinventory['CONTENT']['CUSTOM'])){
                        $haveCustomField = true;
                        $dataContainers = $arrayinventory['CONTENT']['CUSTOM']['CONTAINERS'];
                     }
                  //retrieve custom field here $arrayinventory['CONTENT']['CUSTOM'] for Printer and NetworkEquipment
                  }else{
                     if(isset($arrayinventory['CUSTOM'])){
                        $haveCustomField = true;
                        $dataContainers = $arrayinventory['CUSTOM']['CONTAINERS'];
                     }
                  }

                  //manage custom fields
                  if($haveCustomField){
                     $container = new PluginFieldsContainer();
                     $container->getFromDB($dataContainers['ID']);

                     $data = [];
                     $data["items_id"] = $items_id;
                     $data["itemtype"] = $itemtype;
                     $data["plugin_fields_containers_id"] = $dataContainers['ID'];
                     foreach ($dataContainers['FIELDS'] as $key => $value) {
                        $data[strtolower($key)] = $value;
                     }

                     $container->updateFieldsValues($data, $itemtype, false);
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
