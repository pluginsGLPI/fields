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

use Glpi\Console\AbstractCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PluginFieldsCheckDatabaseCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('plugin:fields:check_database');
        $this->setDescription(__('Check database to detect inconsistencies.', 'fields'));
        $this->setHelp(
            __('This command will check database to detect following inconsistencies:', 'fields')
            . "\n"
            . sprintf(
                __('- some deleted fields may still be present in database (bug introduced in version %s and fixed in version %s)', 'fields'),
                '1.15.0',
                '1.15.3'
            )
        );

        $this->addOption(
            'fix',
            null,
            InputOption::VALUE_NONE,
            __('Use this option to actually fix database', 'fields')
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Read option
        $fix = $input->getOption('fix');

        $dead_fields = PluginFieldsMigration::checkDeadFields($fix);
        $dead_fields_count = count($dead_fields, COUNT_RECURSIVE) - count($dead_fields);

        // No invalid fields found
        if ($dead_fields_count === 0) {
            $output->writeln(
                '<info>' . __('Everything is in order - no action needed.', 'fields') . '</info>',
            );
            return Command::SUCCESS;
        }

        // Indicate which fields will have been or must be deleted
        $error = $fix
            ? sprintf(__('Database was containing orphaned data from %s improperly deleted field(s).', 'fields'), $dead_fields_count)
            : sprintf(__('Database contains orphaned data from %s improperly deleted field(s).', 'fields'), $dead_fields_count);
        $output->writeln('<error>' . $error . '</error>', OutputInterface::VERBOSITY_QUIET);

        foreach ($dead_fields as $table => $fields) {
            foreach ($fields as $field) {
                $info = $fix
                    ? sprintf(__('-> "%s.%s" has been deleted.', 'fields'), $table, $field)
                    : sprintf(__('-> "%s.%s" should be deleted.', 'fields'), $table, $field);
                $output->writeln($info);
            }
        }

        // Show extra info in dry-run mode
        if (!$fix) {
            // Print command to do the actual deletion
            $next_command = sprintf(
                __('Run "%s" to fix database inconsistencies.', 'fields'),
                sprintf("php bin/console %s --fix", $this->getName())
            );
            $output->writeln(
                '<comment>' . $next_command . '</comment>',
                OutputInterface::VERBOSITY_QUIET
            );
        }

        return Command::SUCCESS;
    }
}
