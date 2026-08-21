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
use DBmysql;
use Glpi\Tests\DbTestCase;
use Glpi\Tests\GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use PluginFieldsContainer;
use PluginFieldsMigration;

require_once __DIR__ . '/../FieldTestCase.php';

final class MigrationTest extends DbTestCase
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

    public function testCheckContainerTablesConsistencyOnHealthyContainer(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'Consistent ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $result = PluginFieldsMigration::checkContainerTablesConsistency();

        $missing_ids = array_column($result['missing'], 'container_id');
        $this->assertNotContains($container->getID(), $missing_ids);
    }

    public function testCheckContainerTablesConsistencyDetectsMissingTable(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'MissingTable ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $table = getTableForItemType(PluginFieldsContainer::getClassname(Computer::class, $container->fields['name']));

        /** @var DBmysql $DB */
        global $DB;
        $DB->doQuery(sprintf('DROP TABLE IF EXISTS `%s`', $table));

        try {
            $result = PluginFieldsMigration::checkContainerTablesConsistency();

            $missing_entry = null;
            foreach ($result['missing'] as $entry) {
                if ($entry['container_id'] === $container->getID()) {
                    $missing_entry = $entry;
                }
            }

            $this->assertNotNull($missing_entry);
            $this->assertSame(Computer::class, $missing_entry['itemtype']);
            $this->assertSame($table, $missing_entry['table']);
        } finally {
            // Recreate the table so container cleanup in tearDown does not fail.
            PluginFieldsContainer::create($container->fields);
        }
    }

    public function testCheckContainerTablesConsistencyDetectsOrphanedTable(): void
    {
        $orphan_table = 'glpi_plugin_fields_' . strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', (string) $this->getUniqueString())) . 'orphan';

        /** @var DBmysql $DB */
        global $DB;
        $DB->doQuery(sprintf(
            'CREATE TABLE `%s` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`))',
            $orphan_table,
        ));

        try {
            $result = PluginFieldsMigration::checkContainerTablesConsistency();

            $this->assertContains($orphan_table, $result['orphaned']);
        } finally {
            $DB->doQuery(sprintf('DROP TABLE IF EXISTS `%s`', $orphan_table));
        }
    }
}
