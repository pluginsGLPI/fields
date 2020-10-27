<?php

class PluginFieldsInventory extends CommonDBTM {


   static function updateInventory($params = []) {

      if (!empty($params)
         && isset($params['inventory_data']) && !empty($params['inventory_data'])) {

         $availaibleItemType = ["Computer","Printer","NetworkEquipment"];
         foreach ($params['inventory_data'] as $itemtype => $inventories) {

            if (in_array($itemtype, $availaibleItemType)) {
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

               //load inventory from DB because
               //FI not update XML file if computer is not update
               if ($itemtype == Computer::getType()) {
                  $db_info = new PluginFusioninventoryInventoryComputerComputer();
                  if ($db_info->getFromDBByCrit(['computers_id' => $items_id])) {

                     $arrayinventory = unserialize(gzuncompress($db_info->fields['serialized_inventory']));
                     if (isset($arrayinventory['custom'])) {
                        self::updateFields($arrayinventory['custom']['container'], $itemtype, $items_id);
                     }
                  }
                  //Load XML file because FI always update XML file and don't store inventory into DB
               } else {
                  $file = self::loadXMLFile($itemtype, $items_id);
                  if ($file !== false) {
                     $arrayinventory = PluginFusioninventoryFormatconvert::XMLtoArray($file);
                     if (isset($arrayinventory['CUSTOM'])) {
                        self::updateFields($arrayinventory['CUSTOM']['CONTAINER'], $itemtype, $items_id);
                     }
                  }
               }
            }
         }
      }
   }

   static function updateFields($containersData, $itemtype, $items_id) {
      if (isset($containersData['ID'])) {
         // $containersData contains only one element, encapsulate it into an array
         $containersData = [$containersData];
      }
      foreach ($containersData as $key => $containerData) {
         $container = new PluginFieldsContainer();
         $container->getFromDB($containerData['ID']);
         $data = [];
         $data["items_id"] = $items_id;
         $data["itemtype"] = $itemtype;
         $data["plugin_fields_containers_id"] = $containerData['ID'];
         foreach ($containerData['FIELDS'] as $key => $value) {
            $data[strtolower($key)] = $value;
         }
         $container->updateFieldsValues($data, $itemtype, false);
      }
   }

   static function loadXMLFile($itemtype, $items_id) {

      $pxml     = false;
      $folder = substr($items_id, 0, -1);
      if (empty($folder)) {
         $folder = '0';
      }

      //Check if the file exists with the .xml extension (new format)
      $file = PLUGIN_FUSIONINVENTORY_XML_DIR . strtolower($itemtype) . "/" . $folder . "/" . $items_id;
      if (file_exists($file . '.xml')) {
         $file .= '.xml';
      } else if (!file_exists($file)) {
         return false;
      }
      $pxml = simplexml_load_file($file, 'SimpleXMLElement', LIBXML_NOCDATA);
      return $pxml;
   }

}
