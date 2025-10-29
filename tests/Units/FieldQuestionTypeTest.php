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

use Glpi\Form\QuestionType\QuestionTypesManager;
use Glpi\Tests\FormBuilder;
use GlpiPlugin\Field\Tests\QuestionTypeTestCase;
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
            array_map(fn($category) => get_class($category), $categories),
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
            array_map(fn($category) => get_class($category), $categories),
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
            array_map(fn($type) => get_class($type), $types),
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
            array_map(fn($type) => get_class($type), $types),
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
            extra_data: json_encode($this->getFieldExtraDataConfig()),
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
            extra_data: json_encode($this->getFieldExtraDataConfig()),
        );
        $form = $this->createForm($builder);

        // Act: render helpdesk form
        $crawler = $this->renderHelpdeskForm($form);

        // Assert: item was rendered
        $this->assertNotEmpty($crawler->filter('[data-glpi-form-renderer-fields-question-type-specific-container]'));
    }

    private function getFieldExtraDataConfig(): PluginFieldsQuestionTypeExtraDataConfig
    {
        if ($this->block === null || $this->field === null) {
            throw new \LogicException("Field and container must be created before getting extra data config");
        }

        return new PluginFieldsQuestionTypeExtraDataConfig($this->block->getID(), $this->field->getID());
    }
}
