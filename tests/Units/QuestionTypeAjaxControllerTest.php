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

declare(strict_types=1);

namespace GlpiPlugin\Field\Tests\Units;

use Entity;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use GlpiPlugin\Field\Tests\QuestionTypeTestCase;
use GlpiPlugin\Fields\Controller\QuestionTypeAjaxController;
use PluginFieldsProfile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . '/../QuestionTypeTestCase.php';

final class QuestionTypeAjaxControllerTest extends QuestionTypeTestCase
{
    public function testFormAdministratorGetsFieldContent(): void
    {
        $this->login();
        $this->setEntity($this->getTestRootEntity(true), true);

        $response = $this->invokeController();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertStringContainsString($this->fields['glpi_item']->fields['label'], (string) $response->getContent());
    }

    public function testUserWithoutFormUpdateRightIsDenied(): void
    {
        $this->login('post-only', 'postonly');

        $this->expectException(AccessDeniedHttpException::class);
        $this->invokeController();
    }

    public function testBlockOutsideActiveEntitiesIsDenied(): void
    {
        $this->login();
        $this->setEntity(getItemByTypeName(Entity::class, '_test_child_1', true), false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->invokeController();
    }

    public function testBlockWithoutProfileReadRightIsDenied(): void
    {
        $this->login();
        $this->setEntity($this->getTestRootEntity(true), true);

        $profile_right = new PluginFieldsProfile();
        $this->assertTrue($profile_right->getFromDBByCrit([
            'profiles_id'                 => $_SESSION['glpiactiveprofile']['id'],
            'plugin_fields_containers_id' => $this->block->getID(),
        ]));
        $this->updateItem(PluginFieldsProfile::class, $profile_right->getID(), ['right' => 0]);

        $this->expectException(AccessDeniedHttpException::class);
        $this->invokeController();
    }

    public function testUnknownBlockIsNotFound(): void
    {
        $this->login();
        $this->setEntity($this->getTestRootEntity(true), true);

        $this->expectException(NotFoundHttpException::class);
        $this->invokeController(999999);
    }

    private function invokeController(?int $block_id = null): Response
    {
        return (new QuestionTypeAjaxController())->__invoke(
            Request::create('', 'POST', ['block_id' => $block_id ?? $this->block->getID()]),
        );
    }
}
