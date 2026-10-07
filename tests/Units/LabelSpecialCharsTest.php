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
use PHPUnit\Framework\Attributes\DataProvider;
use PluginFieldsContainer;
use PluginFieldsDropdown;
use PluginFieldsField;
use PluginFieldsLabelTranslation;

require_once __DIR__ . '/../FieldTestCase.php';

/**
 * Labels are free text: they must be stored and displayed as typed by the user,
 * while system names (tables, columns, classes, files) stay restricted to safe chars.
 */
final class LabelSpecialCharsTest extends DbTestCase
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

    public static function provideLabels(): iterable
    {
        yield 'apostrophe' => [
            'label' => "N° d'inventaire",
        ];

        yield 'slash' => [
            'label' => 'Site / Bâtiment',
        ];

        yield 'parentheses and ampersand' => [
            'label' => 'Contrat (R&D)',
        ];

        yield 'double quotes' => [
            'label' => 'Le "bon" champ',
        ];

        yield 'accents and punctuation' => [
            'label' => 'Échéance : été, hiver ?',
        ];

        yield 'html markup' => [
            'label' => '<b>Important</b>',
        ];

        yield 'php code injection attempt' => [
            'label' => "x'); die('pwned'); //",
        ];
    }

    #[DataProvider('provideLabels')]
    public function testContainerLabelKeepsSpecialChars(string $label): void
    {
        $container = $this->createFieldContainer([
            'label'        => $label,
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $this->assertTrue($container->getFromDB($container->getID()));
        $this->assertSame($label, $container->fields['label']);

        // System name is still restricted to identifier safe chars
        $this->assertMatchesRegularExpression('/^[a-z]+$/', $container->fields['name']);

        // Displayed label (translation) is kept as typed
        $this->assertSame(
            $label,
            PluginFieldsLabelTranslation::getLabelFor([
                'itemtype' => PluginFieldsContainer::class,
                'id'       => $container->getID(),
            ]),
        );
    }

    #[DataProvider('provideLabels')]
    public function testFieldLabelKeepsSpecialChars(string $label): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'Label special chars ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => $label,
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
        ]);

        $this->assertTrue($field->getFromDB($field->getID()));
        $this->assertSame($label, $field->fields['label']);

        // System name is still restricted to identifier safe chars
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', $field->fields['name']);

        // Displayed label (translation) is kept as typed
        $this->assertSame(
            $label,
            PluginFieldsLabelTranslation::getLabelFor([
                'itemtype' => PluginFieldsField::class,
                'id'       => $field->getID(),
            ]),
        );
    }

    #[DataProvider('provideLabels')]
    public function testTranslationUpdateKeepsSpecialChars(string $label): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'Label translation ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => 'Initial label',
            'type'                                      => 'text',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
        ]);

        $translations = (new PluginFieldsLabelTranslation())->find([
            'itemtype' => PluginFieldsField::class,
            'items_id' => $field->getID(),
            'language' => $_SESSION['glpilanguage'],
        ]);
        $this->assertCount(1, $translations);

        $translation = new PluginFieldsLabelTranslation();
        $this->assertTrue($translation->update([
            'id'    => array_key_first($translations),
            'label' => $label,
        ]));

        $this->assertSame(
            $label,
            PluginFieldsLabelTranslation::getLabelFor([
                'itemtype' => PluginFieldsField::class,
                'id'       => $field->getID(),
            ]),
        );
    }

    #[DataProvider('provideLabels')]
    public function testDropdownClassIsSafelyGeneratedWithSpecialCharsInLabel(string $label): void
    {
        $container = $this->createFieldContainer([
            'label'        => 'Label dropdown ' . $this->getUniqueString(),
            'type'         => 'tab',
            'itemtypes'    => [Computer::class],
            'is_active'    => 1,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        $field = $this->createField([
            'label'                                     => $label,
            'type'                                      => 'dropdown',
            PluginFieldsContainer::getForeignKeyField() => $container->getID(),
            'ranking'                                   => 1,
            'is_active'                                 => 1,
        ]);

        $classname = PluginFieldsDropdown::getClassname($field->fields['name']);

        // The label is injected in the generated class file as an exported string literal only
        $class_file = PLUGINFIELDS_CLASS_PATH . '/' . $field->fields['name'] . 'dropdown.class.php';
        $this->assertFileExists($class_file);
        $this->assertStringContainsString(var_export($label, true), (string) file_get_contents($class_file));

        // The generated class is valid, loadable and returns the label as typed
        $this->assertTrue(class_exists($classname));
        $this->assertSame($label, $classname::getTypeName());
    }
}