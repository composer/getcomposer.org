<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

if (!isset($env) || $env !== 'dev') {
    // force ssl
    $app->before(function (Request $request) {
        // skip SSL & non-GET/HEAD requests
        if (strtolower($request->server->get('HTTPS')) == 'on' || strtolower($request->headers->get('X_FORWARDED_PROTO')) == 'https' || !$request->isMethodSafe()) {
            return;
        }

        return new RedirectResponse('https://'.substr($request->getUri(), 7));
    });

    $app->after(function (Request $request, Response $response) {
        if (!$response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31104000');
        }
    });
}
