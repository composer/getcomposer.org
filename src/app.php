<?php

require __DIR__.'/../vendor/.composer/autoload.php';

use Symfony\Component\Finder\Finder;
use dflydev\markdown\MarkdownExtraParser;

$app = new Silex\Application();

$app['debug'] = $_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1';

$app->register(new Silex\Provider\SymfonyBridgesServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app['composer.doc_dir'] = __DIR__.'/../vendor/composer/composer/doc';

$app['markdown'] = function () {
    return new MarkdownExtraParser();
};

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html.twig');
})
->bind('home');

$app->get('/download', function () use ($app) {
    return $app['twig']->render('download.html.twig');
})
->bind('download');

$app->get('/doc', function () use ($app) {
    $finder = new Finder();
    $finder->files()
        ->in($app['composer.doc_dir'])
        ->depth(0);

    $filenames = array();

    foreach ($finder as $file) {
        $filename = basename($file->getPathname(), '.md');
        $url = $app['url_generator']->generate('docs.view', array('page' => $filename));

        $displayName = ucwords(str_replace('-', ' ', $filename));
        $filenames[$displayName] = $url;
    }

    return $app['twig']->render('doc.list.html.twig', array('filenames' => $filenames));
})
->bind('docs');

$app->get('/doc/{page}', function ($page) use ($app) {
    $filename = $app['composer.doc_dir'].'/'.str_replace('.', '', $page).'.md';

    if (!file_exists($filename)) {
        $app->abort(404, 'Requested page was not found.');
    }

    $contents = file_get_contents($filename);
    $page = $app['markdown']->transformMarkdown($contents);

    return $app['twig']->render('doc.show.html.twig', array('doc' => $page));
})
->bind('docs.view');

return $app;
