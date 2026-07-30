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

use Rector\Configuration\RectorConfigBuilder;

require_once __DIR__ . '/../../src/Plugin.php';

$baseline_file = __DIR__ . '/../../PluginsRector.php';
if (!file_exists($baseline_file)) {
    throw new RuntimeException(
        sprintf(
            'Unable to find "%s". Running rector on a plugin requires a GLPI development checkout that ships PluginsRector.php.',
            $baseline_file,
        ),
    );
}

$baseline = require $baseline_file;

/** @var RectorConfigBuilder $config */
$config = $baseline([
    __DIR__ . '/ajax',
    __DIR__ . '/front',
    __DIR__ . '/inc',
    __DIR__ . '/src',
    __DIR__ . '/tests',
]);

return $config;
