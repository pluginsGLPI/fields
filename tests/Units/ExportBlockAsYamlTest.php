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
use PluginFieldsContainer;
use PluginFieldsProfile;

require_once __DIR__ . '/../FieldTestCase.php';

final class ExportBlockAsYamlTest extends DbTestCase
{
    use FieldTestTrait;

    private int $root_entity_id;

    private int $child_entity_id;

    public function setUp(): void
    {
        GLPITestCase::setUp();
        $this->login();
        $this->root_entity_id  = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $this->child_entity_id = getItemByTypeName(Entity::class, '_test_child_1', true);
        $this->setEntity($this->root_entity_id, true);
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        GLPITestCase::tearDown();
    }

    public function testVisibleBlockIsExported(): void
    {
        $container = $this->createContainer($this->root_entity_id);

        $this->assertTrue(plugin_fields_exportBlockAsYaml($container->getID()));
        $this->assertStringContainsString(
            $container->getID() . '-' . Computer::class,
            (string) file_get_contents(GLPI_TMP_DIR . '/fields_conf.yaml'),
        );
    }

    public function testBlockWithoutProfileAccessIsOmitted(): void
    {
        $container = $this->createContainer($this->root_entity_id);

        $profile_right = new PluginFieldsProfile();
        $this->assertTrue($profile_right->getFromDBByCrit([
            'profiles_id'                 => $_SESSION['glpiactiveprofile']['id'],
            'plugin_fields_containers_id' => $container->getID(),
        ]));
        $this->updateItem(PluginFieldsProfile::class, $profile_right->getID(), ['right' => 0]);

        $this->assertFalse(plugin_fields_exportBlockAsYaml($container->getID()));
    }

    public function testBlockOutsideActiveEntitiesIsOmitted(): void
    {
        $container = $this->createContainer($this->child_entity_id);

        $this->setEntity($this->root_entity_id, false);
        $this->assertFalse(plugin_fields_exportBlockAsYaml($container->getID()));

        $this->setEntity($this->child_entity_id, false);
        $this->assertTrue(plugin_fields_exportBlockAsYaml($container->getID()));
    }

    private function createContainer(int $entities_id): PluginFieldsContainer
    {
        $container = $this->createFieldContainer([
            'label'        => 'Export ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => $entities_id,
            'is_recursive' => 0,
        ]);
        $this->createField([
            'label'                                     => 'Exported text',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
            'is_readonly'                               => 0,
        ]);

        return $container;
    }
}
