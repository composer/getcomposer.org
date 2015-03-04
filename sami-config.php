<?php

use Sami\Sami;
use Sami\Version\GitVersionCollection;

$dir = 'composer-src';

$versions = GitVersionCollection::create($dir)
    ->addFromTags('*')
    ->add('master', 'master branch')
;

return new Sami($dir.'/src', array(
    'theme' => 'enhanced',
    'versions' => $versions,
    'title' => 'Composer API',
    'build_dir' => 'web/apidoc/%version%',
    'cache_dir' => 'cache/%version%',
    'default_opened_level' => 2,
));
