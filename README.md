# Fields GLPI plugin

The fields plugin allows you to add custom fields on glpi types : tickets, computers, users...

Addionnal data can be added :
 * In object tab
 * In main form of object (above save button)
 * In form of a tab (Warning, this feature is experimental)

Possible fields type are :
 * Header (title bloc)
 * Text (single line)
 * Text (multiple lines)
 * Number
 * URL
 * Dropdown (always a tree dropdown)
 * Yes / No
 * Date
 * Date / Hour
 * Glpi User list

There is a [migration script](https://github.com/pluginsGLPI/customfields/blob/master/scripts/migrate-to-fields.php) from "customfields" plugin.  
**WARNING : this one is experimental and deserved to be more tested. We strongly advise you to backup your data before using it**


## Documentation

http://glpi-plugins.rtfd.io/en/latest/fields/index.html

## Contributing

* Open a ticket for each bug/feature so it can be discussed
* Follow [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins.html)
* Refer to [GitFlow](http://git-flow.readthedocs.io/) process for branching
* Work on a new branch on your own fork
* Open a PR that will be reviewed by a developer
