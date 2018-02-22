<?php

class %%CLASSNAME%%Injection extends %%CLASSNAME%% implements PluginDatainjectionInjectionInterface
{
   static $rightname = 'plugin_datainjection_model';

   /**
    * Return the table used to stor this object
    *
    * @see CommonDBTM::getTable()
    *
    * @return string
   **/
   static function getTable($classname = null) {
      return getTableForItemType(get_parent_class());
   }

   static function getTypeName($nb = 0) {
      return %%ITEMTYPE%%::getTypeName() . " - %%CONTAINER_NAME%%";
   }

   /**
    * Tells datainjection is the type is a primary type or not
    *
    * @return iboolean
   **/
   function isPrimaryType() {
      return false;
   }

   /**
    * Indicates to with other types it can be connected
    *
    * @return an array of GLPI types
   **/
   function connectedTo() {
      return array('%%ITEMTYPE%%');
   }

   /**
    * Function which calls getSearchOptions and add more parameters specific to display
    *
    * @param string $primary_type (default '')
    *
    * @return array of search options, as defined in each commondbtm object
   **/
   function getOptions($primary_type='') {
      $searchoptions = PluginFieldsContainer::getAddSearchOptions('%%ITEMTYPE%%', %%CONTAINER_ID%%);

      foreach ($searchoptions as $id => $data) {
         $searchoptions[$id]['injectable'] = PluginDatainjectionCommonInjectionLib::FIELD_INJECTABLE;
         if (!isset($searchoptions[$id]['displaytype'])) {
            if (isset($searchoptions[$id]['datatype'])) {
               $searchoptions[$id]['displaytype'] = $searchoptions[$id]['datatype'];
            } else {
               $searchoptions[$id]['displaytype'] = 'text';
            }
         }
      }

      return $searchoptions;
   }

   /**
    * Standard method to add an object into glpi
    *
    * @param $values    array fields to add into glpi
    * @param $options   array options used during creation
    *
    * @return array of IDs of newly created objects:
    * for example array(Computer=>1, Networkport=>10)
   **/
   function addOrUpdateObject($values=array(), $options=array()) {
      $lib = new PluginDatainjectionCommonInjectionLib($this, $values, $options);
      $lib->processAddOrUpdate();
      return $lib->getInjectionResults();
   }
}
