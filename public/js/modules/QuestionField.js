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

export class GlpiPluginFieldsQuestionTypeField {
    /**
     * Move the field dropdown to the dropdown group
     * @param {jQuery} question - The question element
     */
    moveFieldDropdownToGroup(question) {
        const fieldDropdown = question.find('[data-glpi-form-editor-question-type-specific]')
            .find('select[data-glpi-form-editor-question-type-fields-field-id-selector]').parent();
        const dropdownGroup = question.find('.question-type-dropdown-group');

        // Remove from current parent if already in group
        dropdownGroup.find('select[data-glpi-form-editor-question-type-fields-field-id-selector]').parent().remove();

        // Append to the dropdown group
        dropdownGroup.append(fieldDropdown);
    }

    /**
     * Remove the field dropdown if it's in the dropdown group
     * @param {jQuery} question - The question element
     */
    removeFieldDropdownFromGroup(question) {
        const fieldDropdown = question.find('select[data-glpi-form-editor-question-type-fields-field-id-selector]');

        if (fieldDropdown.parent().closest('.question-type-dropdown-group').length === 1) {
            fieldDropdown.parent().remove();
        }
    }

    /**
     * Lock the mandatory input for the question
     * @param {jQuery} question - The question element
     * @param {boolean} isMandatory - Whether the question is mandatory
     */
    lockMandatoryInput(question, isMandatory) {
        question.find('[name="is_mandatory"][type="checkbox"], [data-glpi-form-editor-original-name="is_mandatory"][type="checkbox"]')
            .prop('disabled', true)
            .prop('checked', isMandatory);
        question.find('[name="is_mandatory"][type="hidden"], [data-glpi-form-editor-original-name="is_mandatory"][type="hidden"]')
            .val(isMandatory ? '1' : '0');
    }

    /**
     * Unlock the mandatory input for the question
     * @param {jQuery} question - The question element
     */
    unlockMandatoryInput(question) {
        question.find('[name="is_mandatory"][type="checkbox"], [data-glpi-form-editor-original-name="is_mandatory"][type="checkbox"]')
            .prop('disabled', false);
        question.find('[name="is_mandatory"][type="hidden"], [data-glpi-form-editor-original-name="is_mandatory"][type="hidden"]')
            .val('0');
    }

    /**
     * Reload question content via AJAX
     * @param {jQuery} question - The question element
     * @param {number} blockId - The block ID
     * @param {number} fieldId - The field ID
     */
    async reloadQuestionContent(question, blockId, fieldId) {
        // Get the current default value if it exists
        const defaultValueInput = question.find('[name="default_value"], [data-glpi-form-editor-original-name="default_value"]');
        let defaultValue = null;

        if (defaultValueInput.length > 0) {
            if (defaultValueInput.is('select[multiple]')) {
                defaultValue = defaultValueInput.val() || [];
            } else {
                defaultValue = defaultValueInput.val();
            }
        }

        // Find the container for the question type specific content
        const specificContainer = question.find('[data-glpi-form-editor-question-type-specific]');

        // Set loading state
        const editorController = question.closest('form[data-glpi-form-editor-container]').data('controller');
        editorController.setQuestionTypeSpecificLoadingState(question, true);

        // Make AJAX request to get updated content
        specificContainer.load(`${CFG_GLPI.root_doc}/plugins/fields/GetFieldQuestionContent`, {
            block_id: blockId,
            field_id: fieldId,
            default_value: defaultValue
        }, () => {
            // Move the field dropdown back to the group
            this.moveFieldDropdownToGroup(question);

            // Remove loading state
            editorController.setQuestionTypeSpecificLoadingState(question, false);

            // Mark form as having unsaved changes
            if (window.setHasUnsavedChanges) {
                window.setHasUnsavedChanges(true);
            }
        });
    }

    /**
     * Initialize event handlers for question type changes
     */
    initEventHandlers() {
        // Handle question type changes
        $(document).on('glpi-form-editor-question-type-changed', (event, question, type) => {
            if (type !== 'PluginFieldsQuestionType') {
                this.removeFieldDropdownFromGroup(question);
                this.unlockMandatoryInput(question);
            } else {
                this.moveFieldDropdownToGroup(question);
            }
        });

        // Handle block_id (sub-type) changes
        $(document).on('glpi-form-editor-question-sub-type-changed', async (event, question, subType) => {
            // Check if this is a PluginFieldsQuestionType question
            const questionType = question.find('[name="type"], [data-glpi-form-editor-original-name="type"]').val();
            if (questionType !== 'PluginFieldsQuestionType') {
                return;
            }

            // Get the field_id selector
            const fieldDropdown = question.find('select[data-glpi-form-editor-question-type-fields-field-id-selector]');
            const fieldId = fieldDropdown.val();

            // Reload the question content with the new block_id and current field_id
            if (subType && fieldId) {
                await this.reloadQuestionContent(question, subType, fieldId);
            }
        });

        // Handle field_id changes
        $(document).on('change', 'select[data-glpi-form-editor-question-type-fields-field-id-selector]', async (event) => {
            const fieldDropdown = $(event.target);
            const question = fieldDropdown.closest('[data-glpi-form-editor-question]');

            // Check if this is a PluginFieldsQuestionType question
            const questionType = question.find('[name="type"], [data-glpi-form-editor-original-name="type"]').val();
            if (questionType !== 'PluginFieldsQuestionType') {
                return;
            }

            // Get the block_id (sub-type)
            const blockDropdown = question.find('[data-glpi-form-editor-question-sub-type-selector]');
            const blockId = blockDropdown.val();
            const fieldId = fieldDropdown.val();

            // Reload the question content with the current block_id and new field_id
            if (blockId && fieldId) {
                await this.reloadQuestionContent(question, blockId, fieldId);
            }
        });
    }

    /**
     * Initialize an existing question
     * @param {string} rand - The random identifier
     */
    initExistingQuestion(rand) {
        const fieldDropdown = $(`select[data-glpi-form-editor-question-type-fields-field-id-selector="${rand}"]`);
        const question = fieldDropdown.closest('[data-glpi-form-editor-question]');
        this.moveFieldDropdownToGroup(question);
    }
}
