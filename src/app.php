<?php

require __DIR__.'/../vendor/.composer/autoload.php';

use Symfony\Component\Finder\Finder;
use dflydev\markdown\MarkdownExtraParser;

$app = new Silex\Application();

$app['debug'] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

$app->register(new Silex\Provider\UrlGeneratorServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

$app['composer.doc_dir'] = __DIR__.'/../vendor/composer/composer/doc';

$app['markdown'] = function () {
    return new MarkdownExtraParser();
};

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html.twig');
});

$app->get('/doc', function () use ($app) {
    $finder = new Finder();
    $finder->files()
        ->in($app['composer.doc_dir'])
        ->depth(0);

    $filenames = array();

    foreach ($finder as $file) {
        $filename = $file->getRelativePathname();
        $url = $app['url_generator']->generate('doc.show', array('doc' => $filename));

        $filenames[$filename] = $url;
    }

    return $app['twig']->render('doc.list.html.twig', array('filenames' => $filenames));
});

$app->get('/doc/{doc}', function ($doc) use ($app) {
    $filename = $app['composer.doc_dir'].'/'.$doc;

    if (!file_exists($filename)) {
        $app->abort(404, 'Requested doc was not found.');
    }

    $contents = file_get_contents($filename);
    $doc = $app['markdown']->transformMarkdown($contents);

    return $app['twig']->render('doc.show.html.twig', array('doc' => $doc));
})
->bind('doc.show');

return $app;
