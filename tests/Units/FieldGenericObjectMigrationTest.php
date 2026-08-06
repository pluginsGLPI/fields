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

use DBmysql;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use PluginFieldsContainer;
use PluginFieldsField;
use PluginFieldsMigration;
use Psr\Log\LogLevel;
use RuntimeException;
use Ticket;

require_once __DIR__ . '/../FieldTestCase.php';

final class FieldGenericObjectMigrationTest extends DbTestCase
{
    use FieldTestTrait;

    public function setUp(): void
    {
        GLPITestCase::setUp();
        $this->login();

        /** @var DBmysql $DB */
        global $DB;
        $DB->doQuery(
            'CREATE TABLE IF NOT EXISTS `glpi_plugin_genericobject_types` (
                `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `itemtype` VARCHAR(255) DEFAULT NULL,
                `name`     VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();

        /** @var DBmysql $DB */
        global $DB;
        $DB->dropTable('glpi_plugin_genericobject_types');

        GLPITestCase::tearDown();
    }

    public function testMigrationGuardTriggersWhenGenericobjectFieldsExist(): void
    {
        $container = $this->createFieldContainer([
            'label'       => 'Genericobject Migration Container',
            'type'        => 'tab',
            'itemtypes'   => [Ticket::class],
            'is_active'   => 1,
            'entities_id' => 0,
        ]);

        $this->createField([
            'label'                                      => 'Genericobject Field',
            'type'                                        => 'dropdown-PluginGenericobjectFoo',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                     => 1,
            'is_active'                                   => 1,
            'is_readonly'                                  => 0,
        ]);

        try {
            PluginFieldsField::installBaseData(new PluginFieldsMigration('0'), '0');
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $runtimeException) {
            $this->assertStringContainsString('GenericObject plugin cannot be migrated', $runtimeException->getMessage());
        }

        $this->hasPhpLogRecordThatContains(
            'plugin_version_genericobject method must be defined!',
            LogLevel::WARNING,
        );
    }

    public function testMigrationGuardDoesNotTriggerWithoutGenericobjectFields(): void
    {
        $container = $this->createFieldContainer([
            'label'       => 'Regular Container',
            'type'        => 'tab',
            'itemtypes'   => [Ticket::class],
            'is_active'   => 1,
            'entities_id' => 0,
        ]);

        $this->createField([
            'label'                                      => 'Plain Field',
            'type'                                        => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                     => 1,
            'is_active'                                   => 1,
            'is_readonly'                                  => 0,
        ]);

        $result = PluginFieldsField::installBaseData(new PluginFieldsMigration('0'), '0');

        $this->assertTrue($result);
    }
}
