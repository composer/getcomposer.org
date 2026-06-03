#!/usr/bin/env php
<?php

use App\Version\Versions;

require __DIR__.'/../vendor/autoload.php';

/**
 * Generates the web/versions JSON from the version numbers resolved by
 * update.sh. Arguments are passed as key=value pairs. Each maintained LTS line
 * is passed as its own `*_lts` argument (e.g. v2_2_lts=2.2.28), so multiple LTS
 * lines can coexist:
 *
 *   php bin/generate-versions.php stable=2.10.0 v1=1.10.28 v2_2_lts=2.2.28 \
 *       preview=2.10.0 v2=2.10.0 v2Path=/download/2.10.0/composer.phar snapshot=<sha>
 */

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (!str_contains($arg, '=')) {
        fwrite(STDERR, "Invalid argument '$arg', expected key=value\n");
        exit(1);
    }
    [$key, $value] = explode('=', $arg, 2);
    $args[$key] = $value;
}

$required = ['stable', 'v1', 'preview', 'v2', 'v2Path', 'snapshot'];
$missing = array_diff($required, array_keys($args));
if ($missing !== []) {
    fwrite(STDERR, 'Missing required argument(s): '.implode(', ', $missing)."\n");
    exit(1);
}

// Each maintained LTS line is passed as its own `*_lts` argument, e.g.
// v2_2_lts=2.2.28 (and later v2_11_lts=2.11.5). Argument order sets fallback order.
$lts = [];
foreach ($args as $key => $value) {
    if (str_ends_with($key, '_lts') && $value !== '') {
        $lts[] = $value;
    }
}
if ($lts === []) {
    fwrite(STDERR, "Missing required argument: at least one *_lts (e.g. v2_2_lts=2.2.28)\n");
    exit(1);
}

$resolved = [
    'stable' => $args['stable'],
    'v1' => $args['v1'],
    'lts' => $lts,
    'preview' => $args['preview'],
    'v2' => $args['v2'],
    'v2Path' => $args['v2Path'],
    'snapshot' => $args['snapshot'],
];

echo json_encode(Versions::generate($resolved), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
