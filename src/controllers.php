<?php

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;

$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html.twig');
})
->bind('home');

$app->get('/download/', function () use ($app) {
    $versions = array();
    foreach (glob(__DIR__.'/../web/download/*', GLOB_ONLYDIR) as $version) {
        $versions[basename($version)] = new \DateTime('@'.filemtime($version.'/composer.phar'));
    }

    uksort($versions, 'version_compare');
    $versions = array_reverse($versions);

    $data = array(
        'page' => 'download',
        'versions' => $versions,
        'windows' => false !== strpos($app['request']->headers->get('User-Agent'), 'Windows'),
    );

    return $app['twig']->render('download.html.twig', $data);
})
->bind('download');

$app->get('/download/{version}/composer.phar', function () {
    return new Response('Version Not Found', 404);
})
->bind('download_version');

$app->get('/doc/', function () use ($app) {
    $scan = function ($dir, $prefix = '') use ($app) {
        $finder = new Finder();
        $finder->files()
            ->in($dir)
            ->sortByName()
            ->depth(0);

        $filenames = array();

        foreach ($finder as $file) {
            $filename = basename($file->getPathname(), '.md');
            $url = $app['url_generator']->generate(
                'docs.view',
                array('page' => $prefix.$filename.'.md')
            );

            $metadata = null;
            if (preg_match('{^<!--(.*)-->}s', file_get_contents($file->getPathname()), $match)) {
                preg_match_all('{^ *(?P<keyword>\w+): *(?P<value>.*)}m', $match[1], $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $metadata[$match['keyword']] = $match['value'];
                }
            }

            $displayName = preg_replace('{^\d{2} }', '', ucwords(str_replace('-', ' ', $filename)));
            $filenames[$displayName] = array('link' => $url, 'metadata' => $metadata);
        }

        return $filenames;
    };

    $book = $scan($app['composer.doc_dir']);
    $articles = $scan($app['composer.doc_dir'].'/articles', 'articles/');
    $faqs = $scan($app['composer.doc_dir'].'/faqs', 'faqs/');

    return $app['twig']->render('doc.list.html.twig', array(
        'book' => $book,
        'articles' => $articles,
        'faqs' => $faqs,
        'page' => 'docs'
    ));
})
->bind('docs');

$app->get('/doc/{page}', function ($page) use ($app) {
    $filename = $app['composer.doc_dir'].'/'.$page;

    if (!file_exists($filename)) {
        $app->abort(404, 'Requested page was not found.');
    }

    $contents = file_get_contents($filename);
    $content = $app['markdown']->transformMarkdown($contents);

    $dom = new DOMDocument();
    $dom->loadHtml($content);
    $xpath = new DOMXPath($dom);

    $toc = array();
    $ids = array();

    $isSpan = function ($node) {
        return XML_ELEMENT_NODE === $node->nodeType && 'span' === $node->tagName;
    };

    $genId = function ($node) use (&$ids, $isSpan) {
        $count = 0;
        do {
            if ($isSpan($node->lastChild)) {
                $node = clone $node;
                $node->removeChild($node->lastChild);
            }

            $id = preg_replace('{[^a-z0-9]}i', '-', strtolower(trim($node->nodeValue)));
            $id = preg_replace('{-+}', '-', $id);
            if ($count) {
                $id .= '-'.($count+1);
            }
            $count++;
        } while (isset($ids[$id]));
        $ids[$id] = true;
        return $id;
    };

    $getDesc = function ($node) use ($isSpan) {
        if ($isSpan($node->lastChild)) {
            return $node->lastChild->nodeValue;
        }

        return null;
    };

    $getTitle = function ($node) use ($isSpan) {
        if ($isSpan($node->lastChild)) {
            $node = clone $node;
            $node->removeChild($node->lastChild);
        }

        return $node->nodeValue;
    };

    // build TOC & deep links
    $h1 = $h2 = $h3 = $h4 = 0;
    $nodes = $xpath->query('//*[self::h1 or self::h2 or self::h3 or self::h4]');
    foreach ($nodes as $node) {
        // set id and add anchor link
        $id = $genId($node);
        $title = $getTitle($node);
        if ('03-cli.md' === $page && 'Options' === $title) {
            continue;
        }
        $desc = $getDesc($node);
        $node->setAttribute('id', $id);
        $link = $dom->createElement('a', '#');
        $link->setAttribute('href', '#'.$id);
        $link->setAttribute('class', 'anchor');
        $node->appendChild($link);

        // parse into a tree
        switch ($node->nodeName) {
            case 'h1':
                $toc[++$h1] = array('title' => $title, 'id' => $id, 'desc' => $desc);
            break;

            case 'h2':
                $toc[$h1][++$h2] = array('title' => $title, 'id' => $id, 'desc' => $desc);
            break;

            case 'h3':
                $toc[$h1][$h2][++$h3] = array('title' => $title, 'id' => $id, 'desc' => $desc);
            break;

            case 'h4':
                $toc[$h1][$h2][$h3][++$h4] = array('title' => $title, 'id' => $id, 'desc' => $desc);
            break;
        }
    }

    // save new content with IDs
    $content = $dom->saveHtml();
    $content = preg_replace('{.*<body>(.*)</body>.*}is', '$1', $content);

    // add class to footer nav
    $content = preg_replace('{<p>(&larr;.+?|.+?&rarr;)</p>}', '<p class="prev-next">$1</p>', $content);

    return $app['twig']->render('doc.show.html.twig', array(
        'doc' => $content,
        'file' => $page,
        'page' => $page == '00-intro.md' ? 'getting-started' : 'docs',
        'toc' => $toc,
    ));
})
->assert('page', '[a-z0-9/\'-]+\.md')
->bind('docs.view');

