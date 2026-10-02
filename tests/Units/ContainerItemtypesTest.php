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
use DbTestCase;
use GLPITestCase;
use GlpiPlugin\Field\Tests\FieldTestTrait;
use Monitor;
use PluginFieldsContainer;
use PluginFieldsToolbox;

require_once __DIR__ . '/../FieldTestCase.php';

final class ContainerItemtypesTest extends DbTestCase
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

    public static function provideMalformedItemtypes(): iterable
    {
        yield 'path separator' => ['itemtype' => '../Computer'];
        yield 'leading digit' => ['itemtype' => '1Computer'];
        yield 'whitespace' => ['itemtype' => 'Computer Model'];
    }

    /**
     * @dataProvider provideMalformedItemtypes
     */
    public function testAddWithMalformedItemtypeIsRejected(string $itemtype): void
    {
        $container = new PluginFieldsContainer();
        $result = $container->add([
            'label'        => 'Malformed itemtype',
            'type'         => 'tab',
            'itemtypes'    => [$itemtype],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $this->assertFalse($result);
        $this->hasSessionMessages(ERROR, ['At least one selected object is not a valid element type']);
    }

    /**
     * @dataProvider provideMalformedItemtypes
     */
    public function testUpdateWithMalformedItemtypeIsRejected(string $itemtype): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'Upd malformed',
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);
        $original_itemtypes = $container->fields['itemtypes'];

        $result = $container->update([
            'id'        => $container->getID(),
            'itemtypes' => json_encode([$itemtype]),
        ]);

        $this->assertFalse($result);
        $this->hasSessionMessages(ERROR, ['At least one selected object is not a valid element type']);
        $container->getFromDB($container->getID());
        $this->assertSame($original_itemtypes, $container->fields['itemtypes']);
    }

    public function testUpdateWithValidItemtypesReencodesThem(): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'Upd valid',
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $result = $container->update([
            'id'        => $container->getID(),
            'itemtypes' => json_encode([Computer::class, Monitor::class]),
        ]);

        $this->assertTrue($result);
        $container->getFromDB($container->getID());
        $this->assertSame(
            [Computer::class, Monitor::class],
            PluginFieldsToolbox::decodeJSONItemtypes($container->fields['itemtypes']),
        );
    }
}
