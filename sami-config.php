<?php

use Sami\Sami;
use Sami\RemoteRepository\GitHubRemoteRepository;
use Symfony\Component\Finder\Finder;
use Sami\Version\GitVersionCollection;

$dir = __DIR__.'/composer-src';

$iterator = Finder::create()
    ->files()
    ->name('*.php')
    ->in($dir.'/src')
;

$versions = GitVersionCollection::create($dir)
    ->addFromTags('*')
    ->add('master', 'master branch')
;

return new Sami($iterator, array(
    'theme'                => 'default',
    'versions'             => $versions,
    'title'                => 'Composer API',
    'build_dir'            => __DIR__.'/web/apidoc/%version%',
    'cache_dir'            => __DIR__.'/cache/%version%',
    'remote_repository'    => new GitHubRemoteRepository('composer/composer', $dir),
    'default_opened_level' => 2,
));
