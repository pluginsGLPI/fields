--
-- -------------------------------------------------------------------------
-- Fields plugin for GLPI
-- -------------------------------------------------------------------------
--
-- LICENSE
--
-- This file is part of Fields.
--
-- Fields is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- Fields is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with Fields. If not, see <http://www.gnu.org/licenses/>.
-- -------------------------------------------------------------------------
-- @copyright Copyright (C) 2013-2023 by Fields plugin team.
-- @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
-- @link      https://github.com/pluginsGLPI/fields
-- -------------------------------------------------------------------------
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_categories`;
CREATE TABLE `glpi_plugin_formcreator_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `comment` mediumtext COLLATE utf8mb4_unicode_ci,
  `completename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plugin_formcreator_categories_id` int unsigned NOT NULL DEFAULT '0',
  `level` int NOT NULL DEFAULT '1',
  `sons_cache` longtext COLLATE utf8mb4_unicode_ci,
  `ancestors_cache` longtext COLLATE utf8mb4_unicode_ci,
  `knowbaseitemcategories_id` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `name` (`name`),
  KEY `knowbaseitemcategories_id` (`knowbaseitemcategories_id`),
  KEY `plugin_formcreator_categories_id` (`plugin_formcreator_categories_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_questions`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_questions`;
CREATE TABLE `glpi_plugin_formcreator_questions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `plugin_formcreator_sections_id` int unsigned NOT NULL DEFAULT '0',
  `fieldtype` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `required` tinyint(1) NOT NULL DEFAULT '0',
  `show_empty` tinyint(1) NOT NULL DEFAULT '0',
  `default_values` mediumtext COLLATE utf8mb4_unicode_ci,
  `itemtype` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'itemtype used for glpi objects and dropdown question types',
  `values` mediumtext COLLATE utf8mb4_unicode_ci,
  `description` mediumtext COLLATE utf8mb4_unicode_ci,
  `row` int NOT NULL DEFAULT '0',
  `col` int NOT NULL DEFAULT '0',
  `width` int NOT NULL DEFAULT '0',
  `show_rule` int NOT NULL DEFAULT '1',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plugin_formcreator_sections_id` (`plugin_formcreator_sections_id`),
  FULLTEXT KEY `Search` (`name`,`description`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_sections`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_sections`;
CREATE TABLE `glpi_plugin_formcreator_sections` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `plugin_formcreator_forms_id` int unsigned NOT NULL DEFAULT '0',
  `order` int NOT NULL DEFAULT '0',
  `show_rule` int NOT NULL DEFAULT '1',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plugin_formcreator_forms_id` (`plugin_formcreator_forms_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_forms`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_forms`;
CREATE TABLE `glpi_plugin_formcreator_forms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `entities_id` int unsigned NOT NULL DEFAULT '0',
  `is_recursive` tinyint(1) NOT NULL DEFAULT '0',
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `icon_color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `background_color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `access_rights` tinyint(1) NOT NULL DEFAULT '1',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `plugin_formcreator_categories_id` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `language` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `helpdesk_home` tinyint(1) NOT NULL DEFAULT '0',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `validation_required` tinyint(1) NOT NULL DEFAULT '0',
  `usage_count` int NOT NULL DEFAULT '0',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `is_captcha_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `show_rule` int NOT NULL DEFAULT '1' COMMENT 'Conditions setting to show the submit button',
  `formanswer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_visible` tinyint NOT NULL DEFAULT '1',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `entities_id` (`entities_id`),
  KEY `plugin_formcreator_categories_id` (`plugin_formcreator_categories_id`),
  FULLTEXT KEY `Search` (`name`,`description`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_targettickets`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_targettickets`;
CREATE TABLE `glpi_plugin_formcreator_targettickets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `plugin_formcreator_forms_id` int unsigned NOT NULL DEFAULT '0',
  `target_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `source_rule` int NOT NULL DEFAULT '0',
  `source_question` int NOT NULL DEFAULT '0',
  `type_rule` int NOT NULL DEFAULT '0',
  `type_question` int unsigned NOT NULL DEFAULT '0',
  `tickettemplates_id` int unsigned NOT NULL DEFAULT '0',
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `due_date_rule` int NOT NULL DEFAULT '1',
  `due_date_question` int unsigned NOT NULL DEFAULT '0',
  `due_date_value` tinyint DEFAULT NULL,
  `due_date_period` int NOT NULL DEFAULT '0',
  `urgency_rule` int NOT NULL DEFAULT '1',
  `urgency_question` int unsigned NOT NULL DEFAULT '0',
  `validation_followup` tinyint(1) NOT NULL DEFAULT '1',
  `destination_entity` int NOT NULL DEFAULT '1',
  `destination_entity_value` int unsigned NOT NULL DEFAULT '0',
  `tag_type` int NOT NULL DEFAULT '1',
  `tag_questions` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tag_specifics` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category_rule` int NOT NULL DEFAULT '1',
  `category_question` int unsigned NOT NULL DEFAULT '0',
  `associate_rule` int NOT NULL DEFAULT '1',
  `associate_question` int unsigned NOT NULL DEFAULT '0',
  `location_rule` int NOT NULL DEFAULT '1',
  `location_question` int unsigned NOT NULL DEFAULT '0',
  `commonitil_validation_rule` int NOT NULL DEFAULT '1',
  `commonitil_validation_question` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_rule` int NOT NULL DEFAULT '1',
  `sla_rule` int NOT NULL DEFAULT '1',
  `sla_question_tto` int unsigned NOT NULL DEFAULT '0',
  `sla_question_ttr` int unsigned NOT NULL DEFAULT '0',
  `ola_rule` int NOT NULL DEFAULT '1',
  `ola_question_tto` int unsigned NOT NULL DEFAULT '0',
  `ola_question_ttr` int unsigned NOT NULL DEFAULT '0',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tickettemplates_id` (`tickettemplates_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_targets_actors`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_targets_actors`;
CREATE TABLE `glpi_plugin_formcreator_targets_actors` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `itemtype` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `items_id` int unsigned NOT NULL DEFAULT '0',
  `actor_role` int NOT NULL DEFAULT '1',
  `actor_type` int NOT NULL DEFAULT '1',
  `actor_value` int unsigned NOT NULL DEFAULT '0',
  `use_notification` tinyint(1) NOT NULL DEFAULT '1',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `item` (`itemtype`,`items_id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_targetchanges`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_targetchanges`;
CREATE TABLE `glpi_plugin_formcreator_targetchanges` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `plugin_formcreator_forms_id` int unsigned NOT NULL DEFAULT '0',
  `target_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `changetemplates_id` int unsigned NOT NULL DEFAULT '0',
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `impactcontent` longtext COLLATE utf8mb4_unicode_ci,
  `controlistcontent` longtext COLLATE utf8mb4_unicode_ci,
  `rolloutplancontent` longtext COLLATE utf8mb4_unicode_ci,
  `backoutplancontent` longtext COLLATE utf8mb4_unicode_ci,
  `checklistcontent` longtext COLLATE utf8mb4_unicode_ci,
  `due_date_rule` int NOT NULL DEFAULT '1',
  `due_date_question` int unsigned NOT NULL DEFAULT '0',
  `due_date_value` tinyint DEFAULT NULL,
  `due_date_period` int NOT NULL DEFAULT '0',
  `urgency_rule` int NOT NULL DEFAULT '1',
  `urgency_question` int unsigned NOT NULL DEFAULT '0',
  `validation_followup` tinyint(1) NOT NULL DEFAULT '1',
  `destination_entity` int NOT NULL DEFAULT '1',
  `destination_entity_value` int unsigned NOT NULL DEFAULT '0',
  `tag_type` int NOT NULL DEFAULT '1',
  `tag_questions` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tag_specifics` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category_rule` int NOT NULL DEFAULT '1',
  `category_question` int unsigned NOT NULL DEFAULT '0',
  `commonitil_validation_rule` int NOT NULL DEFAULT '1',
  `commonitil_validation_question` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_rule` int NOT NULL DEFAULT '1',
  `sla_rule` int NOT NULL DEFAULT '1',
  `sla_question_tto` int unsigned NOT NULL DEFAULT '0',
  `sla_question_ttr` int unsigned NOT NULL DEFAULT '0',
  `ola_rule` int NOT NULL DEFAULT '1',
  `ola_question_tto` int unsigned NOT NULL DEFAULT '0',
  `ola_question_ttr` int unsigned NOT NULL DEFAULT '0',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_targetproblems`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_targetproblems`;
CREATE TABLE `glpi_plugin_formcreator_targetproblems` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `plugin_formcreator_forms_id` int unsigned NOT NULL DEFAULT '0',
  `target_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `problemtemplates_id` int unsigned NOT NULL DEFAULT '0',
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `impactcontent` longtext COLLATE utf8mb4_unicode_ci,
  `causecontent` longtext COLLATE utf8mb4_unicode_ci,
  `symptomcontent` longtext COLLATE utf8mb4_unicode_ci,
  `urgency_rule` int NOT NULL DEFAULT '1',
  `urgency_question` int unsigned NOT NULL DEFAULT '0',
  `destination_entity` int NOT NULL DEFAULT '1',
  `destination_entity_value` int unsigned NOT NULL DEFAULT '0',
  `tag_type` int NOT NULL DEFAULT '1',
  `tag_questions` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tag_specifics` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category_rule` int NOT NULL DEFAULT '1',
  `category_question` int unsigned NOT NULL DEFAULT '0',
  `show_rule` int NOT NULL DEFAULT '1',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `problemtemplates_id` (`problemtemplates_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_forms_users`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_forms_users`;
CREATE TABLE `glpi_plugin_formcreator_forms_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_formcreator_forms_id` int unsigned NOT NULL,
  `users_id` int unsigned NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`plugin_formcreator_forms_id`,`users_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_forms_groups`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_forms_groups`;
CREATE TABLE `glpi_plugin_formcreator_forms_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_formcreator_forms_id` int unsigned NOT NULL,
  `groups_id` int unsigned NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`plugin_formcreator_forms_id`,`groups_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `glpi_plugin_formcreator_forms_groups`
--

LOCK TABLES `glpi_plugin_formcreator_forms_groups` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `glpi_plugin_formcreator_forms_profiles`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_forms_profiles`;
CREATE TABLE `glpi_plugin_formcreator_forms_profiles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_formcreator_forms_id` int unsigned NOT NULL DEFAULT '0',
  `profiles_id` int unsigned NOT NULL DEFAULT '0',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`plugin_formcreator_forms_id`,`profiles_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_items_targettickets`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_items_targettickets`;
CREATE TABLE `glpi_plugin_formcreator_items_targettickets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_formcreator_targettickets_id` int unsigned NOT NULL DEFAULT '0',
  `link` int NOT NULL DEFAULT '0',
  `itemtype` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `items_id` int unsigned NOT NULL DEFAULT '0',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plugin_formcreator_targettickets_id` (`plugin_formcreator_targettickets_id`),
  KEY `item` (`itemtype`,`items_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `glpi_plugin_formcreator_items_targettickets`
--

LOCK TABLES `glpi_plugin_formcreator_items_targettickets` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `glpi_plugin_formcreator_forms_profiles`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_forms_profiles`;
CREATE TABLE `glpi_plugin_formcreator_forms_profiles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_formcreator_forms_id` int unsigned NOT NULL DEFAULT '0',
  `profiles_id` int unsigned NOT NULL DEFAULT '0',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`plugin_formcreator_forms_id`,`profiles_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_forms_groups`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_forms_groups`;
CREATE TABLE `glpi_plugin_formcreator_forms_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_formcreator_forms_id` int unsigned NOT NULL,
  `groups_id` int unsigned NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`plugin_formcreator_forms_id`,`groups_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Dumping data for table `glpi_plugin_formcreator_forms_groups`
--

LOCK TABLES `glpi_plugin_formcreator_forms_groups` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `glpi_plugin_formcreator_forms_users`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_forms_users`;
CREATE TABLE `glpi_plugin_formcreator_forms_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_formcreator_forms_id` int unsigned NOT NULL,
  `users_id` int unsigned NOT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`plugin_formcreator_forms_id`,`users_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_forms_languages`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_forms_languages`;
CREATE TABLE `glpi_plugin_formcreator_forms_languages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_formcreator_forms_id` int unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_conditions`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_conditions`;
CREATE TABLE `glpi_plugin_formcreator_conditions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `itemtype` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'itemtype of the item affected by the condition',
  `items_id` int unsigned NOT NULL DEFAULT '0' COMMENT 'item ID of the item affected by the condition',
  `plugin_formcreator_questions_id` int unsigned NOT NULL DEFAULT '0' COMMENT 'question to test for the condition',
  `show_condition` int NOT NULL DEFAULT '0',
  `show_value` mediumtext COLLATE utf8mb4_unicode_ci,
  `show_logic` int NOT NULL DEFAULT '1',
  `order` int NOT NULL DEFAULT '1',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plugin_formcreator_questions_id` (`plugin_formcreator_questions_id`),
  KEY `item` (`itemtype`,`items_id`)
) ENGINE=InnoDB AUTO_INCREMENT=825 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_questionranges`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_questionranges`;
CREATE TABLE `glpi_plugin_formcreator_questionranges` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_formcreator_questions_id` int unsigned NOT NULL DEFAULT '0',
  `range_min` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `range_max` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fieldname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plugin_formcreator_questions_id` (`plugin_formcreator_questions_id`)
) ENGINE=InnoDB AUTO_INCREMENT=304 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `glpi_plugin_formcreator_questionregexes`
--

DROP TABLE IF EXISTS `glpi_plugin_formcreator_questionregexes`;
CREATE TABLE `glpi_plugin_formcreator_questionregexes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_formcreator_questions_id` int unsigned NOT NULL DEFAULT '0',
  `regex` mediumtext COLLATE utf8mb4_unicode_ci,
  `fieldname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plugin_formcreator_questions_id` (`plugin_formcreator_questions_id`)
) ENGINE=InnoDB AUTO_INCREMENT=297 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

CREATE TABLE `glpi_plugin_formcreator_entityconfigs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `entities_id` int(10) unsigned NOT NULL DEFAULT 0,
  `replace_helpdesk` int(11) NOT NULL DEFAULT -2,
  `default_form_list_mode` int(11) NOT NULL DEFAULT -2,
  `sort_order` int(11) NOT NULL DEFAULT -2,
  `is_kb_separated` int(11) NOT NULL DEFAULT -2,
  `is_search_visible` int(11) NOT NULL DEFAULT -2,
  `is_dashboard_visible` int(11) NOT NULL DEFAULT -2,
  `is_header_visible` int(11) NOT NULL DEFAULT -2,
  `is_search_issue_visible` int(11) NOT NULL DEFAULT -2,
  `tile_design` int(11) NOT NULL DEFAULT -2,
  `home_page` int(11) NOT NULL DEFAULT -2,
  `is_category_visible` int(11) NOT NULL DEFAULT -2,
  `is_folded_menu` int(11) NOT NULL DEFAULT -2,
  `header` text DEFAULT NULL,
  `service_catalog_home` int(11) NOT NULL DEFAULT -2,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`entities_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC
