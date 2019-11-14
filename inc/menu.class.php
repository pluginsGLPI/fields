<?php
class PluginFieldsMenu extends CommonGLPI {
   static $rightname = 'entity';

   static function getMenuName() {
      return __("Additionnal fields", "fields");
   }

   static function getMenuContent() {

      if (!Session::haveRight('entity', READ)) {
         return;
      }

      $front_fields = Plugin::getPhpDir('fields', false)."/front";
      $menu = [
         'title' => self::getMenuName(),
         'page'  =>  "$front_fields/container.php",
         'icon'  => PluginFieldsContainer::getIcon(),
      ];

      $itemtypes = ['PluginFieldsContainer' => 'fieldscontainer'];

      foreach ($itemtypes as $itemtype => $option) {
         $menu['options'][$option] = [
            'title' => $itemtype::getTypeName(2),
            'page'  => $itemtype::getSearchURL(false),
            'links' => [
               'search' => $itemtype::getSearchURL(false)
            ]
         ];

         if ($itemtype::canCreate()) {
            $menu['options'][$option]['links']['add'] = $itemtype::getFormURL(false);
         }

      }
      return $menu;
   }


}