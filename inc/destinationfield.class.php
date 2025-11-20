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

use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\JsonFieldInterface;
use Glpi\Form\AnswersSet;
use Glpi\Form\Destination\AbstractCommonITILFormDestination;
use Glpi\Form\Destination\AbstractConfigField;
use Glpi\Form\Destination\CommonITILField\Category;
use Glpi\Form\Destination\CommonITILField\SimpleValueConfig;
use Glpi\Form\Destination\FormDestination;
use Glpi\Form\Form;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\QuestionTypeItemDropdown;

class PluginFieldsDestinationField extends AbstractConfigField
{
    public function __construct(private AbstractCommonITILFormDestination $itil_destination) {}

    #[Override]
    public function getLabel(): string
    {
        return __('Additional fields', 'fields');
    }

    #[Override]
    public function getConfigClass(): string
    {
        return SimpleValueConfig::class;
    }

    #[Override]
    public function renderConfigForm(
        Form $form,
        FormDestination $destination,
        JsonFieldInterface $config,
        string $input_name,
        array $display_options,
    ): string {
        if (!$config instanceof SimpleValueConfig) {
            throw new InvalidArgumentException("Unexpected config class");
        }

        $twig = TemplateRenderer::getInstance();
        return $twig->render('@fields/destinationfield.html.twig', [
            'value'      => $config->getValue(),
            'input_name' => $input_name . "[" . SimpleValueConfig::VALUE . "]",
            'options'    => $display_options,
        ]);
    }

    #[Override]
    public function applyConfiguratedValueToInputUsingAnswers(
        JsonFieldInterface $config,
        array $input,
        AnswersSet $answers_set,
    ): array {
        if (!$config instanceof SimpleValueConfig) {
            throw new InvalidArgumentException("Unexpected config class");
        }

        if ((bool) $config->getValue()) {
            $answers = $answers_set->getAnswersByTypes([
                PluginFieldsQuestionType::class,
                QuestionTypeItemDropdown::class,
            ]);

            foreach ($answers as $answer) {
                $question = Question::getById($answer->getQuestionId());
                $block_id = PluginFieldsContainer::findContainer($this->itil_destination->getTarget()::class, 'dom');
                if (!$block_id) {
                    continue;
                }

                if ($question->getQuestionType() instanceof QuestionTypeItemDropdown) {
                    $itemtype = (new QuestionTypeItemDropdown())->getDefaultValueItemtype($question);
                    $field_name = $itemtype::getForeignKeyField();
                    if (!str_starts_with($field_name, 'plugin_fields_')) {
                        continue;
                    }

                    /** @var object{field_name: string} $item */
                    $item = getItemForItemtype($itemtype);
                    $field = new PluginFieldsField();
                    if (!$field->getFromDBByCrit(['name' => $item->field_name])) {
                        continue;
                    }

                    $value = $answer->getRawAnswer()['items_id'];
                } else {
                    $field_id = (new PluginFieldsQuestionType())->getDefaultValueFieldId($question);
                    $field = PluginFieldsField::getById($field_id);
                }

                // Check that the field belongs to the correct block
                if ($block_id != $field->fields[PluginFieldsContainer::getForeignKeyField()]) {
                    continue;
                }

                $input['c_id'] = $block_id;
                if ($field->fields['type'] == 'dropdown') {
                    $field_name = 'plugin_fields_' . $field->fields['name'] . 'dropdowns_id';
                } else {
                    $field_name = $field->fields['name'];
                }

                if ($field->fields['type'] == 'glpi_item') {
                    $input[sprintf('itemtype_%s', $field_name)] = $answer->getRawAnswer()['itemtype'];
                    $input[sprintf('items_id_%s', $field_name)] = $answer->getRawAnswer()['items_id'];
                } else {
                    $input[$field_name] = $value ?? $answer->getRawAnswer();
                }
            }
        }
        return $input;
    }

    #[Override]
    public function getDefaultConfig(Form $form): SimpleValueConfig
    {
        return new SimpleValueConfig("1");
    }

    #[Override]
    public function getWeight(): int
    {
        return 1000;
    }

    #[Override]
    public function getCategory(): Category
    {
        return Category::PROPERTIES;
    }
}
