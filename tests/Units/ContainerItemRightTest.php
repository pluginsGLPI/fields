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

use Problem;
use Problem_User;
use Profile;
use Ticket;
use Ticket_User;
use User;
use CommonITILActor;
use CommonDBTM;
use Computer;
use Entity;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
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
        $this->login();
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    public function testCanUpdateTargetItemFollowsRightOnItem(): void
    {
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
        $this->login();

        $this->assertFalse(PluginFieldsContainer::canUpdateTargetItem('', 1));
    }

    public function testCanReadTargetItemFollowsRightOnItem(): void
    {
        $this->login();
        $root_entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $child_entity = $this->createItem(Entity::class, [
            'name'         => 'Entity ' . $this->getUniqueString(),
            'entities_id'  => $root_entity_id,
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
        $this->login();

        $this->assertFalse(PluginFieldsContainer::canReadTargetItem('', 1));
        $this->assertFalse(PluginFieldsContainer::canReadTargetItem(Computer::class, 999999));
    }

    public function testShowDomContainerRendersReadOnlyFieldsWithoutUpdateRight(): void
    {
        $entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $this->setEntity($entity_id, true);

        $container = $this->createFieldContainer([
            'label'        => 'Dom container ' . $this->getUniqueString(),
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

    /**
     * Test rendering: helpdesk observer cannot edit fields
     * An observer on a ticket should see fields rendered as readonly.
     */
    public function testDomContainerRenderReadOnlyForHelpdeskObserver(): void
    {
        $this->login();
        $entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $this->setEntity($entity_id, true);

        // Use Problem (also a CommonITILObject) to avoid conflicting with a pre-existing Ticket DOM container
        // in the test database (mailcollectorcontainer).
        $container = $this->createFieldContainer([
            'label'        => 'Observer Readonly Container',
            'type'         => 'dom',
            'itemtypes'    => [Problem::class],
            'is_active'    => 1,
            'entities_id'  => $entity_id,
            'is_recursive' => 1,
        ]);
        $this->createField([
            'label'                                     => 'Observer Test Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);

        $problem = $this->createItem(Problem::class, [
            'name'        => 'Problem for observer test',
            'content'     => 'Test',
            'entities_id' => $entity_id,
        ]);

        // Create a helpdesk observer role
        $observer_profile = $this->createItem(Profile::class, [
            'name'      => 'Helpdesk_Observer_' . $this->getUniqueString(),
            'interface' => 'helpdesk',
        ]);
        // Grant READ|UPDATE on container so that $right > READ is true — only canUpdateItem() should block editing.
        $this->setRightOnContainerForProfile($observer_profile->getID(), $container->getID(), READ | UPDATE);

        // Create observer user (not requester)
        $observer_username = 'observer_' . $this->getUniqueString();
        $this->createItem(User::class, [
            'name'          => $observer_username,
            'password'      => 'Test1234!',
            'password2'     => 'Test1234!',
            'profiles_id'   => $observer_profile->getID(),
            '_profiles_id'  => $observer_profile->getID(),
            '_entities_id'  => $entity_id,
            '_is_recursive' => true,
        ], ['password', 'password2']);

        // Add observer to problem
        $this->createItem(Problem_User::class, [
            'problems_id' => $problem->getID(),
            'users_id'    => getItemByTypeName(User::class, $observer_username, true),
            'type'        => CommonITILActor::OBSERVER,
        ]);

        // Login as observer and render
        $this->login($observer_username, 'Test1234!');
        $this->setEntity($entity_id, true);

        $html = $this->renderDomContainerForAny($container->getID(), $problem);

        // Assert: field must be rendered with readonly attribute
        $this->assertStringContainsString(
            'readonly',
            $html,
            'Fields must be rendered as readonly for helpdesk observers.',
        );
    }

    /**
     * Test rendering: new item creation allows editing even for a user who cannot update existing items.
     * isNewItem() must bypass the canUpdateItem() restriction so users can fill fields on creation.
     */
    public function testDomContainerRenderEditableOnNewTicketCreation(): void
    {
        $this->login();
        $entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $this->setEntity($entity_id, true);

        // Use Problem (also a CommonITILObject) to avoid conflicting with the pre-existing Ticket DOM container.
        $container = $this->createFieldContainer([
            'label'        => 'New Problem Container',
            'type'         => 'dom',
            'itemtypes'    => [Problem::class],
            'is_active'    => 1,
            'entities_id'  => $entity_id,
            'is_recursive' => 1,
        ]);
        $this->createField([
            'label'                                     => 'New Problem Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);

        // Use a helpdesk observer profile with READ|UPDATE on the container but no UPDATE on problem items.
        // This ensures that only isNewItem() — not canUpdateItem() — makes the fields editable.
        $observer_profile = $this->createItem(Profile::class, [
            'name'      => 'Helpdesk_ObserverNew_' . $this->getUniqueString(),
            'interface' => 'helpdesk',
        ]);
        $this->setRightOnContainerForProfile($observer_profile->getID(), $container->getID(), READ | UPDATE);

        $observer_username = 'observer_new_' . $this->getUniqueString();
        $this->createItem(User::class, [
            'name'          => $observer_username,
            'password'      => 'Test1234!',
            'password2'     => 'Test1234!',
            'profiles_id'   => $observer_profile->getID(),
            '_profiles_id'  => $observer_profile->getID(),
            '_entities_id'  => $entity_id,
            '_is_recursive' => true,
        ], ['password', 'password2']);

        $this->login($observer_username, 'Test1234!');
        $this->setEntity($entity_id, true);

        // New problem has no ID: isNewItem() === true, so fields must be editable
        // even if canUpdateItem() would return false for an existing problem.
        $new_problem = new Problem();
        $new_problem->fields['entities_id'] = $entity_id;

        $html = $this->renderDomContainerForAny($container->getID(), $new_problem);

        $this->assertStringNotContainsString(
            'readonly',
            $html,
            'Fields must remain editable when creating a new problem, even for a user who cannot update existing ones.',
        );
    }

    /**
     * Test that preItemUpdate drops _plugin_fields_data for a central-interface user without UPDATE right.
     * This covers the gap left by the original fix that only checked helpdesk + canRequesterUpdateItem().
     */
    public function testPreItemUpdateDropsPluginFieldsDataForCentralUserWithoutUpdateRight(): void
    {
        $this->login();
        $entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $this->setEntity($entity_id, true);

        $container = $this->createFieldContainer([
            'label'        => 'PreUpdate Guard Container',
            'type'         => 'dom',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => $entity_id,
            'is_recursive' => 1,
        ]);
        $this->createField([
            'label'                                     => 'Guard Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);

        $computer = $this->createItem(Computer::class, [
            'name'        => 'Guard Computer',
            'entities_id' => $entity_id,
        ]);

        // Central profile with READ access on the container but no Computer UPDATE right.
        // A new profile has no profilerights, so canUpdate() / canUpdateItem() returns false for Computer.
        $readonly_profile = $this->createItem(Profile::class, [
            'name'      => 'Central_Readonly_' . $this->getUniqueString(),
            'interface' => 'central',
        ]);
        $this->setRightOnContainerForProfile($readonly_profile->getID(), $container->getID(), READ);

        $readonly_username = 'readonly_central_' . $this->getUniqueString();
        $this->createItem(User::class, [
            'name'          => $readonly_username,
            'password'      => 'Test1234!',
            'password2'     => 'Test1234!',
            'profiles_id'   => $readonly_profile->getID(),
            '_profiles_id'  => $readonly_profile->getID(),
            '_entities_id'  => $entity_id,
            '_is_recursive' => true,
        ], ['password', 'password2']);

        $this->login($readonly_username, 'Test1234!');
        $this->setEntity($entity_id, true);

        $computer->getFromDB($computer->getID());
        $computer->input = [
            'id'                  => $computer->getID(),
            '_plugin_fields_data' => [
                'plugin_fields_containers_id' => $container->getID(),
                'items_id'                    => $computer->getID(),
            ],
        ];

        PluginFieldsContainer::preItemUpdate($computer);

        $this->assertArrayNotHasKey(
            '_plugin_fields_data',
            $computer->input,
            'preItemUpdate must drop _plugin_fields_data when a central user cannot update the item.',
        );
    }

    public function testPreItemUpdateDropsPluginFieldsDataForHelpdeskObserver(): void
    {
        [$ticket, $container, $field_name] = $this->createTicketWithHelpdeskActor(CommonITILActor::OBSERVER);

        $ticket->input = [
            'id'                  => $ticket->getID(),
            '_plugin_fields_data' => [
                'plugin_fields_containers_id' => $container->getID(),
                'items_id'                    => $ticket->getID(),
                $field_name                   => 'new value',
            ],
        ];

        PluginFieldsContainer::preItemUpdate($ticket);

        $this->assertArrayNotHasKey('_plugin_fields_data', $ticket->input);
    }

    public function testPreItemUpdateKeepsPluginFieldsDataForHelpdeskRequester(): void
    {
        [$ticket, $container, $field_name] = $this->createTicketWithHelpdeskActor(CommonITILActor::REQUESTER);

        $ticket->input = [
            'id'                  => $ticket->getID(),
            '_plugin_fields_data' => [
                'plugin_fields_containers_id' => $container->getID(),
                'items_id'                    => $ticket->getID(),
                $field_name                   => 'new value',
            ],
        ];

        $this->assertTrue(PluginFieldsContainer::preItemUpdate($ticket));
        $this->assertArrayHasKey('_plugin_fields_data', $ticket->input);
    }

    /**
     * Create a ticket with a helpdesk user as given actor, then log in as that user.
     *
     * @return array{0: Ticket, 1: PluginFieldsContainer, 2: string}
     */
    private function createTicketWithHelpdeskActor(int $actor_type): array
    {
        $this->login();
        $entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $this->setEntity($entity_id, true);

        $container = $this->createFieldContainer([
            'label'        => 'Helpdesk Actor Container',
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => $entity_id,
            'is_recursive' => 1,
        ]);
        $field = $this->createField([
            'label'                                     => 'Helpdesk Actor Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);

        $profile = $this->createItem(Profile::class, [
            'name'      => 'Helpdesk_Actor_' . $this->getUniqueString(),
            'interface' => 'helpdesk',
        ]);
        $this->setRightOnContainerForProfile($profile->getID(), $container->getID(), READ | UPDATE);

        $username = 'helpdesk_actor_' . $this->getUniqueString();
        $user = $this->createItem(User::class, [
            'name'          => $username,
            'password'      => 'Test1234!',
            'password2'     => 'Test1234!',
            'profiles_id'   => $profile->getID(),
            '_profiles_id'  => $profile->getID(),
            '_entities_id'  => $entity_id,
            '_is_recursive' => true,
        ], ['password', 'password2']);

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Ticket for helpdesk actor test',
            'content'     => 'Test',
            'entities_id' => $entity_id,
        ]);
        $this->createItem(Ticket_User::class, [
            'tickets_id' => $ticket->getID(),
            'users_id'   => $user->getID(),
            'type'       => $actor_type,
        ]);

        $this->login($username, 'Test1234!');
        $this->setEntity($entity_id, true);
        $ticket->getFromDB($ticket->getID());

        return [$ticket, $container, $field->fields['name']];
    }

    private function renderDomContainerForAny(int $containers_id, CommonDBTM $item): string
    {
        ob_start();
        PluginFieldsField::showDomContainer($containers_id, $item);
        return (string) ob_get_clean();
    }

    private function setRightOnContainerForProfile(int $profile_id, int $containers_id, int $right): void
    {
        $profile_right = new PluginFieldsProfile();
        if ($profile_right->getFromDBByCrit([
            'profiles_id'                 => $profile_id,
            'plugin_fields_containers_id' => $containers_id,
        ])) {
            $this->updateItem(PluginFieldsProfile::class, $profile_right->getID(), ['right' => $right]);
        } else {
            $this->createItem(PluginFieldsProfile::class, [
                'profiles_id'                 => $profile_id,
                'plugin_fields_containers_id' => $containers_id,
                'right'                       => $right,
            ]);
        }
    }
}
