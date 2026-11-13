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

use Override;
use Computer;
use Entity;
use Glpi\Search\SearchOption;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use MassiveAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PluginFieldsContainer;
use PluginFieldsField;
use PluginFieldsProfile;
use ReflectionClass;
use Ticket;

require_once __DIR__ . '/../FieldTestCase.php';

/**
 * Massive actions on fields of an item having several tab containers.
 *
 * The core displays its own input (without the plugin `c_id` hidden input) for GLPI item
 * dropdown fields, and the `c_id` input is also lost when a long massive action is reloaded,
 * so the plugin has to find the targeted container from the submitted fields.
 */
final class MassiveActionMultipleTabContainersTest extends DbTestCase
{
    use FieldTestTrait;

    /** @var array<string, PluginFieldsContainer> */
    private array $containers = [];

    /** @var array<string, PluginFieldsField> */
    private array $fields = [];

    #[Override]
    public function setUp(): void
    {
        GLPITestCase::setUp();
        $this->login();
    }

    #[Override]
    public function tearDown(): void
    {
        unset($_REQUEST['massiveaction'], $_REQUEST['c_id']);
        $_POST = [];
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    /**
     * Same configuration as the one reported: two tab containers and a "dom" one on computers.
     */
    private function createContainers(): void
    {
        $common = [
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ];
        $this->containers = [
            'first_tab' => $this->createFieldContainer(['label' => 'Reconditionnement', 'type' => 'tab'] + $common),
            'last_tab'  => $this->createFieldContainer(['label' => 'Achat et vente', 'type' => 'tab'] + $common),
            'dom'       => $this->createFieldContainer(['label' => 'Infos Supps', 'type' => 'dom'] + $common),
        ];

        $definitions = [
            'first_tab_ticket' => ['first_tab', 'Ticket Lot', 'dropdown-Ticket'],
            'first_tab_entity' => ['first_tab', 'Entite origine', 'dropdown-Entity'],
            'first_tab_text'   => ['first_tab', 'Destination', 'text'],
            'last_tab_ticket'  => ['last_tab', 'Ticket Vente', 'dropdown-Ticket'],
            'dom_ticket'       => ['dom', 'Ticket Lot', 'dropdown-Ticket'],
        ];
        $ranking = 0;
        foreach ($definitions as $key => [$container_key, $label, $type]) {
            $this->fields[$key] = $this->createField([
                'label'                                     => $label,
                'type'                                      => $type,
                PluginFieldsContainer::getForeignKeyField() => $this->containers[$container_key]->getID(),
                'ranking'                                   => ++$ranking,
                'is_active'                                 => 1,
                'is_readonly'                               => 0,
            ]);
        }
    }

    private function buildMassiveAction(array $post, string $itemtype, int $id): MassiveAction
    {
        $ref = new ReflectionClass(MassiveAction::class);
        $ma = $ref->newInstanceWithoutConstructor();
        $ma->POST = $post;

        $set = static function (string $name, $value) use ($ref, $ma): void {
            $ref->getProperty($name)->setValue($ma, $value);
        };

        $set('action', 'update');
        $set('done', []);
        $set('nb_done', 0);
        $set('current_itemtype', null);
        $set('remainings', [$itemtype => [$id => $id]]);
        $set('results', ['ok' => 0, 'noaction' => 0, 'ko' => 0, 'noright' => 0, 'messages' => []]);
        $set('start_time', microtime(true));

        return $ma;
    }

    /**
     * Run the core "update" massive action on a single computer.
     *
     * @return array{ok: int, noaction: int, ko: int, noright: int, messages: array}
     */
    private function runMassiveUpdate(Computer $computer, string $field_name, mixed $value, ?int $c_id = null): array
    {
        $so_index = null;
        foreach (SearchOption::getOptionsForItemtype(Computer::class) as $idx => $opt) {
            if (is_array($opt) && ($opt['linkfield'] ?? null) === $field_name) {
                $so_index = $idx;
            }
        }

        $this->assertNotNull($so_index, sprintf('Search option not found for field "%s"', $field_name));

        $_REQUEST['massiveaction'] = 1;
        $_POST = [
            'common_options' => [Computer::class . ':' . $so_index => Computer::class . ':' . $so_index],
            'search_options' => [Computer::class => $so_index],
            'id_field'       => Computer::class . ':' . $so_index,
            'field'          => $field_name,
            $field_name      => $value,
        ];
        if ($c_id !== null) {
            $_REQUEST['c_id'] = $c_id;
            $_POST['c_id']    = $c_id;
        }

        $ma = $this->buildMassiveAction($_POST, Computer::class, $computer->getID());
        MassiveAction::processMassiveActionsForOneItemtype($ma, $computer, [$computer->getID()]);

        return (new ReflectionClass(MassiveAction::class))->getProperty('results')->getValue($ma);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getStoredValues(string $container_key, Computer $computer): ?array
    {
        $classname = PluginFieldsContainer::getClassname(Computer::class, $this->containers[$container_key]->fields['name']);
        $values    = new $classname();

        return $values->getFromDBByCrit(['items_id' => $computer->getID()]) ? $values->fields : null;
    }

    private function setRightOnContainer(string $container_key, int $right): void
    {
        $profile_right = new PluginFieldsProfile();
        $this->assertTrue($profile_right->getFromDBByCrit([
            'profiles_id'                 => $_SESSION['glpiactiveprofile']['id'],
            'plugin_fields_containers_id' => $this->containers[$container_key]->getID(),
        ]));
        $this->updateItem(PluginFieldsProfile::class, $profile_right->getID(), ['right' => $right]);
    }

    public static function fieldProvider(): iterable
    {
        // GLPI item dropdowns: the core displays its own input, without the plugin `c_id`
        yield 'ticket field in the first tab container' => ['first_tab_ticket', 'first_tab'];
        yield 'entity field in the first tab container' => ['first_tab_entity', 'first_tab'];
        yield 'ticket field in the last tab container'  => ['last_tab_ticket', 'last_tab'];
        yield 'ticket field in the dom container'       => ['dom_ticket', 'dom'];
        // plugin input, `c_id` lost when the massive action is reloaded to refresh the progress bar
        yield 'text field in the first tab container'   => ['first_tab_text', 'first_tab'];
    }

    #[DataProvider('fieldProvider')]
    public function testMassiveUpdateWithoutContainerId(string $field_key, string $container_key): void
    {
        $this->createContainers();

        $entities_id = $this->getTestRootEntity(true);
        $computer    = $this->createItem(Computer::class, ['name' => 'PC', 'entities_id' => $entities_id]);
        $field_name  = $this->fields[$field_key]->fields['name'];
        $value       = match ($this->fields[$field_key]->fields['type']) {
            'dropdown-Ticket' => $this->createItem(Ticket::class, ['name' => 'Lot', 'content' => 'Lot', 'entities_id' => $entities_id])->getID(),
            // an entity visible from the computer one, to pass the core entity compatibility check
            'dropdown-Entity' => getItemByTypeName(Entity::class, '_test_child_1', true),
            default           => 'Atelier',
        };

        $results = $this->runMassiveUpdate($computer, $field_name, $value);
        $this->assertSame(1, $results['ok'], json_encode($results));

        $stored = $this->getStoredValues($container_key, $computer);
        $this->assertNotNull($stored, 'Value has not been stored in the targeted container');
        $this->assertEquals($value, $stored[$field_name]);

        // nothing must be stored in the other containers
        foreach (array_keys($this->containers) as $other_key) {
            if ($other_key !== $container_key) {
                $this->assertNull($this->getStoredValues($other_key, $computer), sprintf('Unexpected values stored in "%s" container', $other_key));
            }
        }
    }

    public function testMassiveUpdateWithContainerId(): void
    {
        $this->createContainers();

        $computer   = $this->createItem(Computer::class, ['name' => 'PC', 'entities_id' => $this->getTestRootEntity(true)]);
        $field_name = $this->fields['first_tab_text']->fields['name'];

        $results = $this->runMassiveUpdate($computer, $field_name, 'Atelier', $this->containers['first_tab']->getID());
        $this->assertSame(1, $results['ok'], json_encode($results));

        $this->assertSame('Atelier', $this->getStoredValues('first_tab', $computer)[$field_name] ?? null);
    }

    public function testMassiveUpdateWithoutUpdateRightOnContainer(): void
    {
        $this->createContainers();
        $this->setRightOnContainer('first_tab', READ);

        $entities_id = $this->getTestRootEntity(true);
        $computer    = $this->createItem(Computer::class, ['name' => 'PC', 'entities_id' => $entities_id]);
        $ticket      = $this->createItem(Ticket::class, ['name' => 'Lot', 'content' => 'Lot', 'entities_id' => $entities_id]);

        $this->runMassiveUpdate($computer, $this->fields['first_tab_ticket']->fields['name'], $ticket->getID());

        $this->assertNull($this->getStoredValues('first_tab', $computer));
    }

    public function testUpdateOfInactiveTabContainerField(): void
    {
        $this->createContainers();
        $this->updateItem(PluginFieldsContainer::class, $this->containers['first_tab']->getID(), ['is_active' => 0]);

        $computer = $this->createItem(Computer::class, ['name' => 'PC', 'entities_id' => $this->getTestRootEntity(true)]);
        $field_name = $this->fields['first_tab_text']->fields['name'];
        $this->updateItem(Computer::class, $computer->getID(), [$field_name => 'Atelier'], [$field_name]);

        $this->assertNull($this->getStoredValues('first_tab', $computer));
    }

    public function testUpdateWithoutFieldsDoesNotStoreTabContainersValues(): void
    {
        $this->createContainers();

        $computer = $this->createItem(Computer::class, ['name' => 'PC', 'entities_id' => $this->getTestRootEntity(true)]);
        $this->updateItem(Computer::class, $computer->getID(), ['comment' => 'updated']);

        foreach (array_keys($this->containers) as $container_key) {
            $this->assertNull($this->getStoredValues($container_key, $computer), sprintf('Unexpected values stored in "%s" container', $container_key));
        }
    }

    public function testUpdateOfTwoTabContainersFields(): void
    {
        $this->createContainers();

        $entities_id = $this->getTestRootEntity(true);
        $computer    = $this->createItem(Computer::class, ['name' => 'PC', 'entities_id' => $entities_id]);
        $ticket      = $this->createItem(Ticket::class, ['name' => 'Lot', 'content' => 'Lot', 'entities_id' => $entities_id]);
        $first_name  = $this->fields['first_tab_text']->fields['name'];
        $last_name   = $this->fields['last_tab_ticket']->fields['name'];

        $this->updateItem(
            Computer::class,
            $computer->getID(),
            [$first_name => 'Atelier', $last_name => $ticket->getID()],
            [$first_name, $last_name],
        );

        // both tab containers are saved
        $this->assertSame('Atelier', $this->getStoredValues('first_tab', $computer)[$first_name] ?? null);
        $this->assertEquals($ticket->getID(), $this->getStoredValues('last_tab', $computer)[$last_name] ?? null);
        $this->assertNull($this->getStoredValues('dom', $computer));
    }
}
