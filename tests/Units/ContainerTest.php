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
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use Laminas\Mail\Storage\Message;
use MailCollector;
use PHPUnit\Framework\Attributes\DataProvider;
use PluginFieldsContainer;
use PluginFieldsDropdown;
use PluginFieldsField;
use Search;
use Session;
use Ticket;
use UserEmail;

require_once __DIR__ . '/../FieldTestCase.php';

final class ContainerTest extends DbTestCase
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

    public static function provideInvalidItemtypes(): iterable
    {
        yield 'missing itemtypes' => [
            'input' => [
                'name'  => 'test_container',
                'label' => 'Test Container',
                'type'  => 'tab',
            ],
        ];

        yield 'empty itemtypes array' => [
            'input' => [
                'name'      => 'test_container',
                'label'     => 'Test Container',
                'type'      => 'tab',
                'itemtypes' => [],
            ],
        ];

        yield 'empty itemtypes string' => [
            'input' => [
                'name'      => 'test_container',
                'label'     => 'Test Container',
                'type'      => 'tab',
                'itemtypes' => '',
            ],
        ];
    }

    #[DataProvider('provideInvalidItemtypes')]
    public function testAddWithoutItemtypesIsRejected(array $input): void
    {
        $container = new PluginFieldsContainer();
        $result = $container->add($input);

        $this->assertFalse($result);
    }

    public function testAddWithValidItemtypesSucceeds(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'ValidItemtypes ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $this->assertGreaterThan(0, $container->getID());
    }

    public function testAddDomtabWithIncompatibleItemtypeIsRejected(): void
    {
        $container = new PluginFieldsContainer();
        $result = $container->add([
            'label'        => 'Domtab with invalid item type',
            'type'         => 'domtab',
            'subtype'      => '',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
        $this->assertFalse($result);
    }

    public static function provideMandatoryFieldTypes(): iterable
    {
        yield 'text'     => ['type' => 'text',     'default_value' => 'Text default',      'expected_value' => 'Text default'];
        yield 'textarea' => ['type' => 'textarea', 'default_value' => 'Textarea default',  'expected_value' => 'Textarea default'];
        yield 'url'      => ['type' => 'url',      'default_value' => 'https://example.org', 'expected_value' => 'https://example.org'];
        yield 'number'   => ['type' => 'number',   'default_value' => '42',                'expected_value' => '42'];
        yield 'date'     => ['type' => 'date',     'default_value' => '2024-01-01',        'expected_value' => '2024-01-01'];
        yield 'datetime' => ['type' => 'datetime', 'default_value' => '2024-01-01 10:00:00', 'expected_value' => '2024-01-01 10:00:00'];
        yield 'dropdown'          => ['type' => 'dropdown', 'default_value' => null, 'expected_value' => null, 'multiple' => false];
        yield 'dropdown multiple' => ['type' => 'dropdown', 'default_value' => null, 'expected_value' => null, 'multiple' => true];
        yield 'dropdown itemtype computer'          => ['type' => 'dropdown-Computer', 'default_value' => null, 'expected_value' => null, 'multiple' => false];
        yield 'dropdown itemtype computer multiple' => ['type' => 'dropdown-Computer', 'default_value' => null, 'expected_value' => null, 'multiple' => true];
    }

    #[DataProvider('provideMandatoryFieldTypes')]
    public function testMailCollectorImportRespectsMandatoryFieldDefaultValue(
        string $type,
        array|string|null $default_value,
        array|int|string|null $expected_value,
        bool $multiple = false,
    ): void {
        $this->login();

        $container = $this->createFieldContainer([
            'label'        => 'Mail Collector Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field_input = [
            'label'                                      => 'Mandatory Field',
            'type'                                        => $type,
            'multiple'                                    => $multiple ? 1 : 0,
            PluginFieldsContainer::getForeignKeyField()  => $container->getID(),
            'ranking'                                    => 1,
            'is_active'                                  => 1,
            'is_readonly'                                => 0,
            'mandatory'                                  => 1,
        ];

        if ($multiple) {
            $field_input['default_value'] = [];
        }

        $field = $this->createField($field_input, $multiple ? ['default_value'] : []);

        $field_name = $field->fields['name'];
        $row_key    = $type === 'dropdown' ? 'plugin_fields_' . $field_name . 'dropdowns_id' : $field_name;

        if ($type === 'dropdown') {
            $dropdown_classname = PluginFieldsDropdown::getClassname($field_name);

            if ($multiple) {
                $option_ids = [
                    $this->createItem($dropdown_classname, ['name' => 'Default option 1'])->getID(),
                    $this->createItem($dropdown_classname, ['name' => 'Default option 2'])->getID(),
                ];

                $default_value  = $option_ids;
                $expected_value = $option_ids;
            } else {
                $option_id = $this->createItem($dropdown_classname, ['name' => 'Default option'])->getID();
                $default_value  = (string) $option_id;
                $expected_value = $option_id;
            }
        } elseif ($type === 'dropdown-Computer') {
            if ($multiple) {
                $option_ids = [
                    $this->createItem(Computer::class, ['name' => 'Default option 1', 'entities_id' => 0])->getID(),
                    $this->createItem(Computer::class, ['name' => 'Default option 2', 'entities_id' => 0])->getID(),
                ];

                $default_value  = $option_ids;
                $expected_value = $option_ids;
            } else {
                $option_id = $this->createItem(Computer::class, ['name' => 'Default option', 'entities_id' => 0])->getID();
                $default_value  = $option_id;
                $expected_value = $option_id;
            }
        }

        $collector = $this->createItem(
            MailCollector::class,
            [
                'name'            => 'test-collector-' . $this->getUniqueString(),
                'is_active'       => 1,
                'requester_field' => MailCollector::REQUESTER_FIELD_FROM,
                'mail_server'     => 'imap.test.glpi.com',
                'server_type'     => '/imap',
            ],
            ['mail_server', 'server_type'],
        );

        $sender_email = 'mailcollector-test-' . $this->getUniqueString() . '@test.glpi.com';
        $this->createItem(UserEmail::class, [
            'users_id'   => Session::getLoginUserID(),
            'is_default' => 1,
            'email'      => $sender_email,
        ]);

        $message = new Message([
            'headers' => [
                'From'       => sprintf('Test requester <%s>', $sender_email),
                'To'         => 'helpdesk@glpi.com',
                'Subject'    => 'Ticket',
                'Message-Id' => '<' . uniqid('mailcollector-test-', true) . '@glpi-test.com>',
                'Date'       => 'Mon, 01 Jan 2024 12:00:00 +0000',
            ],
            'content' => 'This is a test email imported via the mail collector.',
        ]);

        // No default value on the mandatory field
        $tkt = $collector->buildTicket(1, $message, ['mailgates_id' => $collector->getID(), 'play_rules' => false]);
        $tkt['entities_id'] = 0;

        $ticket = new Ticket();
        $ticket_id = $ticket->add($tkt);
        $this->assertFalse($ticket_id, sprintf('Import must be blocked when the mandatory %s field has no value and no default.', $type));
        $this->hasSessionMessageThatContains(
            __('Some mandatory fields are empty', 'fields'),
            (string) ERROR,
        );

        $this->updateItem(
            PluginFieldsField::class,
            $field->getID(),
            ['default_value' => $default_value],
            $multiple ? ['default_value'] : [],
        );

        $tkt = $collector->buildTicket(2, $message, ['mailgates_id' => $collector->getID(), 'play_rules' => false]);
        $tkt['entities_id'] = 0;

        $ticket = new Ticket();
        $ticket_id = $ticket->add($tkt);
        $this->assertGreaterThan(0, $ticket_id, sprintf('Import must succeed once the mandatory %s field has a default value.', $type));

        $classname = PluginFieldsContainer::getClassname(Ticket::class, $container->fields['name']);
        $obj = getItemForItemtype($classname);
        $obj->getFromDBByCrit([
            'plugin_fields_containers_id' => $container->getID(),
            'items_id'                    => $ticket_id,
        ]);
        $container_ticket_fields_value = $obj->fields;
        $stored_value = $multiple ? json_decode((string) $container_ticket_fields_value[$row_key], true)
                                    : $container_ticket_fields_value[$row_key];
        $this->assertEquals($expected_value, $stored_value);
    }

    private function getDefaultValueStored(Ticket $ticket, PluginFieldsContainer $container, string $field_name, bool $multiple = false): mixed
    {
        $classname = PluginFieldsContainer::getClassname(Ticket::class, $container->fields['name']);
        $obj = getItemForItemtype($classname);
        $obj->getFromDBByCrit([
            'plugin_fields_containers_id' => $container->getID(),
            'items_id'                    => $ticket->getID(),
        ]);

        $stored = $obj->fields[$field_name];

        return $multiple ? json_decode((string) $stored, true) : $stored;
    }

    public static function provideFieldTypesForDefaultValueBackfill(): iterable
    {
        yield 'text'     => ['type' => 'text',     'created_default' => 'created default text',       'updated_default' => 'updated default text'];
        yield 'textarea' => ['type' => 'textarea', 'created_default' => 'created default textarea',   'updated_default' => 'updated default textarea'];
        yield 'richtext' => ['type' => 'richtext', 'created_default' => 'created default richtext',   'updated_default' => 'updated default richtext'];
        yield 'url'      => ['type' => 'url',      'created_default' => 'https://example.org/created', 'updated_default' => 'https://example.org/updated'];
        yield 'number'   => ['type' => 'number',   'created_default' => '42',                          'updated_default' => '99'];
        yield 'yesno'    => ['type' => 'yesno',    'created_default' => '1',                           'updated_default' => '0'];
        yield 'date'     => ['type' => 'date',     'created_default' => '2024-01-01',                  'updated_default' => '2024-02-02'];
        yield 'datetime' => ['type' => 'datetime', 'created_default' => '2024-01-01 10:00:00',         'updated_default' => '2024-02-02 11:00:00'];
        yield 'dropdown itemtype computer'          => ['type' => 'dropdown-Computer', 'created_default' => null, 'updated_default' => null, 'multiple' => false];
        yield 'dropdown itemtype computer multiple' => ['type' => 'dropdown-Computer', 'created_default' => null, 'updated_default' => null, 'multiple' => true];
    }

    #[DataProvider('provideFieldTypesForDefaultValueBackfill')]
    public function testDefaultValueIsAppliedToExistingItemsOnlyAtFieldCreation(
        string $type,
        mixed $created_default,
        mixed $updated_default,
        bool $multiple = false,
    ): void {
        $this->login();

        $container = $this->createFieldContainer([
            'label'        => 'Backfill Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        // A first field so tickets already get a row in the container table.
        $existing_field = $this->createField([
            'label'                                     => 'Existing Field',
            'type'                                       => 'text',
            PluginFieldsContainer::getForeignKeyField()  => $container->getID(),
            'ranking'                                    => 1,
            'is_active'                                  => 1,
            'is_readonly'                                => 0,
        ]);
        $existing_field_name = $existing_field->fields['name'];

        // Tickets already existing before the new field is created.
        $ticket1 = $this->createItem(Ticket::class, [
            'name'              => 'Ticket 1 ' . $this->getUniqueString(),
            'content'           => 'Test',
            'entities_id'       => 0,
            $existing_field_name => 'value 1',
        ], [$existing_field_name]);

        $ticket2 = $this->createItem(Ticket::class, [
            'name'              => 'Ticket 2 ' . $this->getUniqueString(),
            'content'           => 'Test',
            'entities_id'       => 0,
            $existing_field_name => 'value 2',
        ], [$existing_field_name]);

        if ($type === 'dropdown-Computer') {
            [$computer1, $computer2] = $this->createItems(Computer::class, [
                ['name' => 'Computer 1', 'entities_id' => 0],
                ['name' => 'Computer 2', 'entities_id' => 0],
            ]);

            $created_default = $multiple ? [$computer1->getID()] : $computer1->getID();
            $updated_default = $multiple ? [$computer2->getID()] : $computer2->getID();
        }

        // Create a new field with a default value on the same container.
        $new_field = $this->createField(
            [
                'label'                                     => 'New Field',
                'type'                                       => $type,
                'multiple'                                   => $multiple ? 1 : 0,
                PluginFieldsContainer::getForeignKeyField()  => $container->getID(),
                'ranking'                                    => 2,
                'is_active'                                  => 1,
                'is_readonly'                                => 0,
                'default_value'                              => $created_default,
            ],
            $multiple ? ['default_value'] : [],
        );
        $new_field_name = $new_field->fields['name'];

        $readValue = function (Ticket $ticket) use ($container, $new_field_name, $multiple): mixed {
            $stored = $this->getDefaultValueStored($ticket, $container, $new_field_name);

            return $multiple ? json_decode((string) $stored, true) : $stored;
        };

        // Assert: the default value was applied to all objects that already existed.
        $this->assertEquals($created_default, $readValue($ticket1));
        $this->assertEquals($created_default, $readValue($ticket2));

        // Change the default value afterwards, through an update, not a creation.
        $this->updateItem(
            PluginFieldsField::class,
            $new_field->getID(),
            ['default_value' => $updated_default],
            $multiple ? ['default_value'] : [],
        );

        // The update must not retroactively change existing objects values.
        $this->assertEquals($created_default, $readValue($ticket1));
        $this->assertEquals($created_default, $readValue($ticket2));

        // Sanity check: a ticket created after the update still gets the new default,
        // proving the update did take effect, just not retroactively.
        $ticket3 = $this->createItem(Ticket::class, [
            'name'        => 'Ticket 3 ' . $this->getUniqueString(),
            'content'     => 'Test',
            'entities_id' => 0,
        ]);
        $this->assertEquals($updated_default, $readValue($ticket3));
    }

    public static function provideFieldTypesForSearchDefaultValue(): iterable
    {
        yield 'text'     => ['type' => 'text',     'default_value' => 'search default text'];
        yield 'textarea' => ['type' => 'textarea', 'default_value' => 'search default textarea'];
        yield 'richtext' => ['type' => 'richtext', 'default_value' => 'search default richtext'];
        yield 'url'      => ['type' => 'url',      'default_value' => 'https://example.org/search-default'];
        yield 'number'   => ['type' => 'number',   'default_value' => '42'];
        yield 'yesno'    => ['type' => 'yesno',    'default_value' => '1'];
        yield 'date'     => ['type' => 'date',     'default_value' => '2024-01-01'];
        yield 'date now' => ['type' => 'date',     'default_value' => 'now'];
        yield 'datetime' => ['type' => 'datetime', 'default_value' => '2024-01-01 10:00:00'];
        yield 'dropdown'                            => ['type' => 'dropdown',          'multiple' => false];
        yield 'dropdown multiple'                   => ['type' => 'dropdown',          'multiple' => true];
        yield 'dropdown itemtype computer'          => ['type' => 'dropdown-Computer', 'multiple' => false];
        yield 'dropdown itemtype computer multiple' => ['type' => 'dropdown-Computer', 'multiple' => true];
    }

    #[DataProvider('provideFieldTypesForSearchDefaultValue')]
    public function testSearchoptionsShowsDefaultValueFieldWithoutAnyRow(
        string $type,
        ?string $default_value = null,
        bool $multiple = false,
    ): void {
        $this->login();
        $entities_id = $_SESSION['glpiactive_entity'];

        $container = $this->createFieldContainer([
            'label'        => 'Search Default Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => $entities_id,
            'is_recursive' => 1,
        ]);

        // Ticket created before the field exists
        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Search default ticket ' . $this->getUniqueString(),
            'content'     => 'Test',
            'entities_id' => $entities_id,
        ]);

        $expected_displayname = null;

        if ($type === 'dropdown-Computer') {
            [$option1, $option2] = $this->createItems(Computer::class, [
                ['name' => 'Search default option 1 ' . $this->getUniqueString(), 'entities_id' => $entities_id],
                ['name' => 'Search default option 2 ' . $this->getUniqueString(), 'entities_id' => $entities_id],
            ]);

            $default_value = $multiple ? [$option1->getID(), $option2->getID()] : (string) $option1->getID();
            $expected_displayname = $multiple
                ? implode('<br />', [$option1->fields['name'], $option2->fields['name']])
                : $option1->fields['name'];
        }

        $field_input = [
            'label'                                      => 'Search Default Field',
            'type'                                       => $type,
            'multiple'                                   => $multiple ? 1 : 0,
            PluginFieldsContainer::getForeignKeyField()  => $container->getID(),
            'ranking'                                    => 1,
            'is_active'                                  => 1,
            'is_readonly'                                => 0,
        ];

        // The default value for dropdown type fields can only be set after the field is created,
        // because it requires the creation of dropdown items first.
        if ($type !== 'dropdown') {
            $field_input['default_value'] = $default_value;
        } elseif ($multiple) {
            $field_input['default_value'] = [];
        }

        $field = $this->createField($field_input, $multiple ? ['default_value'] : []);

        if ($type === 'dropdown') {
            $dropdown_classname = PluginFieldsDropdown::getClassname($field->fields['name']);
            [$option1, $option2] = $this->createItems($dropdown_classname, [
                ['name' => 'Search default option 1 ' . $this->getUniqueString()],
                ['name' => 'Search default option 2 ' . $this->getUniqueString()],
            ]);

            $default_value = $multiple ? [$option1->getID(), $option2->getID()] : (string) $option1->getID();
            $expected_displayname = $multiple
                ? implode('<br />', [$option1->fields['name'], $option2->fields['name']])
                : $option1->fields['name'];

            $this->updateItem(
                PluginFieldsField::class,
                $field->getID(),
                ['default_value' => $default_value],
                $multiple ? ['default_value'] : [],
            );
        }

        $searchopt = Search::getOptions(Ticket::class);
        $so_id = PluginFieldsField::SEARCH_OPTION_STARTING_INDEX + $field->getID();
        $this->assertArrayHasKey($so_id, $searchopt);

        $data = Search::getDatas(
            Ticket::class,
            [
                'is_deleted' => 0,
                'start'      => 0,
                'criteria'   => [
                    ['field' => 'view', 'searchtype' => 'contains', 'value' => $ticket->fields['name']],
                ],
            ],
            [$so_id],
        );

        $this->assertSame(1, $data['data']['totalcount']);
        $row = current($data['data']['rows']);

        if ($type === 'dropdown-Computer' || $type === 'dropdown') {
            $this->assertTrue(isset($row['Ticket_' . $so_id]['displayname']));
            $this->assertSame($expected_displayname, $row['Ticket_' . $so_id]['displayname']);
        } elseif ($default_value === 'now') {
            // 'now' is resolved to the current server time at query time,
            // not stored as the literal string 'now'.
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
                (string) $row['raw']['ITEM_Ticket_' . $so_id],
            );
        } else {
            $this->assertSame($default_value, $row['raw']['ITEM_Ticket_' . $so_id]);
        }
    }
}
