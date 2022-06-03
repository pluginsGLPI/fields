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
 * @copyright Copyright (C) 2013-2022 by Fields plugin team.
 * @license   GPLv2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://github.com/pluginsGLPI/fields
 * -------------------------------------------------------------------------
 */

class PluginFieldsAutoloader
{
    protected $paths = [];

    public function __construct($options = null)
    {
        if (null !== $options) {
            $this->setOptions($options);
        }
    }

    public function setOptions($options)
    {
        if (!is_array($options) && !($options instanceof \Traversable)) {
            throw new \InvalidArgumentException();
        }

        foreach ($options as $path) {
            if (!in_array($path, $this->paths)) {
                $this->paths[] = $path;
            }
        }
        return $this;
    }

    public function processClassname($classname)
    {
        $matches = [];
        preg_match("/Plugin([A-Z][a-z0-9]+)([A-Z]\w+)/", $classname, $matches);

        if (count($matches) < 3) {
            return false;
        } else {
            return $matches;
        }
    }

    public function autoload($classname)
    {
        $matches = $this->processClassname($classname);

        if ($matches !== false) {
            $plugin_name = strtolower($matches[1]);
            $class_name = strtolower($matches[2]);

            if ($plugin_name !== "fields") {
                return false;
            }

            $filename = implode(".", [
                $class_name,
                "class",
                "php"
            ]);

            foreach ($this->paths as $path) {
                 $test = $path . DIRECTORY_SEPARATOR . $filename;
                if (file_exists($test)) {
                    return include_once($test);
                }
            }
        }
        return false;
    }

    public function register()
    {
        spl_autoload_register([$this, 'autoload']);
    }
}
