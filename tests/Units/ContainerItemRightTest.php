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

use Computer;
use DbTestCase;
use Entity;
use GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use PluginFieldsContainer;
use PluginFieldsField;
use PluginFieldsProfile;

require_once __DIR__ . '/../FieldTestCase.php';

final class ContainerItemRightTest extends DbTestCase
{
    use FieldTestTrait;

    public function setUp(): void
    {
        GLPITestCase::setUp();

        global $CFG_GLPI;
        $CFG_GLPI["event_loglevel"] = 0;

        $this->login();
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    public function testCanUpdateTargetItemFollowsRightOnItem(): void
    {
        global $CFG_GLPI;
        $CFG_GLPI["event_loglevel"] = 0;

        $this->login();
        $entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $this->setEntity($entity_id, true);
        $computer = $this->createItem(Computer::class, [
            'name'        => 'Computer ' . $this->getUniqueString(),
            'entities_id' => $entity_id,
        ]);

        $this->assertTrue(PluginFieldsContainer::canUpdateTargetItem(Computer::class, $computer->getID()));

        $this->login('post-only', 'postonly');
        $this->setEntity($entity_id, true);

        $this->assertFalse(PluginFieldsContainer::canUpdateTargetItem(Computer::class, $computer->getID()));
    }

    public function testCanUpdateTargetItemRejectsInvalidItemtype(): void
    {
        global $CFG_GLPI;
        $CFG_GLPI["event_loglevel"] = 0;

        $this->login();

        $this->assertFalse(PluginFieldsContainer::canUpdateTargetItem('', 1));
    }

    public function testCanReadTargetItemFollowsRightOnItem(): void
    {
        global $CFG_GLPI;
        $CFG_GLPI["event_loglevel"] = 0;

        $this->login();
        $root_entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $child_entity = $this->createItem(Entity::class, [
            'name'        => 'Entity ' . $this->getUniqueString(),
            'entities_id' => $root_entity_id,
        ]);
        $computer = $this->createItem(Computer::class, [
            'name'        => 'Computer ' . $this->getUniqueString(),
            'entities_id' => $child_entity->getID(),
        ]);

        $this->assertTrue(PluginFieldsContainer::canReadTargetItem(Computer::class, $computer->getID()));

        $this->setEntity($root_entity_id, false);

        $this->assertFalse(PluginFieldsContainer::canReadTargetItem(Computer::class, $computer->getID()));
    }

    public function testCanReadTargetItemRejectsUnknownItem(): void
    {
        global $CFG_GLPI;
        $CFG_GLPI["event_loglevel"] = 0;

        $this->login();

        $this->assertFalse(PluginFieldsContainer::canReadTargetItem('', 1));
        $this->assertFalse(PluginFieldsContainer::canReadTargetItem(Computer::class, 999999));
    }

    public function testShowDomContainerRendersReadOnlyFieldsWithoutUpdateRight(): void
    {
        $entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $this->setEntity($entity_id, true);

        $container = $this->createFieldContainer([
            // Digits are spelled out in the generated system name, keep the label short and digit-free
            'label'        => 'Dom container',
            'type'         => 'dom',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => $entity_id,
            'is_recursive' => 1,
        ]);
        $field = $this->createField([
            'label'                                     => 'Dom field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $computer = $this->createItem(Computer::class, [
            'name'        => 'Computer ' . $this->getUniqueString(),
            'entities_id' => $entity_id,
        ]);

        $this->assertStringNotContainsString(
            'readonly',
            $this->renderDomContainer($container->getID(), $computer),
        );

        $this->setRightOnContainer($container->getID(), READ);

        $this->assertStringContainsString(
            'readonly',
            $this->renderDomContainer($container->getID(), $computer),
        );

        $this->setRightOnContainer($container->getID(), 0);

        $this->assertStringNotContainsString(
            $field->fields['name'],
            $this->renderDomContainer($container->getID(), $computer),
        );
    }

    private function renderDomContainer(int $containers_id, Computer $computer): string
    {
        ob_start();
        PluginFieldsField::showDomContainer($containers_id, $computer);

        return (string) ob_get_clean();
    }

    private function setRightOnContainer(int $containers_id, int $right): void
    {
        $profile_right = new PluginFieldsProfile();
        $this->assertTrue($profile_right->getFromDBByCrit([
            'profiles_id'                 => $_SESSION['glpiactiveprofile']['id'],
            'plugin_fields_containers_id' => $containers_id,
        ]));
        $this->updateItem(PluginFieldsProfile::class, $profile_right->getID(), ['right' => $right]);
    }
}
