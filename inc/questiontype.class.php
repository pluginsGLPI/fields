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
use Glpi\Form\Form;
use Glpi\Form\Migration\FormQuestionDataConverterInterface;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\AbstractQuestionType;
use Glpi\Form\QuestionType\QuestionTypeCategoryInterface;

use function Safe\json_decode;
use function Safe\json_encode;

final class PluginFieldsQuestionType extends AbstractQuestionType implements FormQuestionDataConverterInterface
{
    #[Override]
    public function getCategory(): QuestionTypeCategoryInterface
    {
        return new PluginFieldsQuestionTypeCategory();
    }

    #[Override]
    public function getExtraDataConfigClass(): string
    {
        return PluginFieldsQuestionTypeExtraDataConfig::class;
    }

    #[Override]
    public function getSubTypes(): array
    {
        return $this->getAvailableBlocks();
    }

    #[Override]
    public function getSubTypeFieldName(): string
    {
        return 'block_id';
    }

    #[Override]
    public function getSubTypeFieldAriaLabel(): string
    {
        return __('Select a block');
    }

    #[Override]
    public function getSubTypeDefaultValue(?Question $question): string
    {
        return (string) $this->getDefaultValueBlockId($question);
    }

    #[Override]
    public function formatDefaultValueForDB(mixed $value): string
    {
        return json_encode($value);
    }

    #[Override]
    public function validateExtraDataInput(array $input): bool
    {
        // Check if the block_id is set and is numeric
        if (!isset($input['block_id']) || !is_numeric($input['block_id'])) {
            return false;
        }

        // Check if the block_id exists
        $available_blocks = $this->getAvailableBlocks();
        if (!isset($available_blocks[$input['block_id']])) {
            return false;
        }

        // Check if the field_id is set and is numeric
        if (!isset($input['field_id']) || !is_numeric($input['field_id'])) {
            return false;
        }

        // Check if the field_id exists in the selected block
        $available_fields = self::getFieldsFromBlock($input['block_id']);
        return isset($available_fields[$input['field_id']]);
    }

    #[Override]
    public function renderAdministrationTemplate(?Question $question): string
    {
        // Get the block_id from the question's extra data or use the first available block
        $block_id = $this->getDefaultValueBlockId($question);
        if ($block_id === null) {
            $block_id = current(array_keys($this->getAvailableBlocks()));
        }
        $available_fields = self::getFieldsFromBlock($block_id);

        // Retrieve current field
        $current_field_id = $this->getDefaultValueFieldId($question);
        if ($current_field_id === null) {
            $current_field_id = current(array_keys($available_fields));
        }

        $current_field = PluginFieldsField::getById($current_field_id);

        // Compute default value for the field
        $default_value = null;
        if ($question !== null && !empty($question->fields['default_value'])) {
            $default_value = json_decode($question->fields['default_value'], true);
        }

        $twig = TemplateRenderer::getInstance();
        return $twig->render('@fields/question_type_administration.html.twig', [
            'question'          => $question,
            'default_value'     => $default_value,
            'selected_field_id' => $current_field_id,
            'available_fields'  => $available_fields,
            'item'              => new Form(),
            'field'             => $current_field->fields,
        ]);
    }

    #[Override]
    public function renderEndUserTemplate(Question $question): string
    {
        // Get the block_id from the question's extra data or use the first available block
        $block_id = $this->getDefaultValueBlockId($question);
        if ($block_id === null) {
            $block_id = current(array_keys($this->getAvailableBlocks()));
        }
        $available_fields = self::getFieldsFromBlock($block_id);

        // Retrieve current field
        $current_field_id = $this->getDefaultValueFieldId($question);
        if ($current_field_id === null) {
            $current_field_id = current(array_keys($available_fields));
        }

        $current_field = PluginFieldsField::getById($current_field_id);

        // Compute default value for the field
        $default_value = null;
        if (!empty($question->fields['default_value'])) {
            $default_value = json_decode($question->fields['default_value'], true);
        }

        $twig = TemplateRenderer::getInstance();
        return $twig->render('@fields/question_type_end_user.html.twig', [
            'question'      => $question,
            'field'         => $current_field->fields,
            'default_value' => $default_value,
            'item'          => new Form(),
        ]);
    }

    #[Override]
    public function formatRawAnswer(mixed $answer, Question $question): string
    {
        $current_field_id = $this->getDefaultValueFieldId($question);
        if ($current_field_id === null) {
            throw new LogicException('No field configured for this question');
        }

        $current_field = PluginFieldsField::getById((int) $current_field_id);

        switch ($current_field->fields['type']) {
            case 'header':
            case 'text':
            case 'textarea':
            case 'richtext':
            case 'number':
            case 'url':
            case 'date':
                return (string) $answer;
            case 'dropdown':
                if (is_string($answer)) {
                    $answer = [$answer];
                }

                $itemtype = PluginFieldsDropdown::getClassname($current_field->fields['name']);
                return implode(', ', array_map(fn($opt_id) => $itemtype::getById($opt_id)->fields['name'], $answer));
            case 'yesno':
                return $answer ? __('Yes') : __('No');
            case 'datetime':
                return (new DateTime($answer))->format('Y-m-d H:i');
            case 'glpi_item':
                $item = $answer['itemtype']::getById($answer['items_id']);
                if (!$item) {
                    return '';
                }

                return $item->fields['name'];
        }

        if (str_starts_with($current_field->fields['type'], 'dropdown-')) {
            $itemtype = substr($current_field->fields['type'], strlen('dropdown-'));
            if (!getItemForItemtype($itemtype)) {
                return '';
            }

            if (is_string($answer)) {
                $answer = [$answer];
            }
            return implode(', ', array_map(fn($items_id) => $itemtype::getById($items_id)->fields['name'], $answer));
        }

        return (string) $answer;
    }

    #[Override]
    public function beforeConversion(array $rawData): void {}

    #[Override]
    public function convertDefaultValue(array $rawData): null
    {
        return null;
    }

    #[Override]
    public function convertExtraData(array $rawData): ?array
    {
        $values = json_decode($rawData['values'], true);
        if (!isset($values['dropdown_fields_field']) || !isset($values['blocks_field'])) {
            return null;
        }

        $block = new PluginFieldsContainer();
        if (!$block->getFromDB($values['blocks_field'])) {
            return null;
        }

        $field = new PluginFieldsField();
        if (!$field->getFromDBByCrit(['name' => $values['dropdown_fields_field']])) {
            return null;
        }

        return [
            'block_id' => $block->getID(),
            'field_id' => $field->getID(),
        ];
    }

    #[Override]
    public function getTargetQuestionType(array $rawData): string
    {
        return self::class;
    }

    /**
     * Retrieve the default value block from the question's extra data
     *
     * @param Question|null $question The question to retrieve the default value from
     * @return ?int
     */
    public function getDefaultValueBlockId(?Question $question): ?int
    {
        if ($question === null) {
            return null;
        }

        /** @var ?PluginFieldsQuestionTypeExtraDataConfig $config */
        $config = $this->getExtraDataConfig(json_decode($question->fields['extra_data'], true) ?? []);
        if ($config === null) {
            return null;
        }

        return $config->getBlockId();
    }

    /**
     * Retrieve the default value field from the question's extra data
     *
     * @param Question|null $question The question to retrieve the default value from
     * @return ?int
     */
    public function getDefaultValueFieldId(?Question $question): ?int
    {
        if ($question === null) {
            return null;
        }

        /** @var ?PluginFieldsQuestionTypeExtraDataConfig $config */
        $config = $this->getExtraDataConfig(json_decode($question->fields['extra_data'], true) ?? []);
        if ($config === null) {
            return null;
        }

        return $config->getFieldId();
    }

    private function getAvailableBlocks(?Form $form = null): array
    {
        $field_container  = new PluginFieldsContainer();
        $available_blocks = [];
        $result           = $field_container->find([
            'is_active' => 1,
            'type'      => 'dom',
            'OR'        => [
                ['itemtypes' => ['LIKE', '%\"Ticket\"%']],
                ['itemtypes' => ['LIKE', '%\"Change\"%']],
                ['itemtypes' => ['LIKE', '%\"Problem\"%']],
            ],
        ] + getEntitiesRestrictCriteria(PluginFieldsContainer::getTable(), '', '', true), 'name');
        foreach ($result as $id => $data) {
            $available_blocks[$id] = $data['label'];
        }
        return $available_blocks;
    }

    public static function getFieldsFromBlock(?int $block_id): array
    {
        $fields = [];
        $field_container = PluginFieldsContainer::getById($block_id);
        if ($field_container) {
            $field = new PluginFieldsField();
            $result = $field->find([
                'is_active'                   => 1,
                'plugin_fields_containers_id' => $block_id,
                'NOT'                         => [
                    ['type' => 'header'], // Exclude headers
                ],
            ]);
            foreach ($result as $id => $data) {
                $fields[$id] = $data['label'];
            }
        }

        return $fields;
    }

    /**
     * Check if there is at least one available field in the available blocks
     *
     * @return bool
     */
    public static function hasAvailableFields(): bool
    {
        $blocks = (new self())->getAvailableBlocks();
        foreach (array_keys($blocks) as $block_id) {
            $fields = (new self())->getFieldsFromBlock($block_id);
            if ($fields !== []) {
                return true;
            }
        }

        return false;
    }
}
