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

final class FieldContainerTest extends DbTestCase
{
    use FieldTestTrait;

    public function tearDown(): void
    {
        $this->tearDownFieldTest();
        parent::tearDown();
    }

    public static function provideCheckContainerName(): iterable
    {
        yield 'empty name'          => [
            false,
            '',
        ];
        yield 'wrong numeric name'  => [
            false,
            '11753069',
        ];
        yield 'valid name'          => [
            true,
            'testCheckContainerName',
        ];
        yield 'valid numeric name'  => [
            false,
            'd7',
        ];
    }

    #[DataProvider('provideCheckContainerName')]
    public function testCheckContainerName(bool $expected, string $label): void
    {
        $container = new PluginFieldsContainer();
        $input = [
            'label'     => $label,
            'itemtypes' => [Computer::class],
            'type'      => 'tab',
        ];
        $result = $container->add($input);
        $this->assertSame($expected, $result);
    }
}
