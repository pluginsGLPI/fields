<?php

class PluginFieldsDropdownBase extends CommonTreeDropdown {
   
   //function mayBeVisible() {

   //    if (!isset($this->fields['id'])) {
   //        $this->getEmpty();
   //    }
   //    return array_key_exists('is_visible', $this->fields);
   //}
       
   function getAdditionalFields() {
       global $LANG;

       $val = parent::getAdditionalFields() ;
       //if( $this->mayBeVisible( ) ) {
           $val[] = array('name'  => 'is_visible',
                          'label' => $LANG['group'][0],
                          'type'  => 'bool',
                          'list'  => false) ;
       //}
           
       return $val;
   }

   function getSearchOptions() {
      global $LANG;
      
      $val = parent::getSearchOptions( ) ;
      
      //if( $this->mayBeVisible( ) ) {
          $val[] = array( 'table'   => $this->getTable(),
                      'field'   => 'is_visible',
                      'name'    => $LANG['group'][0],
                      'datatype' => 'bool') ;
      //}
      
      return $val ;
   }
   
}