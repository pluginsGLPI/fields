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
            'label'        => 'Mail Collector ' . $type . ($multiple ? ' Multi' : '') . ' Container',
            'type'         => 'dom',
            'itemtypes'    => [Ticket::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field_input = [
            'label'                                      => 'Mandatory ' . $type . ($multiple ? ' multiple' : ''),
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
}
