<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app->after(function (Request $request, Response $response) {
    if (!$response->headers->has('Strict-Transport-Security')) {
        $response->headers->set('Strict-Transport-Security', 'max-age=2592000');
    }
});
