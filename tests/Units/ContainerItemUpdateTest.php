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
 * along with Fields. If not, see <http://www.gnu.org/licenses/\>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2013-2023 by Fields plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/fields
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Field\Tests\Units;

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use PluginFieldsContainer;
use Ticket;

require_once __DIR__ . '/../FieldTestCase.php';

/**
 * Tests covering ticket/ITIL updates containing both native fields and plugin Fields.
 *
 * These tests reproduce the bug where API update calls update only native fields
 * and silently skip plugin fields injected by the Fields plugin.
 *
 * Scenarios covered:
 *  - Hook registration guard: CRUD hooks are registered even without an active session
 *  - API-like context: update and create with plugin fields after session-less init
 *  - Single DOM container, update with both native and plugin fields (main bug scenario)
 *  - Single DOM container, update with explicit c_id in payload
 *  - DOM + TAB containers, each updated independently via c_id
 *  - Constraint: only one DOM container per itemtype
 *  - Create with plugin fields
 *  - Successive updates round-trip
 *  - Ticket update without plugin fields (regression guard)
 */
final class ContainerItemUpdateTest extends DbTestCase
{
    use FieldTestTrait;

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Read Fields plugin values for a given item from its container table.
     *
     * @param string $itemtype  e.g. Ticket::class
     * @param int    $items_id  The item ID
     * @param int    $c_id      The container ID
     * @return array|false      Row from the container table, or false if none
     */
    private function getPluginFieldValues(string $itemtype, int $items_id, int $c_id): array|false
    {
        $container = new PluginFieldsContainer();
        $container->getFromDB($c_id);

        $classname = PluginFieldsContainer::getClassname($itemtype, $container->fields['name']);
        $obj = new $classname();
        $rows = $obj->find([
            'plugin_fields_containers_id' => $c_id,
            'items_id'                    => $items_id,
        ]);

        return count($rows) > 0 ? current($rows) : false;
    }

    /**
     * Update a ticket, skipping plugin field verification in the native item.
     * Plugin fields are stored in a separate table and cannot be retrieved from
     * $item->fields, so they must be excluded from DbTestCase::updateItem checks.
     */
    private function updateTicket(int $ticket_id, array $input, array $plugin_field_names = []): void
    {
        $this->updateItem(Ticket::class, $ticket_id, $input, $plugin_field_names);
    }

    /**
     * Simulate REST API lifecycle: clear session, re-init plugin (as during
     * GLPI boot with no session cookie), then restore the session (as the
     * API does via token auth after boot).
     */
    private function simulateApiBoot(): array
    {
        $saved_session = $_SESSION;
        $_SESSION = [];

        /** @var array $PLUGIN_HOOKS */
        global $PLUGIN_HOOKS;
        unset(
            $PLUGIN_HOOKS['pre_item_update']['fields'],
            $PLUGIN_HOOKS['pre_item_add']['fields'],
            $PLUGIN_HOOKS['item_add']['fields'],
            $PLUGIN_HOOKS['pre_item_purge']['fields'],
        );

        plugin_init_fields();

        // Restore session (simulates API::retrieveSession with token auth)
        $_SESSION = $saved_session;

        return $saved_session;
    }

    // -----------------------------------------------------------------------
    // Tests: hook registration guard (catches the API regression)
    // -----------------------------------------------------------------------

    /**
     * CRUD hooks must be registered even when no PHP session / login exists.
     *
     * This is the root cause of the API regression: plugin_init_fields() runs
     * during GLPI boot, before the REST API session is established (token auth).
     * If the hooks are only registered when Session::getLoginUserID() is truthy,
     * they are silently skipped for API calls.
     */
    public function testCrudHooksRegisteredWithoutSession(): void
    {
        $this->login();

        $this->createFieldContainer([
            'label'        => 'Hook Guard Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        // Simulate API boot: no session, no login
        $saved_session = $_SESSION;
        $_SESSION = [];

        /** @var array $PLUGIN_HOOKS */
        global $PLUGIN_HOOKS;
        unset(
            $PLUGIN_HOOKS['pre_item_update']['fields'],
            $PLUGIN_HOOKS['pre_item_add']['fields'],
            $PLUGIN_HOOKS['item_add']['fields'],
            $PLUGIN_HOOKS['pre_item_purge']['fields'],
        );

        try {
            plugin_init_fields();

            $this->assertArrayHasKey(
                Ticket::class,
                $PLUGIN_HOOKS['pre_item_update']['fields'] ?? [],
                'pre_item_update hook must be registered without active session',
            );
            $this->assertArrayHasKey(
                Ticket::class,
                $PLUGIN_HOOKS['pre_item_add']['fields'] ?? [],
                'pre_item_add hook must be registered without active session',
            );
            $this->assertArrayHasKey(
                Ticket::class,
                $PLUGIN_HOOKS['item_add']['fields'] ?? [],
                'item_add hook must be registered without active session',
            );
            $this->assertArrayHasKey(
                Ticket::class,
                $PLUGIN_HOOKS['pre_item_purge']['fields'] ?? [],
                'pre_item_purge hook must be registered without active session',
            );
        } finally {
            $_SESSION = $saved_session;
            plugin_init_fields();
        }
    }

    // -----------------------------------------------------------------------
    // Tests: API-like context (session-less init then session restore)
    // -----------------------------------------------------------------------

    /**
     * Simulate the full REST API lifecycle:
     * 1. Boot GLPI (init plugins) — no session exists yet
     * 2. Restore session (API token auth)
     * 3. Update a ticket with plugin fields
     * 4. Assert plugin fields are persisted
     *
     * This catches the exact regression reported by the client.
     */
    public function testUpdateTicketInApiLikeContext(): void
    {
        $this->login();

        $container = $this->createFieldContainer([
            'label'        => 'API Context Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'API Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $field_name = $field->fields['name'];

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Ticket for API test',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        $this->simulateApiBoot();

        $ticket_item = new Ticket();
        $result = $ticket_item->update([
            'id'        => $ticket->getID(),
            'name'      => 'API updated name',
            $field_name => 'api plugin value',
        ]);
        $this->assertTrue($result);

        $ticket->getFromDB($ticket->getID());
        $this->assertSame('API updated name', $ticket->fields['name']);

        $plugin_row = $this->getPluginFieldValues(Ticket::class, $ticket->getID(), $container->getID());
        $this->assertNotFalse($plugin_row, 'Plugin fields row must exist after API-like update.');
        $this->assertSame('api plugin value', $plugin_row[$field_name]);
    }

    /**
     * Same as above but for ticket creation (POST in the API).
     */
    public function testCreateTicketInApiLikeContext(): void
    {
        $this->login();

        $container = $this->createFieldContainer([
            'label'        => 'API Create Context Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'API Create Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $field_name = $field->fields['name'];

        $this->simulateApiBoot();

        $ticket = new Ticket();
        $ticket_id = $ticket->add([
            'name'        => 'API created ticket',
            'content'     => 'Test creation',
            'entities_id' => 0,
            $field_name   => 'created via api',
        ]);
        $this->assertGreaterThan(0, $ticket_id);

        $plugin_row = $this->getPluginFieldValues(Ticket::class, $ticket_id, $container->getID());
        $this->assertNotFalse($plugin_row, 'Plugin fields row must exist after API-like creation.');
        $this->assertSame('created via api', $plugin_row[$field_name]);
    }

    /**
     * Update a ticket with explicit c_id through an API-like context.
     */
    public function testUpdateTicketWithExplicitCidInApiLikeContext(): void
    {
        $this->login();

        $container = $this->createFieldContainer([
            'label'        => 'API Explicit CID Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'API CID Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $field_name = $field->fields['name'];

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Ticket for API c_id test',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        $this->simulateApiBoot();

        $ticket_item = new Ticket();
        $result = $ticket_item->update([
            'id'        => $ticket->getID(),
            'c_id'      => $container->getID(),
            $field_name => 'explicit cid api value',
        ]);
        $this->assertTrue($result);

        $plugin_row = $this->getPluginFieldValues(Ticket::class, $ticket->getID(), $container->getID());
        $this->assertNotFalse($plugin_row, 'Plugin fields row must exist after API-like update with c_id.');
        $this->assertSame('explicit cid api value', $plugin_row[$field_name]);
    }

    // -----------------------------------------------------------------------
    // Tests: single container, auto-detection (no c_id in payload)
    // -----------------------------------------------------------------------

    /**
     * Main bug scenario: when a ticket is updated with both native and plugin fields,
     * BOTH should be persisted.
     * The container is found automatically (no c_id in payload).
     *
     * This is the scenario reported by the client via the legacy REST API:
     * PUT /apirest.php/Ticket/1  {"input": {"name": "new name", "mycustomfield": "value"}}
     * Only native fields were updated, plugin fields were silently ignored.
     */
    public function testUpdateTicketNativeAndPluginFieldsWithAutoDetect(): void
    {
        $this->login();

        // Arrange: one DOM container with one text field for Ticket
        $container = $this->createFieldContainer([
            'label'        => 'Main Bug Test Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'My Text Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $field_name = $field->fields['name'];

        // Arrange: create a ticket without plugin fields first
        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Initial name',
            'content'     => 'Initial content',
            'entities_id' => 0,
        ]);

        // Act: update with BOTH native and plugin fields (no c_id) — simulating REST API call
        $this->updateTicket($ticket->getID(), [
            'id'        => $ticket->getID(),
            'name'      => 'Updated name',
            $field_name => 'my plugin value',
        ], [$field_name]);

        // Assert: native field updated
        $ticket->getFromDB($ticket->getID());
        $this->assertSame('Updated name', $ticket->fields['name']);

        // Assert: plugin field also updated (main bug scenario)
        $plugin_row = $this->getPluginFieldValues(Ticket::class, $ticket->getID(), $container->getID());
        $this->assertNotFalse($plugin_row, 'Plugin fields row should exist after update.');
        $this->assertSame('my plugin value', $plugin_row[$field_name]);
    }

    /**
     * Plugin fields should also be saved when the ticket is CREATED
     * with plugin fields in the input.
     */
    public function testCreateTicketWithPluginFields(): void
    {
        $this->login();

        $container = $this->createFieldContainer([
            'label'        => 'Create Test Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'Creation Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $field_name = $field->fields['name'];

        // Act: create ticket with plugin field inline
        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Ticket with plugin fields',
            'content'     => 'Test',
            'entities_id' => 0,
            $field_name   => 'created value',
        ], [$field_name]);

        // Assert: plugin field was saved at creation
        $plugin_row = $this->getPluginFieldValues(Ticket::class, $ticket->getID(), $container->getID());
        $this->assertNotFalse($plugin_row, 'Plugin fields row should exist after creation.');
        $this->assertSame('created value', $plugin_row[$field_name]);
    }

    /**
     * Plugin fields should be updated when an explicit c_id is provided in the
     * update payload — the documented way to target a specific container.
     *
     * REST API equivalent:
     * PUT /apirest.php/Ticket/1  {"input": {"c_id": 5, "mycustomfield": "value"}}
     */
    public function testUpdateTicketWithExplicitContainerId(): void
    {
        $this->login();

        $container = $this->createFieldContainer([
            'label'        => 'Explicit CID Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'Explicit Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $field_name = $field->fields['name'];

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Ticket for explicit c_id',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        // Act: update with explicit c_id in payload alongside plugin field
        $this->updateTicket($ticket->getID(), [
            'id'        => $ticket->getID(),
            'name'      => 'Updated with c_id',
            'c_id'      => $container->getID(),
            $field_name => 'explicit value',
        ], [$field_name, 'c_id']);

        // Assert: plugin field updated
        $plugin_row = $this->getPluginFieldValues(Ticket::class, $ticket->getID(), $container->getID());
        $this->assertNotFalse($plugin_row, 'Plugin fields row should exist after update with explicit c_id.');
        $this->assertSame('explicit value', $plugin_row[$field_name]);
    }

    /**
     * Updating only native ticket fields (without any plugin field)
     * must still work and must not crash the plugin hook.
     * Regression guard: the plugin hook must not break native-only updates.
     */
    public function testUpdateTicketNativeFieldsOnlyRegressionGuard(): void
    {
        $this->login();

        $container = $this->createFieldContainer([
            'label'        => 'Regression Guard Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $this->createField([
            'label'                                     => 'Unused Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Ticket only native',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        // Act: update only native fields — plugin hook must not break this
        $this->updateTicket($ticket->getID(), [
            'id'   => $ticket->getID(),
            'name' => 'New native name',
        ]);

        // Assert: native field updated
        $ticket->getFromDB($ticket->getID());
        $this->assertSame('New native name', $ticket->fields['name']);
    }

    // -----------------------------------------------------------------------
    // Tests: DOM container + TAB container for the same itemtype
    // -----------------------------------------------------------------------

    /**
     * A ticket can have one DOM container and one TAB container.
     * Each container's fields are updateable via its explicit c_id.
     */
    public function testUpdateTicketDomAndTabContainersWithExplicitCid(): void
    {
        $this->login();

        // DOM container
        $dom_container = $this->createFieldContainer([
            'label'        => 'DOM Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
        $dom_field = $this->createField([
            'label'                                     => 'DOM Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $dom_container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $dom_field_name = $dom_field->fields['name'];

        // TAB container (multiple TAB containers per itemtype ARE allowed)
        $tab_container = $this->createFieldContainer([
            'label'        => 'TAB Container',
            'type'         => 'tab',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
        $tab_field = $this->createField([
            'label'                                     => 'TAB Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $tab_container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $tab_field_name = $tab_field->fields['name'];

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'DOM + TAB ticket',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        // Act: update DOM container fields with explicit c_id
        $this->updateTicket($ticket->getID(), [
            'id'            => $ticket->getID(),
            'c_id'          => $dom_container->getID(),
            $dom_field_name => 'dom value',
        ], [$dom_field_name, 'c_id']);

        // Act: update TAB container fields with explicit c_id
        $this->updateTicket($ticket->getID(), [
            'id'            => $ticket->getID(),
            'c_id'          => $tab_container->getID(),
            $tab_field_name => 'tab value',
        ], [$tab_field_name, 'c_id']);

        // Assert: DOM container field updated
        $dom_row = $this->getPluginFieldValues(Ticket::class, $ticket->getID(), $dom_container->getID());
        $this->assertNotFalse($dom_row, 'DOM container plugin fields row should exist.');
        $this->assertSame('dom value', $dom_row[$dom_field_name]);

        // Assert: TAB container field updated
        $tab_row = $this->getPluginFieldValues(Ticket::class, $ticket->getID(), $tab_container->getID());
        $this->assertNotFalse($tab_row, 'TAB container plugin fields row should exist.');
        $this->assertSame('tab value', $tab_row[$tab_field_name]);
    }

    /**
     * The plugin must prevent creating two DOM containers for the same itemtype.
     * This is an intentional design constraint.
     */
    public function testOnlyOneDomContainerAllowedPerItemtype(): void
    {
        $this->login();

        // Create first DOM container for Ticket
        $this->createFieldContainer([
            'label'        => 'First DOM Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        // Attempt to create a second DOM container for the same itemtype.
        // itemtypes must be passed as an array (prepareInputForAdd converts it internally).
        $container2 = new PluginFieldsContainer();
        $result = $container2->add([
            'label'        => 'Second DOM Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $this->assertFalse((bool) $result, 'Creating a second DOM container for the same itemtype must be rejected.');
    }

    // -----------------------------------------------------------------------
    // Tests: successive updates
    // -----------------------------------------------------------------------

    /**
     * Successive updates should overwrite previous plugin field values.
     * A native-only update must NOT erase previously set plugin field values.
     */
    public function testSuccessiveUpdatesOverwritePluginFieldValues(): void
    {
        $this->login();

        $container = $this->createFieldContainer([
            'label'        => 'Successive Updates Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'Successive Field',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);
        $field_name = $field->fields['name'];

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Ticket for successive updates',
            'content'     => 'Test',
            'entities_id' => 0,
        ]);

        // First update: set plugin field
        $this->updateTicket($ticket->getID(), [
            'id'        => $ticket->getID(),
            $field_name => 'first value',
        ], [$field_name]);

        $row = $this->getPluginFieldValues(Ticket::class, $ticket->getID(), $container->getID());
        $this->assertNotFalse($row, 'Plugin field row should exist after first update.');
        $this->assertSame('first value', $row[$field_name]);

        // Second update: overwrite plugin field value
        $this->updateTicket($ticket->getID(), [
            'id'        => $ticket->getID(),
            $field_name => 'second value',
        ], [$field_name]);

        $row = $this->getPluginFieldValues(Ticket::class, $ticket->getID(), $container->getID());
        $this->assertSame('second value', $row[$field_name]);

        // Third update: native field only — plugin value must remain unchanged
        $this->updateTicket($ticket->getID(), [
            'id'   => $ticket->getID(),
            'name' => 'Native-only update',
        ]);

        $row = $this->getPluginFieldValues(Ticket::class, $ticket->getID(), $container->getID());
        $this->assertSame(
            'second value',
            $row[$field_name],
            'Plugin value must not be erased by a native-only update.',
        );
    }
}
