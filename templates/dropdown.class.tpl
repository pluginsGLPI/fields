<?php

class %%CLASSNAME%% extends CommonTreeDropdown {
   public $field_name      = "%%FIELDNAME%%";
   public $can_be_translated = true;

   static function getTypeName($nb=0) {
      $item = [
         "itemtype" => PluginFieldsField::getType(),
         "id"       => "%%FIELDID%%",
         "label"    => "%%LABEL%%"
      ];
      $label = PluginFieldsLabelTranslation::getLabelFor($item);
      return $label;
   }

   static function install() {
      global $DB;

      $obj = new self();
      $table = $obj->getTable();

      if (!$DB->tableExists($table)) {
         $query = "CREATE TABLE IF NOT EXISTS `$table` (
                  `id`                                      INT(11)        NOT NULL auto_increment,
                  `name`                                    VARCHAR(255)   DEFAULT NULL,
                  `completename`                            TEXT           DEFAULT NULL,
                  `comment`                                 TEXT           DEFAULT NULL,
                  `plugin_fields_%%FIELDNAME%%dropdowns_id` INT(11)        DEFAULT NULL,
                  `level`                                   INT(11)        DEFAULT NULL,
                  `ancestors_cache`                         TEXT           DEFAULT NULL,
                  `sons_cache`                              TEXT           DEFAULT NULL,
                  `entities_id`                             INT(11)        NOT NULL DEFAULT '0',
                  `is_recursive`                            TINYINT(1)     NOT NULL DEFAULT '0',
                  PRIMARY KEY                               (`id`),
                  KEY                                       `entities_id`  (`entities_id`),
                  KEY                                       `is_recursive` (`is_recursive`),
                  KEY                                       `plugin_fields_%%FIELDNAME%%dropdowns_id`
                                                            (`plugin_fields_%%FIELDNAME%%dropdowns_id`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
         $DB->query($query) or die ($DB->error());
      }
   }

   static function uninstall() {
      global $DB;

      $obj = new self();
      return $DB->query("DROP TABLE IF EXISTS `".$obj->getTable()."`");
   }

   /**
    * Get the search page URL for the current classe
    *
    * @param $full path or relative one (true by default)
   **/
   static function getTabsURL($full=true) {
      $url = Toolbox::getItemTypeTabsURL('PluginFieldsCommonDropdown', $full);
      $plug = isPluginItemType(get_called_class());
      $url .= '?ddtype=' . strtolower($plug['class']);
      return $url;
   }

   /**
    * Get the search page URL for the current class
    *
    * @param $full path or relative one (true by default)
   **/
   static function getSearchURL($full=true) {
      $url = Toolbox::getItemTypeSearchURL('PluginFieldsCommonDropdown', $full);
      $plug = isPluginItemType(get_called_class());
      $url .= '?ddtype=' . strtolower($plug['class']);
      return $url;
   }

   /**
    * Get the form page URL for the current class
    *
    * @param $full path or relative one (true by default)
   **/
   static function getFormURL($full=true) {
      $url = Toolbox::getItemTypeFormURL('PluginFieldsCommonDropdown', $full);
      $plug = isPluginItemType(get_called_class());
      $url .= '?ddtype=' . strtolower($plug['class']);
      return $url;
   }

   /**
    * Get the form page URL for the current class and point to a specific ID
    *
    * @param $id      (default 0)
    * @param $full    path or relative one (true by default)
    *
    * @since version 0.90
   **/
   static function getFormURLWithID($id=0, $full=true) {

      $link     = self::getFormURL($full);
      $link    .= '&id=' . $id;
      return $link;
   }

   /**
    * Get default values to search engine to override
   **/
   static function getDefaultSearchRequest() {
      $plug = isPluginItemType(get_called_class());
      $search = ['addhidden' => ['ddtype' => strtolower($plug['class'])]];
      return $search;
   }
}
