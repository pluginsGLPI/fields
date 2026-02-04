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

use CommonITILObject;
use Glpi\Form\AnswersHandler\AnswersHandler;
use Glpi\Form\Destination\CommonITILField\SimpleValueConfig;
use Glpi\Form\Destination\FormDestinationProblem;
use Glpi\Form\Form;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Tests\AbstractDestinationFieldTest;
use Glpi\Tests\FormBuilder;
use Glpi\Tests\FormTesterTrait;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use Group;
use Location;
use Override;
use PluginFieldsContainer;
use PluginFieldsDestinationField;
use PluginFieldsQuestionType;
use Problem;
use Ticket;
use User;

include_once __DIR__ . '/../../../../tests/abstracts/AbstractDestinationFieldTest.php';

final class FieldDestinationFieldTest extends AbstractDestinationFieldTest
{
    use FormTesterTrait;
    use FieldTestTrait;

    private array $blocks = [];

    private array $fields = [];

    private function initFieldTest(): void
    {
        $this->blocks[Ticket::class] = $this->createFieldContainer([
            'label'        => 'Ticket additional fields',
            'type'        => 'dom',
            'itemtypes'   => [Ticket::class],
            'is_active'   => 1,
            'entities_id' => $this->getTestRootEntity(true),
        ]);
        $this->blocks[Problem::class] = $this->createFieldContainer([
            'label'        => 'Problem additional fields',
            'type'        => 'dom',
            'itemtypes'   => [Problem::class],
            'is_active'   => 1,
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        $this->fields[] = $this->createField([
            'label'                                     => 'Short text',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $this->blocks[Ticket::class]->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $this->fields[] = $this->createField([
            'label'                                     => 'GLPI Item',
            'type'                                      => 'glpi_item',
            PluginFieldsContainer::getForeignKeyField() => $this->blocks[Ticket::class]->getID(),
            'ranking'                                   => 2,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
            'allowed_values'                            => [User::class, Group::class],
        ]);
        $this->fields[] = $this->createField([
            'label'                                     => 'Short text',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $this->blocks[Problem::class]->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $this->fields[] = $this->createField([
            'label'                                     => 'GLPI Item',
            'type'                                      => 'glpi_item',
            PluginFieldsContainer::getForeignKeyField() => $this->blocks[Problem::class]->getID(),
            'ranking'                                   => 2,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
            'allowed_values'                            => [User::class, Group::class],
        ]);
        $this->fields[] = $this->createField([
            'label'                                     => 'Location Field',
            'type'                                      => 'dropdown-Location',
            PluginFieldsContainer::getForeignKeyField() => $this->blocks[Ticket::class]->getID(),
            'ranking'                                   => 3,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
    }

    public function setUp(): void
    {
        $this->initFieldTest();
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->tearDownFieldTest();
    }

    public function testDestinationWithNoAdditionalFields(): void
    {
        $builder = (new FormBuilder())->addQuestion("Short text", QuestionTypeShortText::class);
        $form = $this->createForm($builder);

        $this->sendFormAndAssertITILObjectAdditionalFields(
            form: $form,
            config: new SimpleValueConfig(1),
            answers: [
                "Short text" => "Test value",
            ],
            expected_field_values: [Ticket::class => []],
        );
    }

    public function testDestinationWithAdditionalFields(): void
    {
        $this->login();
        $form = $this->createAndGetFormWithMultipleFieldQuestions();

        $this->sendFormAndAssertITILObjectAdditionalFields(
            form: $form,
            config: new SimpleValueConfig(1),
            answers: [
                "Field 1" => "Text value",
                "Field 2" => [
                    'itemtype' => User::class,
                    'items_id' => getItemByTypeName(User::class, TU_USER, true),
                ],
                "Field 3" => "Problem text",
                "Field 4" => [
                    'itemtype' => User::class,
                    'items_id' => getItemByTypeName(User::class, TU_USER, true),
                ],
            ],
            expected_field_values: [
                Ticket::class => [
                    $this->fields[0]->fields['name']               => 'Text value',
                    'itemtype_' . $this->fields[1]->fields['name'] => User::class,
                    'items_id_' . $this->fields[1]->fields['name'] => getItemByTypeName(User::class, TU_USER, true),
                ],
                Problem::class => [
                    $this->fields[2]->fields['name']               => 'Problem text',
                    'itemtype_' . $this->fields[3]->fields['name'] => User::class,
                    'items_id_' . $this->fields[3]->fields['name'] => getItemByTypeName(User::class, TU_USER, true),
                ],
            ],
        );
    }

    public function testDestinationWithAdditionalFieldsButDisabledInConfig(): void
    {
        $this->login();
        $form = $this->createAndGetFormWithMultipleFieldQuestions();

        $this->sendFormAndAssertITILObjectAdditionalFields(
            form: $form,
            config: new SimpleValueConfig(false),
            answers: [
                "Field 1" => "Text value",
                "Field 2" => [
                    'itemtype' => User::class,
                    'items_id' => getItemByTypeName(User::class, TU_USER, true),
                ],
                "Field 3" => "Problem text",
                "Field 4" => [
                    'itemtype' => User::class,
                    'items_id' => getItemByTypeName(User::class, TU_USER, true),
                ],
            ],
            expected_field_values: [Ticket::class => [], Problem::class => []],
        );
    }

    public function testDestinationWithLocationAdditonalFields(): void
    {
        $this->login();
        $form = $this->createForm((new FormBuilder())->addQuestion(
            "Location Field",
            PluginFieldsQuestionType::class,
            extra_data: json_encode([
                'block_id' => $this->blocks[Ticket::class]->getID(),
                'field_id' => $this->fields[4]->getID(),
            ]),
        ));

        // Arrange: Create a location to select
        $location = $this->createItem(Location::class, [
            'name'        => 'Test Location',
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        $this->sendFormAndAssertITILObjectAdditionalFields(
            form: $form,
            config: new SimpleValueConfig(1),
            answers: [
                "Location Field" => $location->getID(),
            ],
            expected_field_values: [
                Ticket::class => [
                    $this->fields[4]->fields['name'] => $location->getID(),
                ],
            ],
        );
    }

    #[Override]
    public static function provideConvertFieldConfigFromFormCreator(): iterable
    {
        yield 'No destination field config related to Field question in FormCreator - Default configuration must be applied' => [
            'field_key'     => PluginFieldsDestinationField::getKey(),
            'fields_to_set' => [],
            'field_config' => new SimpleValueConfig(1),
        ];
    }

    private function sendFormAndAssertITILObjectAdditionalFields(
        Form $form,
        SimpleValueConfig $config,
        array $answers,
        array $expected_field_values,
    ): void {
        // Insert config
        $destinations = $form->getDestinations();
        foreach ($destinations as $destination) {
            $this->updateItem(
                $destination::getType(),
                $destination->getId(),
                ['config' => [PluginFieldsDestinationField::getKey() => $config->jsonSerialize()]],
                ["config"],
            );
        }

        // The provider use a simplified answer format to be more readable.
        // Rewrite answers into expected format.
        $formatted_answers = [];
        foreach ($answers as $question => $answer) {
            $key = $this->getQuestionId($form, $question);
            $formatted_answers[$key] = $answer;
        }

        // Submit form
        $answers_handler = AnswersHandler::getInstance();
        $answers = $answers_handler->saveAnswers(
            $form,
            $formatted_answers,
            getItemByTypeName(User::class, TU_USER, true),
        );

        // Get created itil object
        $created_items = $answers->getCreatedItems();
        $this->assertCount(count($expected_field_values), $created_items);

        // Check field values for each created item
        foreach ($expected_field_values as $itil_class => $expected_fields) {
            /** @var ?CommonITILObject $itil_object */
            $itil_object = array_reduce(
                $created_items,
                fn(?CommonITILObject $carry, CommonITILObject $item) => $carry ?? ($item instanceof $itil_class ? $item : null),
            );
            $this->assertNotNull($itil_object, sprintf('No created item of type %s found.', $itil_class));

            // Check field values
            $classname = PluginFieldsContainer::getClassname($itil_class, $this->blocks[$itil_class]->fields['name']);
            $obj = getItemForItemtype($classname);
            $values = current($obj->find([
                PluginFieldsContainer::getForeignKeyField() => $this->blocks[$itil_class]->getID(),
                'items_id'                                  => $itil_object->getID(),
            ]));

            if ($values === false) {
                $this->assertEmpty($expected_fields);
                return;
            }

            foreach ($expected_fields as $field_name => $expected_value) {
                $this->assertArrayHasKey($field_name, $values);
                $this->assertEquals(
                    $expected_value,
                    $values[$field_name],
                    sprintf("Field '%s' does not have the expected value.", $field_name),
                );
            }
        }
    }

    private function createAndGetFormWithMultipleFieldQuestions(): Form
    {
        $builder = new FormBuilder();

        // Add Problem destination
        $builder->addDestination(FormDestinationProblem::class, 'Problem');

        // Add Field questions
        $builder->addQuestion("Field 1", PluginFieldsQuestionType::class, extra_data: json_encode([
            'block_id' => $this->blocks[Ticket::class]->getID(),
            'field_id' => $this->fields[0]->getID(),
        ]));
        $builder->addQuestion("Field 2", PluginFieldsQuestionType::class, extra_data: json_encode([
            'block_id' => $this->blocks[Ticket::class]->getID(),
            'field_id' => $this->fields[1]->getID(),
        ]));
        $builder->addQuestion("Field 3", PluginFieldsQuestionType::class, extra_data: json_encode([
            'block_id' => $this->blocks[Problem::class]->getID(),
            'field_id' => $this->fields[2]->getID(),
        ]));
        $builder->addQuestion("Field 4", PluginFieldsQuestionType::class, extra_data: json_encode([
            'block_id' => $this->blocks[Problem::class]->getID(),
            'field_id' => $this->fields[3]->getID(),
        ]));
        return $this->createForm($builder);
    }
}
