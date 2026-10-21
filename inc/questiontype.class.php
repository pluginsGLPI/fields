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
use Glpi\Form\Condition\ConditionHandler\ItemAsTextConditionHandler;
use Glpi\Form\Condition\ConditionHandler\ItemConditionHandler;
use Glpi\Form\Form;
use Glpi\Form\Migration\FormQuestionDataConverterInterface;
use Glpi\Form\Question;
use Glpi\Form\QuestionType\AbstractQuestionType;
use Glpi\Form\QuestionType\QuestionTypeCategoryInterface;
use Safe\Exceptions\JsonException;

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

    /**
     * Decode the stored default value of a cloned question, as it is passed
     * again to `formatDefaultValueForDB()` when the clone is added.
     */
    public static function preQuestionAdd(Question $question): void
    {
        if (
            !($question->input['clone'] ?? false)
            || ($question->input['type'] ?? null) !== self::class
            || !is_string($question->input['default_value'] ?? null)
        ) {
            return;
        }

        try {
            $question->input['default_value'] = json_decode($question->input['default_value'], true);
        } catch (JsonException) {
            // Not a JSON value, keep it as is
        }
    }

    /**
     * Repair default values that were JSON encoded several times by previous form clones.
     */
    public static function installBaseData(Migration $migration, $version): void
    {
        /** @var DBmysql $DB */
        global $DB;

        $question_table = Question::getTable();
        if (version_compare($version, '1.24.6', '>') || !$DB->tableExists($question_table)) {
            return;
        }

        $migration->displayMessage(__('Repair default values of cloned form questions', 'fields'));

        $questions = $DB->request([
            'SELECT' => ['id', 'default_value', 'extra_data'],
            'FROM'   => $question_table,
            'WHERE'  => ['type' => self::class],
        ]);
        foreach ($questions as $question) {
            if (!is_string($question['default_value']) || !is_string($question['extra_data'])) {
                continue;
            }

            $extra_data = \json_decode($question['extra_data'], true);
            $field      = new PluginFieldsField();
            if (!is_array($extra_data) || !$field->getFromDB((int) ($extra_data['field_id'] ?? 0))) {
                continue;
            }

            // Dropdowns and GLPI items store an array, other fields store a scalar value
            $is_array_value = $field->fields['type'] === 'glpi_item'
                || str_starts_with((string) $field->fields['type'], 'dropdown');

            $default_value = $question['default_value'];
            while (is_string($decoded = \json_decode($default_value, true))) {
                // Unwrap while the value is still a JSON encoded string (or array for array values)
                $inner = \json_decode($decoded, true);
                if (!is_string($inner) && (!$is_array_value || !is_array($inner))) {
                    break;
                }

                $default_value = $decoded;
            }

            if ($default_value !== $question['default_value']) {
                $DB->update(
                    $question_table,
                    ['default_value' => $default_value],
                    ['id' => $question['id']],
                );
            }
        }
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
        if ($question instanceof Question) {
            $default_value = $this->decodeDefaultValue($question);
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

    private function decodeDefaultValue(Question $question): mixed
    {
        if (empty($question->fields['default_value'])) {
            return null;
        }

        return json_decode($question->fields['default_value'], true);
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
        $default_value = $this->decodeDefaultValue($question);

        $itemtype = null;
        if (str_starts_with((string) $current_field->fields['type'], 'dropdown')) {
            if ($current_field->fields['type'] == 'dropdown') {
                $itemtype = PluginFieldsDropdown::getClassname($current_field->fields['name']);
            } else {
                $dropdown_matches = [];
                preg_match('/^dropdown-(?<class>.+)$/', (string) $current_field->fields['type'], $dropdown_matches);
                $itemtype = $dropdown_matches['class'];
            }
        }

        $twig = TemplateRenderer::getInstance();
        return $twig->render('@fields/question_type_end_user.html.twig', [
            'question'      => $question,
            'field'         => $current_field->fields,
            'default_value' => $default_value,
            'item'          => new Form(),
            'itemtype'      => $itemtype,
        ]);
    }

    #[Override]
    public function formatRawAnswer(mixed $answer, Question $question): string
    {
        $current_field_id = $this->getDefaultValueFieldId($question);
        if ($current_field_id === null) {
            throw new LogicException('No field configured for this question');
        }

        $current_field = PluginFieldsField::getById($current_field_id);

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
                $answer = $answer['items_id'];
                if (is_string($answer) || is_numeric($answer)) {
                    $answer = [$answer];
                }

                $itemtype = PluginFieldsDropdown::getClassname($current_field->fields['name']);
                return implode(', ', array_map(fn($opt_id) => $itemtype::getById($opt_id)?->fields['name'] ?? '', $answer));
            case 'yesno':
                return $answer ? __('Yes') : __('No');
            case 'datetime':
                return (new DateTime($answer))->format('Y-m-d H:i');
            case 'glpi_item':
                $itemtype = $answer['itemtype'];
                if (!is_a($itemtype, CommonDBTM::class, true)) {
                    return '';
                }

                $item = $answer['itemtype']::getById($answer['items_id']);
                if (!$item) {
                    return '';
                }

                return $item->fields['name'];
        }

        if (str_starts_with((string) $current_field->fields['type'], 'dropdown-')) {
            $itemtype = substr((string) $current_field->fields['type'], strlen('dropdown-'));
            if (!getItemForItemtype($itemtype)) {
                return '';
            }

            if (is_array($answer) && array_key_exists('items_id', $answer)) {
                $answer = $answer['items_id'];
            }

            if (!is_array($answer)) {
                $answer = [$answer];
            }

            $names = [];
            foreach ($answer as $items_id) {
                $item = $itemtype::getById((int) $items_id);
                if ($item) {
                    $names[] = $item->fields['name'];
                }
            }

            return implode(', ', $names);
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

    #[Override]
    public function getConditionHandlers(
        ?JsonFieldInterface $question_config,
    ): array {
        $condition_handlers = parent::getConditionHandlers($question_config);
        if (!$question_config instanceof PluginFieldsQuestionTypeExtraDataConfig) {
            throw new InvalidArgumentException();
        }

        if (!$question_config->getFieldId()) {
            return parent::getConditionHandlers($question_config);
        }

        // If the question is configured with a dropdown field, we add condition handlers to handle item and item as text conditions on the dropdown options
        $field = PluginFieldsField::getById($question_config->getFieldId());
        if ($field && str_starts_with((string) $field->fields['type'], 'dropdown')) {
            if ($field->fields['type'] == 'dropdown') {
                $itemtype = PluginFieldsDropdown::getClassname($field->fields['name']);
            } else {
                $dropdown_matches = [];
                preg_match('/^dropdown-(?<class>.+)$/', (string) $field->fields['type'], $dropdown_matches);
                $itemtype = $dropdown_matches['class'];
            }

            $condition_handlers = array_merge(
                $condition_handlers,
                [
                    new ItemConditionHandler($itemtype),
                    new ItemAsTextConditionHandler($itemtype),
                ],
            );
        }

        return $condition_handlers;
    }

    /**
     * Retrieve the default value block from the question's extra data
     *
     * @param Question|null $question The question to retrieve the default value from
     */
    public function getDefaultValueBlockId(?Question $question): ?int
    {
        if (!$question instanceof Question) {
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
     */
    public function getDefaultValueFieldId(?Question $question): ?int
    {
        if (!$question instanceof Question) {
            return null;
        }

        /** @var ?PluginFieldsQuestionTypeExtraDataConfig $config */
        $config = $this->getExtraDataConfig(json_decode($question->fields['extra_data'], true) ?? []);
        if ($config === null) {
            return null;
        }

        return $config->getFieldId();
    }

    private function getAvailableBlocks(): array
    {
        $field_container  = new PluginFieldsContainer();
        $available_blocks = [];

        // No session and not CLI: return early to avoid invalid SQL criterion from
        // getEntitiesRestrictCriteria() (returns entities_id = '' on integer column,
        // producing MySQL warning 1292). Consistent with the guard in setup.php.
        if (!Session::getLoginUserID() && !isCommandLine()) {
            return $available_blocks;
        }

        $entity_restrict = isCommandLine() ? [] : getEntitiesRestrictCriteria(PluginFieldsContainer::getTable(), '', '', true);

        $result           = $field_container->find([
            'is_active' => 1,
            'type'      => 'dom',
            'OR'        => [
                ['itemtypes' => ['LIKE', '%\"Ticket\"%']],
                ['itemtypes' => ['LIKE', '%\"Change\"%']],
                ['itemtypes' => ['LIKE', '%\"Problem\"%']],
            ],
        ] + $entity_restrict, 'name');
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
