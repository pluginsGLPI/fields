<?php

class %%CLASSNAME%%Injection extends %%CLASSNAME%% implements PluginDatainjectionInjectionInterface
{
    static $rightname = 'plugin_datainjection_model';

    /**
     * Return the table used to store this object
     *
     * @see CommonDBTM::getTable()
     *
     * @return string
    **/
    static function getTable($classname = null) {
        return getTableForItemType(get_parent_class(__CLASS__));
    }

    static function getTypeName($nb = 0) {
        $itemtype = %%ITEMTYPE%%;
        $container_name = %%CONTAINER_NAME%%;
        return $itemtype::getTypeName($nb) . " - " . $container_name;
    }

    /**
     * Tells datainjection if the type is a primary type or not
     *
     * @return boolean
    **/
    function isPrimaryType() {
        return false;
    }

    /**
     * Indicates with which other types it can be connected
     *
     * @return an array of GLPI types
    **/
    function connectedTo() {
        return [%%ITEMTYPE%%];
    }

    /**
     * Function which calls getSearchOptions and adds more parameters specific to display
     *
     * @param string $primary_type (default '')
     *
     * @return array of search options, as defined in each commondbtm object
    **/
    function getOptions($primary_type='') {
        $container_id = (int) %%CONTAINER_ID%%;

        $searchoptions = PluginFieldsContainer::getAddSearchOptions(%%ITEMTYPE%%, $container_id);

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
     * Standard method to add an object into GLPI
     *
     * @param $values    array fields to add into GLPI
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
