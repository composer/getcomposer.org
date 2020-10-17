#!/usr/bin/env php
<?php

ini_set('error_reporting', -1);
ini_set('display_errors', 1);
ini_set('date.timezone', 'UTC');

if (!file_exists(__DIR__.'/../../composer.github.io/installer.sig')) {
    throw new \RuntimeException('Make sure that a clone of https://github.com/composer/composer.github.io exists as '.dirname(__DIR__).'/composer.github.io');
}

$newChecksum = hash_file('sha384', __DIR__.'/../web/installer');
$oldChecksum = file_get_contents(__DIR__.'/../../composer.github.io/installer.sig');

echo 'Updating checksum from '.$oldChecksum.' to '.$newChecksum.PHP_EOL;

$files = [
    __DIR__.'/../templates/download.html.twig',
    __DIR__.'/../../composer.github.io/installer.sig',
    __DIR__.'/../../composer.github.io/installer.sha384sum',
    __DIR__.'/../../composer.github.io/pubkeys.html',
];

foreach ($files as $file) {
    $c = file_get_contents($file);
    $c = str_replace($oldChecksum, $newChecksum, $c, $count);
    if (!$count) {
        throw new \RuntimeException('Failed updating checksum in '.$file);
    }
    if (basename($file) === 'pubkeys.html') {
        $c = preg_replace('{Last Updated: \d+-\d+-\d+, also available as <a href="/installer.sig">}', 'Last Updated: '.date('Y-m-d').', also available as <a href="/installer.sig">', $c, 1, $count);
        if (!$count) {
            throw new \RuntimeException('Failed updating last updated date in '.$file);
        }
    }

    file_put_contents($file, $c);
    echo 'Updated checksum in '.$file.PHP_EOL;
}
