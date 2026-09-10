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
use PluginFieldsContainer;

final class ContainerItemRightTest extends DbTestCase
{
    public function testCanUpdateTargetItemFollowsRightOnItem(): void
    {
        $this->login();
        $entity_id = getItemByTypeName(Entity::class, '_test_root_entity', true);
        $this->setEntity($entity_id, true);
        $computer = $this->createItem(Computer::class, [
            'name'        => 'Computer ' . $this->getUniqueString(),
            'entities_id' => $entity_id,
        ]);

        $this->assertTrue(PluginFieldsContainer::canUpdateTargetItem(Computer::class, $computer->getID()));

        $this->login('post-only', 'postonly');
        $this->setEntity($entity_id, true);

        $this->assertFalse(PluginFieldsContainer::canUpdateTargetItem(Computer::class, $computer->getID()));
    }

    public function testCanUpdateTargetItemRejectsInvalidItemtype(): void
    {
        $this->login();

        $this->assertFalse(PluginFieldsContainer::canUpdateTargetItem('', 1));
    }
}
