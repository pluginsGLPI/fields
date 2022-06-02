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

use Glpi\Console\AbstractCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PluginFieldsFixDroppedFieldsCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('plugin:fields:fixdroppedfields');
        $this->setAliases(['fields:fixdroppedfields']);
        $this->setDescription(
            'Remove fields that were wrongly kept in the database following an '
            . 'issue introduced in 1.15.0 and fixed in 1.15.3.'
        );

        $this->addOption(
            "delete",
            null,
            InputOption::VALUE_NONE,
            "Use this option to actually delete data"
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Read option
        $delete = $input->getOption("delete");

        $fields = PluginFieldsMigration::fixDroppedFields(!$delete);

        // No invalid fields found
        if (!count($fields)) {
            $output->writeln(
                __("Everything is in order - no action needed.", 'fields'),
            );
            return Command::SUCCESS;
        }

        // Indicate which fields will have been or must be deleted
        foreach ($fields as $field) {
            if ($delete) {
                $info = sprintf(__("-> %s was deleted.", 'fields'), $field);
            } else {
                $info = sprintf(__("-> %s must be deleted.", 'fields'), $field);
            }

            $output->writeln($info);
        }

        // Show extra info in dry-run mode
        if (!$delete) {
            $fields_found = sprintf(
                __("%s field(s) need to be deleted.", 'fields'),
                count($fields)
            );
            $output->writeln($fields_found);

            // Print command to do the actual deletion
            $next_command = sprintf(
                __("Run \"%s\" to delete the found field(s).", 'fields'),
                "php bin/console plugin:fields:fixdroppedfields --delete"
            );
            $output->writeln($next_command);
        }

        return Command::SUCCESS;
    }
}
