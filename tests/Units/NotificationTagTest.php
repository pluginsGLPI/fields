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

use Change;
use Computer;
use DBmysql;
use Dropdown;
use Entity;
use Glpi\ContentTemplates\ParametersPreset;
use Glpi\ContentTemplates\TemplateManager;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use ITILFollowup;
use ITILFollowupTemplate;
use NotificationEventMailing;
use NotificationTarget;
use PluginFieldsContainer;
use PluginFieldsDropdown;
use PluginFieldsField;
use PluginFieldsLabelTranslation;
use PluginFieldsProfile;
use Profile;
use Profile_User;
use Session;
use Ticket;
use User;

require_once __DIR__ . '/../FieldTestCase.php';

final class NotificationTagTest extends DbTestCase
{
    use FieldTestTrait;

    private PluginFieldsContainer $container;

    /** @var array<string, string> Field name by type */
    private array $names = [];

    public function setUp(): void
    {
        GLPITestCase::setUp();
        $this->login();
        $this->setEntity(0, true);

        $this->container = $this->createFieldContainer([
            'label'        => 'Notified',
            'type'         => 'tab',
            'itemtypes'    => [Change::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $fields = [
            'header'            => ['type' => 'header'],
            'text'              => ['type' => 'text'],
            'textarea'          => ['type' => 'textarea'],
            'richtext'          => ['type' => 'richtext'],
            'number'            => ['type' => 'number'],
            'url'               => ['type' => 'url'],
            'yesno'             => ['type' => 'yesno'],
            'date'              => ['type' => 'date'],
            'datetime'          => ['type' => 'datetime'],
            'dropdown'          => ['type' => 'dropdown'],
            'dropdown_multiple' => ['type' => 'dropdown', 'multiple' => 1, 'default_value' => []],
            'user'              => ['type' => 'dropdown-User'],
            'users'             => ['type' => 'dropdown-User', 'multiple' => 1, 'default_value' => []],
            'item'              => ['type' => 'glpi_item', 'allowed_values' => [Computer::class]],
            'inactive'          => ['type' => 'text', 'is_active' => 0],
        ];
        $ranking = 1;
        foreach ($fields as $key => $inputs) {
            $field = $this->createField($inputs + [
                'label'                                     => 'Label ' . $key,
                PluginFieldsContainer::getForeignKeyField() => $this->container->getID(),
                'ranking'                                   => $ranking++,
                'is_active'                                 => 1,
                'is_readonly'                               => 0,
            ], ['default_value']);
            $this->names[$key] = $field->fields['name'];
        }
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    public function testTagsAreListedForActiveFields(): void
    {
        $target = NotificationTarget::getInstanceByType(Change::class);
        $target->getTags();

        $values = array_keys($target->tag_descriptions[NotificationTarget::TAG_VALUE]);
        $labels = array_keys($target->tag_descriptions[NotificationTarget::TAG_LANGUAGE]);

        foreach (array_diff_key($this->names, ['header' => 0, 'inactive' => 0]) as $name) {
            $this->assertContains($this->tag($name), $values);
            $this->assertContains($this->langTag($name), $labels);
        }

        $this->assertNotContains($this->tag($this->names['header']), $values);
        $this->assertNotContains($this->tag($this->names['inactive']), $values);

        $tag = $target->tag_descriptions[NotificationTarget::TAG_VALUE][$this->tag($this->names['text'])];
        $this->assertSame('Notified - Label text', $tag['label']);
    }

    public function testValuesAreDisplayedAsInTheForm(): void
    {
        $dropdown_class = PluginFieldsDropdown::getClassname($this->names['dropdown']);
        $alpha = $this->createItem($dropdown_class, ['name' => 'Alpha', 'entities_id' => 0]);

        $multiple_class = PluginFieldsDropdown::getClassname($this->names['dropdown_multiple']);
        $one = $this->createItem($multiple_class, ['name' => 'One', 'entities_id' => 0]);
        $two = $this->createItem($multiple_class, ['name' => 'Two', 'entities_id' => 0]);

        $user_id   = getItemByTypeName(User::class, TU_USER, true);
        $tech_id   = getItemByTypeName(User::class, 'tech', true);
        $computer  = $this->createItem(Computer::class, [
            'name'        => 'Linked computer',
            'entities_id' => getItemByTypeName(Entity::class, '_test_root_entity', true),
        ]);
        $change    = $this->createChange();

        $this->assertTrue($this->container->updateFieldsValues([
            'plugin_fields_containers_id'                                 => $this->container->getID(),
            'itemtype'                                                    => Change::class,
            'items_id'                                                    => $change->getID(),
            $this->names['text']                                          => 'Some text',
            $this->names['textarea']                                      => "Line 1\nLine 2",
            $this->names['richtext']                                      => '<p>Rich <strong>text</strong></p>',
            $this->names['number']                                        => '42',
            $this->names['url']                                           => 'https://example.com/?a=1&b=2',
            $this->names['yesno']                                         => '1',
            $this->names['date']                                          => '2026-11-19',
            $this->names['datetime']                                      => '2026-11-19 10:30:00',
            'plugin_fields_' . $this->names['dropdown'] . 'dropdowns_id'  => $alpha->getID(),
            'plugin_fields_' . $this->names['dropdown_multiple'] . 'dropdowns_id' => [$one->getID(), $two->getID()],
            $this->names['user']                                          => $user_id,
            $this->names['users']                                         => [$user_id, $tech_id],
            'itemtype_' . $this->names['item']                            => Computer::class,
            'items_id_' . $this->names['item']                            => $computer->getID(),
        ], Change::class));

        $data = $this->getData($change);

        $this->assertSame('Some text', $data[$this->tag($this->names['text'])]);
        $this->assertSame("Line 1\nLine 2", $data[$this->tag($this->names['textarea'])]);
        $this->assertSame('<p>Rich <strong>text</strong></p>', $data[$this->tag($this->names['richtext'])]);
        $this->assertSame('42', $data[$this->tag($this->names['number'])]);
        $this->assertSame('https://example.com/?a=1&b=2', $data[$this->tag($this->names['url'])]);
        $this->assertSame('Yes', $data[$this->tag($this->names['yesno'])]);
        $this->assertSame('2026-11-19', $data[$this->tag($this->names['date'])]);
        $this->assertSame('2026-11-19 10:30', $data[$this->tag($this->names['datetime'])]);
        $this->assertSame('Alpha', $data[$this->tag($this->names['dropdown'])]);
        $this->assertSame('One, Two', $data[$this->tag($this->names['dropdown_multiple'])]);
        $this->assertSame(
            Dropdown::getDropdownName(User::getTable(), $user_id),
            $data[$this->tag($this->names['user'])],
        );
        $this->assertSame(
            Dropdown::getDropdownName(User::getTable(), $user_id) . ', ' . Dropdown::getDropdownName(User::getTable(), $tech_id),
            $data[$this->tag($this->names['users'])],
        );
        $this->assertSame('Linked computer', $data[$this->tag($this->names['item'])]);
        $this->assertSame('Label text', $data[$this->langTag($this->names['text'])]);
    }

    public function testTagsOfAnItemWithoutValuesAreEmpty(): void
    {
        $data = $this->getData($this->createChange());

        foreach (array_diff_key($this->names, ['header' => 0, 'inactive' => 0, 'yesno' => 0]) as $name) {
            $this->assertSame('', $data[$this->tag($name)], $name);
        }

        // a Yes/No field is "No" by default, as in the form
        $this->assertSame('No', $data[$this->tag($this->names['yesno'])]);
    }

    public function testDefaultValueIsUsedWhenNoValueIsStored(): void
    {
        $field = $this->createField([
            'label'                                     => 'Label default',
            'type'                                      => 'yesno',
            'default_value'                             => '1',
            PluginFieldsContainer::getForeignKeyField() => $this->container->getID(),
            'ranking'                                   => 99,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);

        $data = $this->getData($this->createChange());

        $this->assertSame('Yes', $data[$this->tag($field->fields['name'])]);
    }

    public function testLabelIsTranslatedInTheRecipientLanguage(): void
    {
        $field_id = $this->getFieldId($this->names['text']);
        $this->createItem(PluginFieldsLabelTranslation::class, [
            'itemtype' => PluginFieldsField::class,
            'items_id' => $field_id,
            'language' => 'fr_FR',
            'label'    => 'Libellé texte',
        ]);
        $change = $this->createChange();

        $this->assertSame('Label text', $this->getData($change)[$this->langTag($this->names['text'])]);

        $language = $_SESSION['glpilanguage'];
        $_SESSION['glpilanguage'] = 'fr_FR';
        try {
            $this->assertSame('Libellé texte', $this->getData($change)[$this->langTag($this->names['text'])]);
        } finally {
            $_SESSION['glpilanguage'] = $language;
        }
    }

    public function testContainerOfAnotherEntityGivesEmptyValues(): void
    {
        $child_entity = getItemByTypeName(Entity::class, '_test_child_1', true);
        $this->assertTrue($this->container->update([
            'id'           => $this->container->getID(),
            'entities_id'  => $child_entity,
            'is_recursive' => 0,
        ]));
        $change = $this->createChange();

        $this->assertTrue($this->container->updateFieldsValues([
            'plugin_fields_containers_id' => $this->container->getID(),
            'itemtype'                    => Change::class,
            'items_id'                    => $change->getID(),
            $this->names['text']          => 'Hidden',
        ], Change::class));

        $this->assertSame('', $this->getData($change)[$this->tag($this->names['text'])]);
    }

    public function testValuesAreOnlySentToRecipientsAllowedToReadTheContainer(): void
    {
        /** @var DBmysql $DB */
        global $DB;

        $change = $this->createChange();
        $this->assertTrue($this->container->updateFieldsValues([
            'plugin_fields_containers_id' => $this->container->getID(),
            'itemtype'                    => Change::class,
            'items_id'                    => $change->getID(),
            $this->names['text']          => 'Restricted',
        ], Change::class));

        $profiles_id = getItemByTypeName(Profile::class, 'Self-Service', true);
        $user = $this->createItem(User::class, ['name' => 'recipient' . $this->getUniqueString()]);
        $this->createItem(Profile_User::class, [
            'users_id'     => $user->getID(),
            'profiles_id'  => $profiles_id,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $set_right = fn(int $right) => $DB->update(PluginFieldsProfile::getTable(), ['right' => $right], [
            'profiles_id'                 => $profiles_id,
            'plugin_fields_containers_id' => $this->container->getID(),
        ]);

        $set_right(0);
        $data = $this->getData($change, $user->getID());
        $this->assertSame('', $data[$this->tag($this->names['text'])]);
        // the label is not confidential
        $this->assertSame('Label text', $data[$this->langTag($this->names['text'])]);

        $set_right(READ);
        $this->assertSame('Restricted', $this->getData($change, $user->getID())[$this->tag($this->names['text'])]);

        // email address without GLPI account
        $this->assertSame('', $this->getData($change, 0)[$this->tag($this->names['text'])]);
    }

    public function testRecipientIsPassedToTheTemplateData(): void
    {
        $users_id = getItemByTypeName(User::class, TU_USER, true);

        $target = NotificationTarget::getInstance($this->createChange(), 'update');
        $target->setEvent(NotificationEventMailing::class);
        $target->addToRecipientsList([
            'users_id' => $users_id,
            'email'    => 'recipient@example.com',
            'language' => 'en_GB',
        ]);

        $recipients = array_values($target->target);
        $this->assertCount(1, $recipients);
        $this->assertSame($users_id, $recipients[0]['additionnaloption']['users_id']);
    }

    public function testTagsOfAnEventWithoutItemAreEmpty(): void
    {
        $target = NotificationTarget::getInstanceByType(Change::class, 'update');
        $data = $target->getForTemplate('update', [
            'additionnaloption' => ['usertype' => NotificationTarget::GLPI_USER],
        ]);

        $this->assertSame('', $data[$this->tag($this->names['text'])]);
        $this->assertSame('Label text', $data[$this->langTag($this->names['text'])]);
    }

    public function testFollowupTemplateRendersFieldsVariables(): void
    {
        $dropdown_class = PluginFieldsDropdown::getClassname($this->names['dropdown']);
        $alpha = $this->createItem($dropdown_class, ['name' => 'Alpha', 'entities_id' => 0]);

        $change = $this->createChange();
        $this->assertTrue($this->container->updateFieldsValues([
            'plugin_fields_containers_id'                                => $this->container->getID(),
            'itemtype'                                                   => Change::class,
            'items_id'                                                   => $change->getID(),
            $this->names['text']                                         => 'Some <text>',
            $this->names['richtext']                                     => '<p>Rich <strong>text</strong></p>',
            'plugin_fields_' . $this->names['dropdown'] . 'dropdowns_id' => $alpha->getID(),
        ], Change::class));

        $variable = fn(string $key, string $filter = '') => sprintf(
            '{{ change.fields.%s.%s%s }}',
            $this->container->fields['name'],
            $this->names[$key],
            $filter,
        );
        $template = $this->createItem(ITILFollowupTemplate::class, [
            'name'    => 'Template ' . $this->getUniqueString(),
            'content' => '<p>' . $variable('text') . ' / ' . $variable('dropdown') . '</p>' . $variable('richtext', ' | raw'),
        ]);

        $this->assertSame(
            '<p>Some &lt;text&gt; / Alpha</p><p>Rich <strong>text</strong></p>',
            $template->getRenderedContent($change),
        );

        // the current profile cannot read the container: empty values, as in the item form
        /** @var DBmysql $DB */
        global $DB;
        $DB->update(PluginFieldsProfile::getTable(), ['right' => 0], [
            'profiles_id'                 => $_SESSION['glpiactiveprofile']['id'],
            'plugin_fields_containers_id' => $this->container->getID(),
        ]);
        $this->assertSame('<p> / </p>', $template->getRenderedContent($change));

        // without right checks, as when a form creates the ticket: all the values
        $this->assertSame(
            '<p>Some &lt;text&gt; / Alpha</p><p>Rich <strong>text</strong></p>',
            Session::callAsSystem(fn() => $template->getRenderedContent($change)),
        );
    }

    public function testTemplateRenderedForARequesterOnlyShowsReadableContainers(): void
    {
        $profiles_id = getItemByTypeName(Profile::class, 'Self-Service', true);
        /** @var DBmysql $DB */
        global $DB;
        $DB->update(PluginFieldsProfile::getTable(), ['right' => 0], [
            'profiles_id'                 => $profiles_id,
            'plugin_fields_containers_id' => $this->container->getID(),
        ]);

        $change = $this->createChange();
        $this->assertTrue($this->container->updateFieldsValues([
            'plugin_fields_containers_id' => $this->container->getID(),
            'itemtype'                    => Change::class,
            'items_id'                    => $change->getID(),
            $this->names['text']          => 'Technical data',
        ], Change::class));

        $template = $this->createItem(ITILFollowupTemplate::class, [
            'name'    => 'Template ' . $this->getUniqueString(),
            'content' => sprintf('<p>{{ change.fields.%s.%s }}</p>', $this->container->fields['name'], $this->names['text']),
        ]);

        // as rendered by ajax/itilfollowup.php for a requester, who can read the change but not the container
        $this->login('post-only', 'postonly');
        $this->assertSame('Self-Service', $_SESSION['glpiactiveprofile']['name']);
        $this->assertSame('<p></p>', $template->getRenderedContent($change));
    }

    public function testFollowupTemplateAppliedOnTicketCreationWithoutRightChecks(): void
    {
        $computer_container = $this->createFieldContainer([
            'label'        => 'Admin',
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
        $ip_field = $this->createField([
            'label'                                     => 'IP Admin',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $computer_container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);

        $entities_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $computer    = $this->createItem(Computer::class, ['name' => 'Server', 'entities_id' => $entities_id]);
        $this->assertTrue($computer_container->updateFieldsValues([
            'plugin_fields_containers_id' => $computer_container->getID(),
            'itemtype'                    => Computer::class,
            'items_id'                    => $computer->getID(),
            $ip_field->fields['name']     => '192.0.2.10',
        ], Computer::class));

        $template = $this->createItem(ITILFollowupTemplate::class, [
            'name'       => 'Template ' . $this->getUniqueString(),
            'is_private' => 1,
            'content'    => sprintf(
                '{%% for asset in ticket.assets %%}<p>{{ asset.fields.%s.%s }}</p>{%% endfor %%}',
                $computer_container->fields['name'],
                $ip_field->fields['name'],
            ),
        ]);

        // the current profile (a requester) cannot read the container
        $profiles_id = getItemByTypeName(Profile::class, 'Self-Service', true);
        /** @var DBmysql $DB */
        global $DB;
        $DB->update(PluginFieldsProfile::getTable(), ['right' => 0], [
            'profiles_id'                 => $profiles_id,
            'plugin_fields_containers_id' => $computer_container->getID(),
        ]);
        $this->login('post-only', 'postonly');
        $this->setEntity($entities_id, true);

        // as Glpi\Form\Destination\AbstractCommonITILFormDestination::createDestinationItems() does
        $tickets_id = Session::callAsSystem(fn() => (new Ticket())->add([
            'name'                      => 'Ticket ' . $this->getUniqueString(),
            'content'                   => 'Content',
            'entities_id'               => $entities_id,
            'items_id'                  => [Computer::class => [$computer->getID()]],
            '_itilfollowuptemplates_id' => [$template->getID()],
        ]));
        $this->assertGreaterThan(0, $tickets_id);

        $followup = new ITILFollowup();
        $this->assertTrue($followup->getFromDBByCrit(['itemtype' => Ticket::class, 'items_id' => $tickets_id]));
        $this->assertSame('<p>192.0.2.10</p>', $followup->fields['content']);
    }

    public function testFollowupTemplateRendersFieldsOfLinkedAssets(): void
    {
        $computer_container = $this->createFieldContainer([
            'label'        => 'Admin',
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
        $ip_field = $this->createField([
            'label'                                     => 'IP Admin',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $computer_container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);

        $entities_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $with_ip     = $this->createItem(Computer::class, ['name' => 'Server 1', 'entities_id' => $entities_id]);
        $without_ip  = $this->createItem(Computer::class, ['name' => 'Server 2', 'entities_id' => $entities_id]);
        $this->assertTrue($computer_container->updateFieldsValues([
            'plugin_fields_containers_id' => $computer_container->getID(),
            'itemtype'                    => Computer::class,
            'items_id'                    => $with_ip->getID(),
            $ip_field->fields['name']     => '192.0.2.10',
        ], Computer::class));

        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Ticket ' . $this->getUniqueString(),
            'content'     => 'Content',
            'entities_id' => $entities_id,
            'items_id'    => [Computer::class => [$with_ip->getID(), $without_ip->getID()]],
        ], ['items_id']);

        $template = $this->createItem(ITILFollowupTemplate::class, [
            'name'    => 'Template ' . $this->getUniqueString(),
            'content' => sprintf(
                '{%% for asset in ticket.assets %%}<p>{{ asset.name }}: {{ asset.fields.%s.%s }}</p>{%% endfor %%}',
                $computer_container->fields['name'],
                $ip_field->fields['name'],
            ),
        ]);

        $this->assertSame(
            '<p>Server 1: 192.0.2.10</p><p>Server 2: </p>',
            $template->getRenderedContent($ticket),
        );
    }

    public function testTemplateVariablesAreDocumented(): void
    {
        $documentation = TemplateManager::generateMarkdownDocumentation(ParametersPreset::ITIL_CHILD_TEMPLATE);

        $this->assertStringContainsString(
            // usage as written by GLPI, with an empty filter
            sprintf('{{ change.fields.%s.%s  }}', $this->container->fields['name'], $this->names['text']),
            $documentation,
        );
        $this->assertStringContainsString('Notified - Label text', $documentation);
        $this->assertStringContainsString(
            sprintf('{{ change.fields.%s.%s \\| nl2br }}', $this->container->fields['name'], $this->names['textarea']),
            $documentation,
        );
        $this->assertStringContainsString(
            // pipe escaped in the markdown table
            sprintf('{{ change.fields.%s.%s \\| raw }}', $this->container->fields['name'], $this->names['richtext']),
            $documentation,
        );
        // only for the itemtypes of the containers
        $this->assertStringNotContainsString('ticket.fields.', $documentation);
        // asset types without fields container: no asset variables
        $this->assertStringNotContainsString('asset.fields.', $documentation);
    }

    private function createChange(): Change
    {
        return $this->createItem(Change::class, [
            'name'        => 'Change ' . $this->getUniqueString(),
            'content'     => 'Content',
            'entities_id' => 0,
        ]);
    }

    /**
     * Template data for a recipient, the super-admin test user by default, 0 for an email address
     *
     * @return array<string, mixed>
     */
    private function getData(Change $change, ?int $users_id = null): array
    {
        $users_id ??= getItemByTypeName(User::class, TU_USER, true);

        $target = NotificationTarget::getInstance($change, 'update');
        $additionnaloption = ['usertype' => NotificationTarget::GLPI_USER];
        if ($users_id > 0) {
            // set by PluginFieldsNotificationTag::addRecipient() when the recipient is added
            $additionnaloption['users_id'] = $users_id;
        }

        return $target->getForTemplate('update', ['additionnaloption' => $additionnaloption]);
    }

    private function getFieldId(string $name): int
    {
        $field = new PluginFieldsField();
        $this->assertTrue($field->getFromDBByCrit(['name' => $name]));

        return $field->getID();
    }

    private function tag(string $name): string
    {
        return sprintf('##fields.%s.%s##', $this->container->fields['name'], $name);
    }

    private function langTag(string $name): string
    {
        return sprintf('##lang.fields.%s.%s##', $this->container->fields['name'], $name);
    }
}
