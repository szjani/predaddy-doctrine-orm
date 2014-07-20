<?php
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    $loader = include __DIR__ . '/../vendor/autoload.php';
    $loader->add('predaddy', __DIR__ . '/src');
    $loader->add('predaddy', __DIR__ . '/../vendor/predaddy/predaddy/tests/src');
}
Logger::configure(__DIR__ . '/src/resources/log4php.xml');
