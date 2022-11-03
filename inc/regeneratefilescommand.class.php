<?php

use Glpi\Console\AbstractCommand;
use Symfony\Component\Console\Command\Command;

class PluginFieldsRegenerateFilesCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('plugin:fields:regenerate_files');
        $this->setDescription(__('Regenerate container files.', 'fields'));
        $this->setHelp(
            __('Check all stored containers files (classes & front) are present and create them if needed', 'fields')
            . "\n"
        );
    }

    protected function execute($input, $output)
    {
        plugin_fields_checkFiles();
        return Command::SUCCESS;
    }
}
