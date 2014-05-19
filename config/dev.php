<?php

use Silex\Provider\MonologServiceProvider;

$env = 'dev';

// include the prod configuration
require __DIR__.'/prod.php';

// enable the debug mode
$app['debug'] = true;

$app->register(new MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../silex.log',
));
