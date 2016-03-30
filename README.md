The fields plugin allows you to add custom fields on glpi types : tickets, computers, users...  
Directories "inc", "front" and "ajax" should be available in writing for apache user

Addionnal data can be added : 
 * In object tab
 * In main form of object (above save button)
 * In form of a tab (Warning, this feature is experimental)

Possible fields type are : 
 * Header (title bloc)
 * Text (single line)
 * Text (multiple lines)
 * Number
 * Dropdown (always a tree dropdown)
 * Yes / No
 * Date
 * Date / Hour
 * Glpi User list

There is a [migration script](https://github.com/pluginsGLPI/customfields/blob/master/scripts/migrate-to-fields.php) from "customfields" plugin.  
**WARNING : this one is experimental and deserved to be more tested. We strongly advise you to backup your data before using it**


Documentation
=============

For 0.84 plugin : https://github.com/pluginsGLPI/fields/blob/0.84/bugfixes/documentation/doc_plugin_fields.asciidoc
