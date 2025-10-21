# Change Log

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).


## [UNRELEASED]

### Fixed

- Increased the maximum length of the language column to support longer locale codes

## [1.22.1] - 2025-10-10

### Fixed

- Fix UI with GLPI 11
- Fix `tab` container not displayed

## [1.22.0] - 2025-09-30

### Added

- GLPI 11 compatibility

### Fixed

- Fix  `massive action` for adding value to `multiple` dropdown fields
- Fix for the `COALESCE` part of the `SQL query` for `multiple` dropdowns
- Fix `search option` for `multiple` dropdown
- Fix display from Planning view
- Fix `classname` resolution when multiple `s` at end of container name


## [1.21.24] - 2025-10-10

### Fixed

- Fix  `massive action` for adding value to `multiple` dropdown fields
- Fix for the `COALESCE` part of the `SQL query` for `multiple` dropdowns
- Fix `search option` for `multiple` dropdown
- Fix display from Planning view
- Fix `classname` resolution when multiple `s` at end of container name


## [1.21.23] - 2025-08-26

### Fixed

- Fix undefined array key `multiple_dropdown_action` during import
- Fix incompatibility of `multiple` dropdowns with `massiveaction`
- Fix default value properly applied in multiple dropdown search options
- Fix `search option` for default values in `multiple` dropdown
- Fix shared dropdowns not displaying in `massiveaction`
- The field name was empty in the GLPI logs

### Added

- Add `replace` and `add` options in massive action for the multiple dropdowns fields

## [1.21.22] - 2025-05-28

### Fixed

- Fix condition check logic for dropdown field values
- Fix validation for mandatory multiple dropdown
- Fix `twig` error about undefined `dropdown_options`

## [1.21.21] - 2025-03-21

### Fixed

- Fix of GLPI native fields update for objects with `fields` containers.

## [1.21.20] - 2025-03-20

### Fixed

- Fix `numeric` field search
- Fix containers migration while adding `is_recursive` field
- Fix container update from other context (like plugins)
- Fix "not equals" search operator for dropdown `multiple`
- Fix container data (`entities_id`) insert from `dom` type

## [1.21.19] - 2025-02-03

### Fixed

- Fix container update from `API`
- Fix: fix default value for `dropdown` field to avoid empty dropdown

## [1.21.18] - 2024-01-16

### Fixed

- Fix `PluginFieldsContainerDisplayCondition` display when value is no more available.
- Fix issue where the value of custom fields could not be saved

## [1.21.17] - 2024-12-26

### Fixed

- Force decimal `datatype` of `numeric` fields  for more accurate display.
- Do not destroy the dropdown table/class if it is being used by another container.

## [1.21.16] - 2024-12-11

### Fixed

- Fix `container` to prevent calls from `API` returning full container data

## [1.21.15] - 2024-10-09

### Fixed

- Removes multiple document selection in a single field, as the core does not support it.

## [1.21.14] - 2024-10-02

### Fixed

- Fix call to get_parent_class() for PHP 8.3 (#839)
- Fix datatype for search (#836)

## [1.21.13] - 2024-09-12

### CVE-2024-45600

- Fix SQL injection

### Added

- Add ```ComputerVirtualMachine```

## [1.21.12] - 2024-09-06

### Fixed

- Fix handling of empty mandatory fields in generic objects.
- Fix massive update for dropdown (shared by several containers)

## [1.21.11] - 2024-07-10

### Fixed

- Fix ```strpslashes``` log error
- Update main item ```date_mod``` after updating additional fields
- Fix ```datainjection``` mapping error with additional fields

## [1.21.10] - 2024-06-11

### Fixed

- Prevent compatibility issues when importing with the Datainjection plugin (#798)

## [1.21.9] - 2024-06-11

### Fixed

- Fix dropdown recursion (#775)
- Fix list of allowed Search Option (#778)
- Fix(core): load plugin from CLI context
- Fix: multiple dropdown fields emptied when solution added (#795)


## [1.21.8] - 2024-02-22

### Fixed

- Fix crash about undefined array key


## [1.21.7] - 2024-02-22

### Added

- Display generic label for field if not available

### Fixed

- Fix search on dropdown ```multiple```
- Load all users from User dropdown
- Handle / save empty choice for dropdown
- Load overrides from related item if container is ```tab``` type
- Deactivate ```domtab``` that no longer works and handle ```ITILSolution``` (see https://github.com/pluginsGLPI/fields/pull/741)


## [1.6.1] - 2017-03-09

- Drop namespaces added in 1.6.0; GLPI is not ready
- Revert back to zend-loader

## [1.6.0] - 2017-03-03

- Use Fedora/Autoloader, add namespaces
- Normalize backslashes
- Fix permissions issues (cannot create containers or fields)

## [1.5.0] - 2017-01-27

**Compatible with GLPI 9.1.2 and above**

- Use post_item_form hook instead of javascript to display fields
- Fix (and limit) dom tab possibilities

## [1.4.5] - 2017-01-13
- Set minimal PHP version to 5.4
- Prevent values to be kept from a new ticket to another
- Do not check files if plugin is not initialized
- Limit to one domtab per tab

## [1.4.4] - 2017-01-11
- Fix issue on updating items on some cases
- Fix issue on creating item on some cases
- Fix cross plugin issue

## [1.4.3] - 2016-12-30

### Changed
- Fix plugin blocks item update
- Display translated headers
- Do notable used minified files when debug mode is active

## [1.4.2] - 2016-12-21

### Changed
- Fix field update
- Fix path issue on windows
- Fix profile restriction issues
- Fix display on classic and vsplitted views

## [1.4.1] - 2016-12-15

### Added
- Prepare translation on dropdown fields (inactive because of GLPI's issue #953)

### Changed
- Translate labels in search options
- Fix adding bloc for specific tab
- Fix dropdown pagination links
- Fix validation issue creating new tickets
- Fix checks consistency
- Ensure we target only the first tab

## [1.4.0] - 2016-12-13

### Added
- New URL field type.
- Labels translation for containers and fields.

### Changed
- Improve uninstallation when generated classes are missing.
- Update translations from [Transifex](https://www.transifex.com/teclib/glpi-plugin-plugin-fields)
- Fix self-service missing field on tickets
