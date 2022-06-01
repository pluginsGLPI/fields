<?php

abstract class PluginFieldsContainerTemplate extends CommonDBTM
{
    abstract public static function get_ITEMTYPE(): string;

    abstract public static function get_CONTAINER(): string;

    public static function install($containers_id = 0)
    {
        global $DB;

        $default_charset = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

        $obj = new static();
        $table = $obj->getTable();

        // create Table
        $itemtype =  static::get_ITEMTYPE();
        $container =  static::get_CONTAINER();
        if (!$DB->tableExists($table)) {
             $query = "CREATE TABLE IF NOT EXISTS `$table` (
                `id`                               INT          {$default_key_sign} NOT NULL auto_increment,
                `items_id`                         INT          {$default_key_sign} NOT NULL,
                `itemtype`                         VARCHAR(255) DEFAULT '$itemtype',
                `plugin_fields_containers_id`      INT          {$default_key_sign} NOT NULL DEFAULT '$container',
                PRIMARY KEY                        (`id`),
                UNIQUE INDEX `itemtype_item_container`
                    (`itemtype`, `items_id`, `plugin_fields_containers_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->query($query) or die($DB->error());
        }

        // and its fields
        if ($containers_id) {
            foreach (
                $DB->request(PluginFieldsField::getTable(), [
                    'plugin_fields_containers_id' => $containers_id
                ]) as $field
            ) {
                static::addField($field['name'], $field['type']);
            }
        }
    }

    public static function uninstall()
    {
        global $DB;

        $obj = new static();
        return $DB->query("DROP TABLE IF EXISTS `" . $obj->getTable() . "`");
    }

    public static function addField($fieldname, $type)
    {
        $migration = new PluginFieldsMigration(0);

        $sql_fields = PluginFieldsMigration::getSQLFields($fieldname, $type);
        foreach ($sql_fields as $sql_field_name => $sql_field_type) {
            $migration->addField(static::getTable(), $sql_field_name, $sql_field_type);
        }

        $migration->migrationOneTable(static::getTable());
    }

    public static function removeField($fieldname, $type)
    {
        $migration = new PluginFieldsMigration(0);

        $sql_fields = PluginFieldsMigration::getSQLFields($fieldname, $type);
        foreach (array_keys($sql_fields) as $sql_field_name) {
            $migration->dropField(static::getTable(), $sql_field_name);
        }

        $migration->migrationOneTable(static::getTable());
    }

    public static function unsetUndisclosedFields(&$fields)
    {
        parent::unsetUndisclosedFields($fields);

        $base_fields = [
            "id",
            "items_id",
            "itemtype",
            "plugin_fields_containers_id",
        ];

        // Remove deleted fields
        foreach ($fields as $field => $value) {
            if (in_array($field, $base_fields)) {
                // Not a custom field; skip
                continue;
            }

            $custom_fields = new PluginFieldsField();

            if (preg_match("/plugin_fields_(.*)dropdowns_id/", $field, $matches)) {
                // Field is a dropdown
                $data = $custom_fields->find([
                    'name'                        => $matches[1],
                    'plugin_fields_containers_id' => $fields['plugin_fields_containers_id'],
                    'type'                        => "dropdown",
                    'is_active'                   => 1,
                ]);

                // This dropdown was deleted, remove it from results
                if (count($data) == 0) {
                    unset($fields[$field]);
                }
            } else {
                // Normal field
                $data = $custom_fields->find([
                    'name'                        => $field,
                    'plugin_fields_containers_id' => $fields['plugin_fields_containers_id'],
                    'is_active'                   => 1,
                ]);

                // This field was deleted, remove it from results
                if (count($data) == 0) {
                    unset($fields[$field]);
                }
            }
        }
    }
}
