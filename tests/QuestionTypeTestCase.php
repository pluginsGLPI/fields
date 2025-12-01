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

namespace GlpiPlugin\Field\Tests;

use Glpi\Controller\Form\RendererController;
use Glpi\Form\Form;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\FormTesterTrait;
use PluginFieldsContainer;
use PluginFieldsField;
use ReflectionClass;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Ticket;

abstract class QuestionTypeTestCase extends DbTestCase
{
    use FormTesterTrait;
    use FieldTestTrait;

    protected ?PluginFieldsContainer $block = null;

    protected ?PluginFieldsField $field     = null;

    public function createFieldAndContainer(): void
    {
        // Arrange: create block and field
        $this->block = $this->createFieldContainer([
            'label'       => 'Tickets Fields',
            'itemtypes'   => [Ticket::class],
            'type'        => 'dom',
            'is_active'   => 1,
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        $this->field = $this->createField([
            'label'                                     => 'GLPI Item',
            'type'                                      => 'glpi_item',
            PluginFieldsContainer::getForeignKeyField() => $this->block->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
        ]);
    }

    public function setUp(): void
    {
        $this->createFieldAndContainer();
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
    }

    protected function renderFormEditor(Form $form): Crawler
    {
        $this->login();
        ob_start();
        (new Form())->showForm($form->getId());
        return new Crawler(ob_get_clean());
    }

    protected function renderHelpdeskForm(Form $form): Crawler
    {
        $this->login();
        $controller = new RendererController();
        $response = $controller->__invoke(
            Request::create(
                '',
                'GET',
                [
                    'id' => $form->getID(),
                ],
            ),
        );
        return new Crawler($response->getContent());
    }

    protected function deleteSingletonInstance(array $classes)
    {
        foreach ($classes as $class) {
            $reflection_class = new ReflectionClass($class);
            if ($reflection_class->hasProperty('instance')) {
                $reflection_property = $reflection_class->getProperty('instance');
                $reflection_property->setValue(null, null);
            }

            if ($reflection_class->hasProperty('_instances')) {
                $reflection_property = $reflection_class->getProperty('_instances');
                $reflection_property->setValue(null, []);
            }
        }
    }
}
