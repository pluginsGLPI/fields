<?php

require __DIR__ . '/../../../tests/bootstrap.php';

if (!Plugin::isPluginActive("fields")) {
    throw new RuntimeException("Plugin fields is not active in the test database");
}
