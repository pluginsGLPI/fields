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

namespace GlpiPlugin\Field\Tests\Units;

use Computer;
use Glpi\Tests\DbTestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PluginFieldsContainer;

require_once __DIR__ . '/../FieldTestCase.php';

final class ContainerTest extends DbTestCase
{
    use FieldTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->login();
    }

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        parent::tearDown();
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
}
