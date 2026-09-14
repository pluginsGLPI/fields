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

use Glpi\Form\Condition\ConditionHandler\ItemAsTextConditionHandler;
use Glpi\Form\Condition\ConditionHandler\ItemConditionHandler;
use Glpi\Form\Condition\Engine;
use Glpi\Form\Condition\EngineInput;
use Glpi\Form\Condition\LogicOperator;
use Glpi\Form\Condition\Type;
use Glpi\Form\Condition\ValueOperator;
use Glpi\Form\Condition\VisibilityStrategy;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\QuestionType\QuestionTypesManager;
use Glpi\Tests\FormBuilder;
use GlpiPlugin\Field\Tests\QuestionTypeTestCase;
use LogicException;
use Location;
use PluginFieldsContainer;
use PluginFieldsDropdown;
use PluginFieldsField;
use PluginFieldsQuestionType;
use PluginFieldsQuestionTypeCategory;
use PluginFieldsQuestionTypeExtraDataConfig;

use function Safe\json_encode;

final class FieldQuestionTypeTest extends QuestionTypeTestCase
{
    public function testFieldsQuestionCategoryIsAvailableWhenValidFieldExists(): void
    {
        // Act: get enabled question type categories
        $manager = QuestionTypesManager::getInstance();
        $categories = $manager->getCategories();

        // Assert: check that Field question type category is registered
        $this->assertContains(
            PluginFieldsQuestionTypeCategory::class,
            array_map(fn($category) => $category::class, $categories),
        );
    }

    public function testFieldsQuestionCategoryIsNotAvailableWhenNoValidFieldExists(): void
    {
        // Arrange: clean created field and container
        $this->tearDownFieldTest();
        $this->deleteSingletonInstance([
            QuestionTypesManager::class,
        ]);

        // Act: get enabled question type categories
        $manager = QuestionTypesManager::getInstance();
        $categories = $manager->getCategories();

        // Assert: check that Field question type category isn't registered
        $this->assertNotContains(
            PluginFieldsQuestionTypeCategory::class,
            array_map(fn($category) => $category::class, $categories),
        );
    }

    public function testFieldsQuestionIsAvailableWhenValidFieldExists(): void
    {
        // Act: get enabled question types
        $manager = QuestionTypesManager::getInstance();
        $types = $manager->getQuestionTypes();

        // Assert: check that Field question type is registered
        $this->assertContains(
            PluginFieldsQuestionType::class,
            array_map(fn($type) => $type::class, $types),
        );
    }

    public function testFieldsQuestionIsNotAvailableWhenNoValidFieldExists(): void
    {
        // Arrange: clean created field and container
        $this->tearDownFieldTest();
        $this->deleteSingletonInstance([
            QuestionTypesManager::class,
        ]);

        // Act: get enabled question types
        $manager = QuestionTypesManager::getInstance();
        $types = $manager->getQuestionTypes();

        // Assert: check that Field question type isn't registered
        $this->assertNotContains(
            PluginFieldsQuestionType::class,
            array_map(fn($type) => $type::class, $types),
        );
    }

    public function testFieldsQuestionEditorRendering(): void
    {
        $this->login();

        // Arrange: create form with Field question
        $builder = new FormBuilder("My form");
        $builder->addQuestion(
            "My question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('glpi_item')),
        );
        $form = $this->createForm($builder);

        // Act: render form editor
        $crawler = $this->renderFormEditor($form);

        // Assert: item was rendered
        $this->assertNotEmpty($crawler->filter('.form-editor-container [data-glpi-form-editor-question] .glpi-fields-plugin-question-type-glpi-item-field'));
    }

    public function testFieldsQuestionHelpdeskRendering(): void
    {
        $this->login();

        // Arrange: create form with Field question
        $builder = new FormBuilder("My form");
        $builder->addQuestion(
            "My question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('glpi_item')),
        );
        $form = $this->createForm($builder);

        // Act: render helpdesk form
        $crawler = $this->renderHelpdeskForm($form);

        // Assert: item was rendered
        $this->assertNotEmpty($crawler->filter('[data-glpi-form-renderer-fields-question-type-specific-container]'));
    }


    public function testFieldsQuestionSubmitEmptyDropdown(): void
    {
        $this->login();

        $itemtype = PluginFieldsDropdown::getClassname($this->fields['dropdown']->fields['name']);
        $this->createItemsWithNames($itemtype, ['Option 1', 'Option 2', 'Option 3']);

        // Arrange: create form with Field question
        $builder = new FormBuilder("My form");
        $builder->addQuestion(
            "Dropdown field question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('dropdown')),
        );
        $form = $this->createForm($builder);

        // Act: submit form
        $this->sendFormAndGetCreatedTicket($form, [
            "Dropdown field question" => [
                'itemtype'  => $itemtype,
                'items_ids' => '0',
            ],
        ]);
    }

    public function testFormatRawAnswerSupportsLegacyItemsIdKey(): void
    {
        $this->login();

        $itemtype = PluginFieldsDropdown::getClassname($this->fields['dropdown']->fields['name']);
        $item = $this->createItem($itemtype, ['name' => 'Legacy Option']);

        $builder = new FormBuilder("Legacy answer format form");
        $builder->addQuestion(
            "Dropdown field question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('dropdown')),
        );
        $form = $this->createForm($builder);

        $question = new Question();
        $this->assertTrue($question->getFromDB($this->getQuestionId($form, "Dropdown field question")));

        $question_type = new PluginFieldsQuestionType();

        // Answers submitted before the 'items_ids' rename are stored with a singular 'items_id' key
        $legacy_answer = ['itemtype' => $itemtype, 'items_id' => $item->getID()];
        $current_answer = ['itemtype' => $itemtype, 'items_ids' => $item->getID()];

        $this->assertSame('Legacy Option', $question_type->formatRawAnswer($legacy_answer, $question));
        $this->assertSame(
            $question_type->formatRawAnswer($current_answer, $question),
            $question_type->formatRawAnswer($legacy_answer, $question),
        );

        // Answers submitted before dropdown values were wrapped with their itemtype (plugin < 1.24.0)
        // are stored as a bare scalar id
        $pre_wrapping_answer = (string) $item->getID();
        $this->assertSame('Legacy Option', $question_type->formatRawAnswer($pre_wrapping_answer, $question));
    }

    public function testFormatRawAnswerForNativeItemtypeDropdown(): void
    {
        $this->login();

        $location = $this->createItem(Location::class, [
            'name'        => 'Native Dropdown Location',
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        $builder = new FormBuilder("Native dropdown form");
        $builder->addQuestion(
            "Location dropdown question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('dropdown_location')),
        );
        $form = $this->createForm($builder);

        $question = new Question();
        $this->assertTrue($question->getFromDB($this->getQuestionId($form, "Location dropdown question")));

        $question_type = new PluginFieldsQuestionType();

        // Current format
        $answer = ['itemtype' => Location::class, 'items_ids' => $location->getID()];
        $this->assertSame('Native Dropdown Location', $question_type->formatRawAnswer($answer, $question));

        // Answers submitted before the 'items_ids' rename are stored with a singular 'items_id' key
        $legacy_answer = ['itemtype' => Location::class, 'items_id' => $location->getID()];
        $this->assertSame('Native Dropdown Location', $question_type->formatRawAnswer($legacy_answer, $question));

        // Answers submitted before dropdown values were wrapped with their itemtype (plugin < 1.24.0)
        // are stored as a bare scalar id
        $pre_wrapping_answer = (string) $location->getID();
        $this->assertSame('Native Dropdown Location', $question_type->formatRawAnswer($pre_wrapping_answer, $question));

        $location->delete($location->fields, true);
    }

    public function testFormatRawAnswerForNativeItemtypeMultipleDropdown(): void
    {
        $this->login();

        $location1 = $this->createItem(Location::class, [
            'name'        => 'Location Alpha',
            'entities_id' => $this->getTestRootEntity(true),
        ]);
        $location2 = $this->createItem(Location::class, [
            'name'        => 'Location Beta',
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        $builder = new FormBuilder("Native multiple dropdown form");
        $builder->addQuestion(
            "Location dropdown question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('dropdown_location_multiple')),
        );
        $form = $this->createForm($builder);

        $question = new Question();
        $this->assertTrue($question->getFromDB($this->getQuestionId($form, "Location dropdown question")));

        $question_type = new PluginFieldsQuestionType();
        $answer = ['itemtype' => Location::class, 'items_ids' => [$location1->getID(), $location2->getID()]];

        $this->assertSame('Location Alpha, Location Beta', $question_type->formatRawAnswer($answer, $question));

        // Answers submitted before dropdown values were wrapped with their itemtype (plugin < 1.24.0)
        // are stored as a flat array of ids, with no wrapper at all
        $pre_wrapping_answer = [$location1->getID(), $location2->getID()];
        $this->assertSame('Location Alpha, Location Beta', $question_type->formatRawAnswer($pre_wrapping_answer, $question));

        $location1->delete($location1->fields, true);
        $location2->delete($location2->fields, true);
    }

    public function testFieldDeletionWhenUsedInForm(): void
    {
        $this->login();

        // Arrange: create form with Field question
        $builder = new FormBuilder("My form");
        $builder->addQuestion(
            "My question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('glpi_item')),
        );
        $this->createForm($builder);

        // Act: try to delete field
        $response = $this->fields['glpi_item']->delete($this->fields['glpi_item']->fields);

        // Assert: deletion is blocked with appropriate message
        $this->assertFalse($response);
        $this->hasSessionMessageThatContains('The field &quot;GLPI Item&quot; cannot be deleted because it is used in a form question', ERROR);
    }

    public function testFieldDeletionWhenNotUsedInForm(): void
    {
        $this->login();

        // Act: try to delete field
        $response = $this->fields['glpi_item']->delete($this->fields['glpi_item']->fields);

        // Assert: deletion is successful
        $this->assertTrue($response);
        $this->hasNoSessionMessage(ERROR);
    }

    public function testFieldDeletionWhenAnotherFieldUsedInForm(): void
    {
        $this->login();

        // Arrange: create form with Field question using dropdown field
        $builder = new FormBuilder("My form");
        $builder->addQuestion(
            "My question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('dropdown')),
        );
        $this->createForm($builder);

        // Act: try to delete glpi_item field which isn't used in form but exists
        $response = $this->fields['glpi_item']->delete($this->fields['glpi_item']->fields);

        // Assert: deletion is successful and not blocked by the fact that another field is used in form
        $this->assertTrue($response);
        $this->hasNoSessionMessage(ERROR);
    }

    public function testGetConditionHandlersForDropdownFieldIncludesItemHandlers(): void
    {
        $question_type = new PluginFieldsQuestionType();
        $config = $this->getFieldExtraDataConfig('dropdown');

        $handlers = $question_type->getConditionHandlers($config);
        $handler_classes = array_map(fn($h) => $h::class, $handlers);

        $this->assertContains(ItemConditionHandler::class, $handler_classes);
        $this->assertContains(ItemAsTextConditionHandler::class, $handler_classes);
    }

    public function testGetConditionHandlersForNonDropdownFieldExcludesItemHandlers(): void
    {
        $question_type = new PluginFieldsQuestionType();
        $config = $this->getFieldExtraDataConfig('glpi_item');

        $handlers = $question_type->getConditionHandlers($config);
        $handler_classes = array_map(fn($h) => $h::class, $handlers);

        $this->assertNotContains(ItemConditionHandler::class, $handler_classes);
        $this->assertNotContains(ItemAsTextConditionHandler::class, $handler_classes);
    }

    public function testDropdownConditionHandlerEqualsOperator(): void
    {
        $this->login();

        [$form, $question_id, $dropdown_question_id, $itemtype, $item1_id, $item2_id] = $this->createDropdownConditionForm(
            ValueOperator::EQUALS,
        );

        // Test: matching item → question is visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item1_id]]]));
        $this->assertTrue($engine->computeVisibility()->isQuestionVisible($question_id));

        // Test: different item → question is not visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item2_id]]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id));
    }

    public function testDropdownConditionHandlerNotEqualsOperator(): void
    {
        $this->login();

        [$form, $question_id, $dropdown_question_id, $itemtype, $item1_id, $item2_id] = $this->createDropdownConditionForm(
            ValueOperator::NOT_EQUALS,
        );

        // Test: same item → question is not visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item1_id]]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id));

        // Test: different item → question is visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item2_id]]]));
        $this->assertTrue($engine->computeVisibility()->isQuestionVisible($question_id));
    }

    public function testDropdownConditionHandlerContainsOperator(): void
    {
        $this->login();

        $itemtype = PluginFieldsDropdown::getClassname($this->fields['dropdown']->fields['name']);
        $item_id = $this->createItem($itemtype, ['name' => 'Alpha Option'])->getID();

        $builder = new FormBuilder("Dropdown contains test form");
        $builder->addQuestion(
            "Dropdown question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('dropdown')),
        );
        $builder->addQuestion("Subject", QuestionTypeShortText::class);
        $builder->setQuestionVisibility("Subject", VisibilityStrategy::VISIBLE_IF, [
            [
                'logic_operator' => LogicOperator::AND,
                'item_name'      => "Dropdown question",
                'item_type'      => Type::QUESTION,
                'value_operator' => ValueOperator::CONTAINS,
                'value'          => 'alpha',
            ],
        ]);
        $form = $this->createForm($builder);

        $question_id = $this->getQuestionId($form, "Subject");
        $dropdown_question_id = $this->getQuestionId($form, "Dropdown question");

        // Test: item name contains the condition value → question is visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item_id]]]));
        $this->assertTrue($engine->computeVisibility()->isQuestionVisible($question_id));

        // Test: item name does not contain the condition value → question is not visible
        $builder2 = new FormBuilder("Dropdown contains mismatch form");
        $builder2->addQuestion(
            "Dropdown question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('dropdown')),
        );
        $builder2->addQuestion("Subject", QuestionTypeShortText::class);
        $builder2->setQuestionVisibility("Subject", VisibilityStrategy::VISIBLE_IF, [
            [
                'logic_operator' => LogicOperator::AND,
                'item_name'      => "Dropdown question",
                'item_type'      => Type::QUESTION,
                'value_operator' => ValueOperator::CONTAINS,
                'value'          => 'xyz',
            ],
        ]);
        $form2 = $this->createForm($builder2);
        $question_id2 = $this->getQuestionId($form2, "Subject");
        $dropdown_question_id2 = $this->getQuestionId($form2, "Dropdown question");

        $engine = new Engine($form2, new EngineInput([$dropdown_question_id2 => ['itemtype' => $itemtype, 'items_ids' => [$item_id]]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id2));
    }

    public function testDropdownConditionHandlerNotContainsOperator(): void
    {
        $this->login();

        $itemtype = PluginFieldsDropdown::getClassname($this->fields['dropdown']->fields['name']);
        $item_id = $this->createItem($itemtype, ['name' => 'Beta Option'])->getID();

        $builder = new FormBuilder("Dropdown not contains test form");
        $builder->addQuestion(
            "Dropdown question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('dropdown')),
        );
        $builder->addQuestion("Subject", QuestionTypeShortText::class);
        $builder->setQuestionVisibility("Subject", VisibilityStrategy::VISIBLE_IF, [
            [
                'logic_operator' => LogicOperator::AND,
                'item_name'      => "Dropdown question",
                'item_type'      => Type::QUESTION,
                'value_operator' => ValueOperator::NOT_CONTAINS,
                'value'          => 'xyz',
            ],
        ]);
        $form = $this->createForm($builder);

        $question_id = $this->getQuestionId($form, "Subject");
        $dropdown_question_id = $this->getQuestionId($form, "Dropdown question");

        // Test: item name does not contain the value → question is visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item_id]]]));
        $this->assertTrue($engine->computeVisibility()->isQuestionVisible($question_id));

        // Test: item name contains the value → question is not visible
        $builder2 = new FormBuilder("Dropdown not contains match form");
        $builder2->addQuestion(
            "Dropdown question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('dropdown')),
        );
        $builder2->addQuestion("Subject", QuestionTypeShortText::class);
        $builder2->setQuestionVisibility("Subject", VisibilityStrategy::VISIBLE_IF, [
            [
                'logic_operator' => LogicOperator::AND,
                'item_name'      => "Dropdown question",
                'item_type'      => Type::QUESTION,
                'value_operator' => ValueOperator::NOT_CONTAINS,
                'value'          => 'beta',
            ],
        ]);
        $form2 = $this->createForm($builder2);
        $question_id2 = $this->getQuestionId($form2, "Subject");
        $dropdown_question_id2 = $this->getQuestionId($form2, "Dropdown question");

        $engine = new Engine($form2, new EngineInput([$dropdown_question_id2 => ['itemtype' => $itemtype, 'items_ids' => [$item_id]]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id2));
    }

    public function testGetConditionHandlersForMultipleDropdownFieldExcludesItemAsTextHandler(): void
    {
        $question_type = new PluginFieldsQuestionType();
        $config = $this->getFieldExtraDataConfig('dropdown_multiple');

        $handlers = $question_type->getConditionHandlers($config);
        $handler_classes = array_map(fn($h) => $h::class, $handlers);

        $this->assertContains(ItemConditionHandler::class, $handler_classes);
        $this->assertNotContains(ItemAsTextConditionHandler::class, $handler_classes);

        /** @var ItemConditionHandler $item_handler */
        $item_handler = current(array_filter($handlers, fn($h) => $h instanceof ItemConditionHandler));
        $this->assertContains(ValueOperator::CONTAINS, $item_handler->getSupportedValueOperators());
        $this->assertContains(ValueOperator::NOT_CONTAINS, $item_handler->getSupportedValueOperators());
    }

    public function testMultipleDropdownConditionHandlerEqualsOperatorIsOrderIndependent(): void
    {
        $this->login();

        [$form, $question_id, $dropdown_question_id, $itemtype, $item1_id, $item2_id] = $this->createMultipleDropdownConditionForm(
            ValueOperator::EQUALS,
        );

        // Test: same selection (single item, matches condition value) → question is visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item1_id]]]));
        $this->assertTrue($engine->computeVisibility()->isQuestionVisible($question_id));

        // Test: additional item selected → question is not visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item1_id, $item2_id]]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id));
    }

    public function testMultipleDropdownConditionHandlerNotEqualsOperatorIsOrderIndependent(): void
    {
        $this->login();

        [$form, $question_id, $dropdown_question_id, $itemtype, $item1_id, $item2_id] = $this->createMultipleDropdownConditionForm(
            ValueOperator::NOT_EQUALS,
        );

        // Test: same selection (single item, matches condition value) → question is not visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item1_id]]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id));

        // Test: additional item selected → question is visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item1_id, $item2_id]]]));
        $this->assertTrue($engine->computeVisibility()->isQuestionVisible($question_id));
    }

    public function testMultipleDropdownConditionHandlerContainsOperator(): void
    {
        $this->login();

        [$form, $question_id, $dropdown_question_id, $itemtype, $item1_id, $item2_id] = $this->createMultipleDropdownConditionForm(
            ValueOperator::CONTAINS,
        );

        // Test: selection includes the required item among others → question is visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item1_id, $item2_id]]]));
        $this->assertTrue($engine->computeVisibility()->isQuestionVisible($question_id));

        // Test: selection does not include the required item → question is not visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item2_id]]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id));
    }

    public function testMultipleDropdownConditionHandlerNotContainsOperator(): void
    {
        $this->login();

        [$form, $question_id, $dropdown_question_id, $itemtype, $item1_id, $item2_id] = $this->createMultipleDropdownConditionForm(
            ValueOperator::NOT_CONTAINS,
        );

        // Test: selection does not include the excluded item → question is visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item2_id]]]));
        $this->assertTrue($engine->computeVisibility()->isQuestionVisible($question_id));

        // Test: selection includes the excluded item → question is not visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_ids' => [$item1_id, $item2_id]]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id));
    }

    private function getFieldExtraDataConfig(string $field_name): PluginFieldsQuestionTypeExtraDataConfig
    {
        if (!$this->block instanceof PluginFieldsContainer || !$this->fields[$field_name] instanceof PluginFieldsField) {
            throw new LogicException("Field and container must be created before getting extra data config");
        }

        return new PluginFieldsQuestionTypeExtraDataConfig($this->block->getID(), $this->fields[$field_name]->getID());
    }

    /**
     * Helper to create a form with a dropdown question and a condition on it.
     * Returns [form, question_id, dropdown_question_id, itemtype, item1_id, item2_id].
     */
    private function createDropdownConditionForm(ValueOperator $operator): array
    {
        $itemtype = PluginFieldsDropdown::getClassname($this->fields['dropdown']->fields['name']);
        [$item1, $item2] = $this->createItemsWithNames($itemtype, ['First Option', 'Second Option']);
        $item1_id = $item1->getID();
        $item2_id = $item2->getID();

        $condition_value = ['itemtype' => $itemtype, 'items_ids' => [$item1_id]];

        $builder = new FormBuilder("Dropdown condition form");
        $builder->addQuestion(
            "Dropdown question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('dropdown')),
        );
        $builder->addQuestion("Subject", QuestionTypeShortText::class);
        $builder->setQuestionVisibility("Subject", VisibilityStrategy::VISIBLE_IF, [
            [
                'logic_operator' => LogicOperator::AND,
                'item_name'      => "Dropdown question",
                'item_type'      => Type::QUESTION,
                'value_operator' => $operator,
                'value'          => $condition_value,
            ],
        ]);
        $form = $this->createForm($builder);

        $question_id = $this->getQuestionId($form, "Subject");
        $dropdown_question_id = $this->getQuestionId($form, "Dropdown question");

        return [$form, $question_id, $dropdown_question_id, $itemtype, $item1_id, $item2_id];
    }

    /**
     * Helper to create a form with a "multiple" dropdown question and a condition on it.
     * Returns [form, question_id, dropdown_question_id, itemtype, item1_id, item2_id].
     */
    private function createMultipleDropdownConditionForm(ValueOperator $operator): array
    {
        $itemtype = PluginFieldsDropdown::getClassname($this->fields['dropdown_multiple']->fields['name']);
        [$item1, $item2] = $this->createItemsWithNames($itemtype, ['First Option', 'Second Option']);
        $item1_id = $item1->getID();
        $item2_id = $item2->getID();

        $condition_value = ['itemtype' => $itemtype, 'items_ids' => [$item1_id]];

        $builder = new FormBuilder("Multiple dropdown condition form");
        $builder->addQuestion(
            "Dropdown question",
            PluginFieldsQuestionType::class,
            extra_data: json_encode($this->getFieldExtraDataConfig('dropdown_multiple')),
        );
        $builder->addQuestion("Subject", QuestionTypeShortText::class);
        $builder->setQuestionVisibility("Subject", VisibilityStrategy::VISIBLE_IF, [
            [
                'logic_operator' => LogicOperator::AND,
                'item_name'      => "Dropdown question",
                'item_type'      => Type::QUESTION,
                'value_operator' => $operator,
                'value'          => $condition_value,
            ],
        ]);
        $form = $this->createForm($builder);

        $question_id = $this->getQuestionId($form, "Subject");
        $dropdown_question_id = $this->getQuestionId($form, "Dropdown question");

        return [$form, $question_id, $dropdown_question_id, $itemtype, $item1_id, $item2_id];
    }
}
