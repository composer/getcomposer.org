<?php

namespace App\Controller;

use Composer\Pcre\Preg;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DocsController extends AbstractController
{
    #[Route("/doc/", name: "docs")]
    public function index(string $docDir): Response
    {
        $scan = function ($dir, $prefix = '') {
            $finder = new Finder();
            $finder->files()
                ->in($dir)
                ->sortByName()
                ->depth(0);

            $filenames = array();

            foreach ($finder as $file) {
                $filename = basename($file->getPathname(), '.md');
                $url = $this->generateUrl(
                    'docs.view',
                    array('page' => $prefix.$filename.'.md')
                );

                $metadata = null;
                $contents = file_get_contents($file->getPathname());
                assert(is_string($contents));
                if (Preg::isMatchStrictGroups('{^<!--(.*)-->}s', $contents, $match)) {
                    Preg::matchAllStrictGroups('{^ *(?P<keyword>\w+): *(?P<value>.*)}m', $match[1], $matches);
                    $metadata = [];
                    foreach ($matches['keyword'] as $key => $keyword) {
                        $metadata[$keyword] = $matches['value'][$key];
                    }
                }

                if (Preg::isMatch('{^(<!--.+?-->\n+)?# (.+?)\n}s', $contents, $match)) {
                    $displayName = $match[2];
                } else {
                    $displayName = Preg::replace('{^\d{2} }', '', ucwords(str_replace('-', ' ', $filename)));
                }
                $filenames[$displayName] = array('link' => $url, 'metadata' => $metadata);
            }

            return $filenames;
        };

        $book = $scan($docDir);
        $articles = $scan($docDir.'/articles', 'articles/');
        $faqs = $scan($docDir.'/faqs', 'faqs/');

        return $this->render('doc.list.html.twig', array(
            'book' => $book,
            'articles' => $articles,
            'faqs' => $faqs,
            'page' => 'docs'
        ));
    }

    #[Route("/doc/{page}", name: "docs.view", requirements: ["page" => "[a-z0-9/\'-]+\.md"])]
    public function viewPage(string $page, string $docDir, \Parsedown $parsedown): Response
    {
        $filename = $docDir.'/'.$page;

        if (!file_exists($filename)) {
            if ($page === 'articles/handling-private-packages-with-satis.md') {
                return new RedirectResponse('/doc/articles/handling-private-packages.md', 301);
            }

            if ($page === 'articles/http-basic-authentication.md') {
                return new RedirectResponse('/doc/articles/authentication-for-private-packages.md#http-basic', 301);
            }

            if ($page === 'faqs/why-can\'t-composer-load-repositories-recursively.md') {
                return new RedirectResponse('/doc/faqs/why-cant-composer-load-repositories-recursively.md', 301);
            }

            throw $this->createNotFoundException('Requested page was not found.');
        }

        $contents = file_get_contents($filename);
        /** @var string $content */
        $content = $parsedown->text($contents);
        $content = str_replace(array('class="language-jsonc', 'class="language-json', 'class="language-js'), 'class="language-javascript', $content);
        $content = str_replace('class="language-sh', 'class="language-bash', $content);
        $content = str_replace('class="language-ini', 'class="language-clike', $content);

        $dom = new \DOMDocument();
        $dom->loadHtml('<?xml encoding="UTF-8">' . $content);
        $xpath = new \DOMXPath($dom);

        $toc = array();
        $ids = array();

        $isSpan = function (?\DOMNode $node) {
            return $node !== null && XML_ELEMENT_NODE === $node->nodeType && $node instanceof \DOMElement && 'span' === $node->tagName;
        };

        // same slugs as GitHub so that in-doc links work on both
        $genId = function (\DOMNode $node) use (&$ids): string {
            $base = Preg::replace('{[^\p{L}\p{M}\p{N}\p{Pc}\- ]}u', '', strtolower(trim((string) $node->nodeValue)));
            $base = str_replace(' ', '-', $base);
            $id = $base;
            for ($count = 1; isset($ids[$id]); $count++) {
                $id = $base.'-'.$count;
            }
            $ids[$id] = true;

            return $id;
        };

        // pre-GitHub-compatible slugs, kept as aliases so existing deep links still work
        $legacyIds = array();
        $genLegacyId = function (\DOMNode $node) use (&$legacyIds, $isSpan): string {
            $count = 0;
            do {
                if ($isSpan($node->lastChild)) {
                    $node = clone $node;
                    if ($node->lastChild !== null) {
                        $node->removeChild($node->lastChild);
                    }
                }

                $id = Preg::replace('{[^a-z0-9]}i', '-', strtolower(trim((string) $node->nodeValue)));
                $id = Preg::replace('{-+}', '-', $id);
                if ($count) {
                    $id .= '-'.$count;
                }
                $count++;
            } while (isset($legacyIds[$id]));
            $legacyIds[$id] = true;
            return $id;
        };

        $getDesc = function (\DOMNode $node) use ($isSpan): ?string {
            if ($node->lastChild !== null && $isSpan($node->lastChild)) {
                return $node->lastChild->nodeValue;
            }

            return null;
        };

        $getTitle = function (\DOMNode $node) use ($isSpan): ?string {
            if ($isSpan($node->lastChild)) {
                $node = clone $node;
                if ($node->lastChild !== null) {
                    $node->removeChild($node->lastChild);
                }
            }

            return $node->nodeValue;
        };

        // build TOC & deep links
        $firstTitle = null;
        $aliases = [];
        $h1 = $h2 = $h3 = $h4 = 0;
        $nodes = $xpath->query('//*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]');
        if (false === $nodes) {
            throw new \RuntimeException('Failed finding any h1-h6 node');
        }
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            // set id and add anchor link
            $id = $genId($node);
            $legacyId = in_array($node->nodeName, ['h5', 'h6'], true) ? null : $genLegacyId($node);
            $title = $getTitle($node);
            if ('03-cli.md' === $page && 'Options' === $title) {
                continue;
            }
            $aliases[] = [$node, $legacyId];
            $desc = $getDesc($node);
            $node->setAttribute('id', $id);
            $link = $dom->createElement('a', '#');
            $link->setAttribute('href', '#'.$id);
            $link->setAttribute('class', 'anchor');
            $node->appendChild($link);

            if (empty($firstTitle)) {
                $firstTitle = $title;
            }

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

        foreach ($aliases as [$node, $legacyId]) {
            if ($legacyId !== null && !isset($ids[$legacyId]) && $node->parentNode !== null) {
                $alias = $dom->createElement('a');
                $alias->setAttribute('id', $legacyId);
                $node->parentNode->insertBefore($alias, $node);
            }
        }

        // save new content with IDs
        $content = $dom->saveHtml();
        if (false === $content) {
            throw new \RuntimeException('Failed saving HTML');
        }
        $content = Preg::replace('{.*<body>(.*)</body>.*}is', '$1', $content);

        // add class to footer nav
        $content = Preg::replace('{<p>(&larr;.+?|.+?&rarr;)</p>}', '<p class="prev-next">$1</p>', $content);

        return $this->render('doc.show.html.twig', array(
            'doc' => $content,
            'file' => $page,
            'page' => $page == '00-intro.md' ? 'getting-started' : 'docs',
            'toc' => $toc,
            'title' => $firstTitle
        ));
    }
}
