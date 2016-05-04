<?php

use Symfony\Component\ClassLoader\DebugClassLoader;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\Debug\Debug;

require_once __DIR__.'/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(-1);
DebugClassLoader::enable();
Debug::enable();
if ('cli' !== php_sapi_name()) {
    ExceptionHandler::register();
}

$app = require __DIR__.'/../src/app.php';
require __DIR__.'/../config/dev.php';
require __DIR__.'/../src/controllers.php';
$app->run();
