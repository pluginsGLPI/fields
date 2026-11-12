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
use Entity;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use Monitor;
use PluginFieldsContainer;
use PluginFieldsProfile;
use Printer;

require_once __DIR__ . '/../FieldTestCase.php';

final class ContainerGetEntriesTest extends DbTestCase
{
    use FieldTestTrait;

    public function setUp(): void
    {
        GLPITestCase::setUp();
        $this->login();
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    public function testGetEntriesFollowsProfileRightsAndEntities(): void
    {
        $root_entity_id  = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $child_entity_id = getItemByTypeName(Entity::class, '_test_child_1', true);

        // Recursive container on the root entity, readable
        $readable = $this->createTabContainer(Computer::class, $root_entity_id, true);
        $this->setRightOnContainer($readable->getID(), READ);

        // Recursive container on the root entity, without right
        $no_right = $this->createTabContainer(Monitor::class, $root_entity_id, true);
        $this->setRightOnContainer($no_right->getID(), 0);

        // Non recursive container on the root entity, writable
        $not_recursive = $this->createTabContainer(Printer::class, $root_entity_id, false);
        $this->setRightOnContainer($not_recursive->getID(), CREATE);

        // From the root entity (with sub-entities), readable and writable containers are listed
        $this->setEntity($root_entity_id, true);
        $entries = PluginFieldsContainer::getEntries();
        $this->assertContains(Computer::class, $entries);
        $this->assertNotContains(Monitor::class, $entries);
        $this->assertContains(Printer::class, $entries);

        // From a child entity only, the non recursive container of the parent is not listed
        $this->setEntity($child_entity_id, false);
        $entries = PluginFieldsContainer::getEntries();
        $this->assertContains(Computer::class, $entries);
        $this->assertNotContains(Monitor::class, $entries);
        $this->assertNotContains(Printer::class, $entries);
    }

    public function testGetEntriesWithoutActiveProfile(): void
    {
        $root_entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $this->createTabContainer(Computer::class, $root_entity_id, true);
        $this->setEntity($root_entity_id, true);

        $profile = $_SESSION['glpiactiveprofile'];
        try {
            $_SESSION['glpiactiveprofile']['id'] = 0;
            $this->assertSame([], PluginFieldsContainer::getEntries());

            unset($_SESSION['glpiactiveprofile']);
            $this->assertSame([], PluginFieldsContainer::getEntries());
        } finally {
            $_SESSION['glpiactiveprofile'] = $profile;
        }
    }

    public function testGetRightsOnContainersMatchesGetRightOnContainer(): void
    {
        $root_entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $containers     = [
            $this->createTabContainer(Computer::class, $root_entity_id, true),
            $this->createTabContainer(Monitor::class, $root_entity_id, true),
            $this->createTabContainer(Printer::class, $root_entity_id, true),
        ];
        $this->setRightOnContainer($containers[0]->getID(), 0);
        $this->setRightOnContainer($containers[1]->getID(), READ);
        $this->setRightOnContainer($containers[2]->getID(), CREATE);

        $profile_id = (int) $_SESSION['glpiactiveprofile']['id'];
        $ids        = array_map(static fn($container) => $container->getID(), $containers);
        $rights     = PluginFieldsProfile::getRightsOnContainers($profile_id, $ids);

        foreach ($ids as $id) {
            $this->assertSame(PluginFieldsProfile::getRightOnContainer($profile_id, $id), $rights[$id] ?? 0);
        }
        $this->assertSame([0, READ, CREATE], array_map(static fn($id) => $rights[$id] ?? 0, $ids));
        $this->assertSame([], PluginFieldsProfile::getRightsOnContainers($profile_id, []));
        $this->assertSame(0, PluginFieldsProfile::getRightOnContainer($profile_id, 999999));
    }

    private function createTabContainer(string $itemtype, int $entities_id, bool $is_recursive): PluginFieldsContainer
    {
        return $this->createFieldContainer([
            'label'        => 'Tab container ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [$itemtype],
            'is_active'    => 1,
            'entities_id'  => $entities_id,
            'is_recursive' => $is_recursive ? 1 : 0,
        ]);
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
