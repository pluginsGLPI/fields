<?php

use Rector\Configuration\RectorConfigBuilder;

declare(strict_types=1);

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
