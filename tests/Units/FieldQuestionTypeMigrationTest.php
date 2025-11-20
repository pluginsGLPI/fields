<?php

/**
 * -------------------------------------------------------------------------
 * Fields plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Fields.
 *
 * Fields is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Fields is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Fields. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2013-2023 by Fields plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/fields
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Field\Tests\Units;

use Glpi\Form\AccessControl\FormAccessControlManager;
use Glpi\Form\Migration\FormMigration;
use Glpi\Form\Question;
use Glpi\Migration\PluginMigrationResult;
use GlpiPlugin\Field\Tests\QuestionTypeTestCase;
use PluginFieldsQuestionType;

final class FieldQuestionTypeMigrationTest extends QuestionTypeTestCase
{
    public static function setUpBeforeClass(): void
    {
        global $DB;

        $tables = $DB->listTables('glpi\_plugin\_formcreator\_%');
        foreach ($tables as $table) {
            $DB->dropTable($table['TABLE_NAME']);
        }

        $queries = $DB->getQueriesFromFile(sprintf(
            '%s/plugins/fields/tests/fixtures/formcreator.sql',
            GLPI_ROOT,
        ));
        foreach ($queries as $query) {
            $DB->doQuery($query);
        }
    }

    public static function tearDownAfterClass(): void
    {
        global $DB;

        $tables = $DB->listTables('glpi\_plugin\_formcreator\_%');
        foreach ($tables as $table) {
            $DB->dropTable($table['TABLE_NAME']);
        }
    }

    public function testFieldsQuestionIsMigrated(): void
    {
        global $DB;

        $this->login();

        $question_name = 'GLPI item fields question';

        // Create a form
        $this->assertTrue($DB->insert(
            'glpi_plugin_formcreator_forms',
            [
                'name' => $question_name,
            ],
        ));
        $form_id = $DB->insertId();

        // Insert a section for the form
        $this->assertTrue($DB->insert(
            'glpi_plugin_formcreator_sections',
            [
                'plugin_formcreator_forms_id' => $form_id,
            ],
        ));

        $section_id = $DB->insertId();

        // Insert a question for the form
        $this->assertTrue($DB->insert(
            'glpi_plugin_formcreator_questions',
            [
                'name'                           => $question_name,
                'plugin_formcreator_sections_id' => $section_id,
                'fieldtype'                      => 'fields',
                'row'                            => 0,
                'col'                            => 0,
                'values'                         => json_encode([
                    'dropdown_fields_field' => $this->field->fields['name'],
                    'blocks_field'          => $this->block->getID(),
                ]),
            ],
        ));

        // Process migration
        $migration = new FormMigration($DB, FormAccessControlManager::getInstance());
        $this->setPrivateProperty($migration, 'result', new PluginMigrationResult());
        $this->assertTrue($this->callPrivateMethod($migration, 'processMigration'));

        // Verify that the question has been migrated correctly
        /** @var Question $question */
        $question = getItemByTypeName(Question::class, $question_name);
        $question_type = $question->getQuestionType();
        $this->assertInstanceOf(PluginFieldsQuestionType::class, $question_type);
    }
}
