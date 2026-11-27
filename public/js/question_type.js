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

// Question type field: init (existing or new question) then lock the mandatory input.
// init is "none" on an AJAX reload, where only the lock applies.
$('[data-fields-question-type]:not([data-fields-ready])').each(function () {
    const marker = $(this).attr('data-fields-ready', '');
    const rand = marker.attr('data-fields-question-type');
    const init = marker.attr('data-fields-question-type-init');
    const mandatory = marker.attr('data-fields-mandatory') === '1' ? 1 : 0;

    import(CFG_GLPI.root_doc + GLPI_PLUGINS_PATH.fields + '/js/modules/QuestionField.js').then((m) => {
        const questionField = new m.GlpiPluginFieldsQuestionTypeField();

        if (init === 'existing') {
            questionField.initExistingQuestion(rand);
        } else if (init === 'new') {
            questionField.initEventHandlers();
        }

        questionField.lockMandatoryInput(
            $('[data-glpi-form-editor-question-type-fields-field-id-selector="' + rand + '"]').closest('[data-glpi-form-editor-question-details]'),
            mandatory
        );
    });
});
