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

namespace GlpiPlugin\Fields\Controller;

use Glpi\Controller\AbstractController;
use Glpi\Form\Form;
use PluginFieldsContainer;
use PluginFieldsField;
use PluginFieldsQuestionType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class QuestionTypeAjaxController extends AbstractController
{
    #[Route(
        path: 'GetFieldQuestionContent',
        name: 'get_field_question_content_ajax',
    )]
    public function __invoke(Request $request): Response
    {
        // Get the block_id and field_id from the request
        $block_id = $request->request->get('block_id');
        $field_id = $request->request->get('field_id');
        $default_value = $request->request->get('default_value');

        // Validate the block_id
        if (!$block_id || !is_numeric($block_id)) {
            return new Response('Invalid block_id', Response::HTTP_BAD_REQUEST);
        }

        // Get the question type instance
        $question_type = new PluginFieldsQuestionType();

        // Get available fields for the selected block
        $available_fields = $this->getFieldsFromBlock((int) $block_id);

        // If field_id is not provided or invalid, use the first available field
        $current_field_id = null;
        if ($field_id && is_numeric($field_id) && isset($available_fields[$field_id])) {
            $current_field_id = (int) $field_id;
        } else {
            $current_field_id = !empty($available_fields) ? (int) current(array_keys($available_fields)) : null;
        }

        if ($current_field_id === null) {
            return new Response('No fields available for this block', Response::HTTP_BAD_REQUEST);
        }

        // Get the container and field details
        $current_container = PluginFieldsContainer::getById((int) $block_id);
        $current_field = PluginFieldsField::getById($current_field_id);

        if (!$current_container || !$current_field || empty($current_field->fields)) {
            return new Response('Invalid container or field', Response::HTTP_BAD_REQUEST);
        }

        // Process default value if provided
        if ($default_value !== null && !empty($default_value)) {
            // If the field is multiple, convert the default value to an array
            if ($current_field->fields['multiple']) {
                if (!is_array($default_value)) {
                    $default_value = explode(',', $default_value);
                }
            }
        } else {
            $default_value = null;
        }

        return $this->render('@fields/question_type_administration.html.twig', [
            'question'          => null,
            'default_value'     => $default_value,
            'selected_field_id' => $current_field_id,
            'available_fields'  => $available_fields,
            'item'              => new Form(),
            'container'         => $current_container,
            'field'             => $current_field->fields,
            'is_ajax_reload'    => true,
        ]);
    }

    /**
     * Get fields from a block
     *
     * @param int|null $block_id
     * @return array
     */
    private function getFieldsFromBlock(?int $block_id): array
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
}
