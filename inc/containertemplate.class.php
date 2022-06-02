<?php

abstract class PluginFieldsContainerTemplate extends CommonDBTM
{
    abstract public static function get_ITEMTYPE(): string;

    abstract public static function get_CONTAINER(): string;

    public static function install()
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
}
