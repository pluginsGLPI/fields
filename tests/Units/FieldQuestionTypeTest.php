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
use Glpi\Form\QuestionType\QuestionTypeShortText;
use Glpi\Form\QuestionType\QuestionTypesManager;
use Glpi\Tests\FormBuilder;
use GlpiPlugin\Field\Tests\QuestionTypeTestCase;
use LogicException;
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

        /** @var CommonDBTM $dropdown_item */
        $dropdown_item = getItemForItemtype(PluginFieldsDropdown::getClassname($this->fields['dropdown']->fields['name']));
        $dropdown_ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $dropdown_ids[] = $dropdown_item->add([
                'name' => 'Option ' . $i,
            ]);
        }

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
                'items_id' => '0',
            ],
        ]);
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
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_id' => $item1_id]]));
        $this->assertTrue($engine->computeVisibility()->isQuestionVisible($question_id));

        // Test: different item → question is not visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_id' => $item2_id]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id));
    }

    public function testDropdownConditionHandlerNotEqualsOperator(): void
    {
        $this->login();

        [$form, $question_id, $dropdown_question_id, $itemtype, $item1_id, $item2_id] = $this->createDropdownConditionForm(
            ValueOperator::NOT_EQUALS,
        );

        // Test: same item → question is not visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_id' => $item1_id]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id));

        // Test: different item → question is visible
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_id' => $item2_id]]));
        $this->assertTrue($engine->computeVisibility()->isQuestionVisible($question_id));
    }

    public function testDropdownConditionHandlerContainsOperator(): void
    {
        $this->login();

        $itemtype = PluginFieldsDropdown::getClassname($this->fields['dropdown']->fields['name']);
        $dropdown_item = getItemForItemtype($itemtype);
        $item_id = $dropdown_item->add(['name' => 'Alpha Option']);

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
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_id' => $item_id]]));
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

        $engine = new Engine($form2, new EngineInput([$dropdown_question_id2 => ['itemtype' => $itemtype, 'items_id' => $item_id]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id2));
    }

    public function testDropdownConditionHandlerNotContainsOperator(): void
    {
        $this->login();

        $itemtype = PluginFieldsDropdown::getClassname($this->fields['dropdown']->fields['name']);
        $dropdown_item = getItemForItemtype($itemtype);
        $item_id = $dropdown_item->add(['name' => 'Beta Option']);

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
        $engine = new Engine($form, new EngineInput([$dropdown_question_id => ['itemtype' => $itemtype, 'items_id' => $item_id]]));
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

        $engine = new Engine($form2, new EngineInput([$dropdown_question_id2 => ['itemtype' => $itemtype, 'items_id' => $item_id]]));
        $this->assertFalse($engine->computeVisibility()->isQuestionVisible($question_id2));
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
        $dropdown_item = getItemForItemtype($itemtype);
        $item1_id = $dropdown_item->add(['name' => 'First Option']);
        $item2_id = $dropdown_item->add(['name' => 'Second Option']);

        $condition_value = ['itemtype' => $itemtype, 'items_id' => $item1_id];

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
}
