<?php

namespace App\Controller;

use Composer\Pcre\Preg;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @phpstan-type Page array{page: string, metadata: array<string, string>|null, contents: string}
 */
class DocsController extends AbstractController
{
    /** old page => [new page, fragment] */
    private const array MOVED_PAGES = [
        'articles/handling-private-packages-with-satis.md' => ['articles/handling-private-packages.md', null],
        'articles/http-basic-authentication.md' => ['articles/authentication-for-private-packages.md', 'http-basic'],
        'faqs/why-can\'t-composer-load-repositories-recursively.md' => ['faqs/why-cant-composer-load-repositories-recursively.md', null],
    ];

    #[Route("/doc/", name: "docs")]
    public function index(string $docDir): Response
    {
        $sections = $this->listSections($docDir);

        return $this->render('doc.list.html.twig', [
            'book' => $sections['Book'],
            'articles' => $sections['Articles'],
            'faqs' => $sections['FAQs'],
            'page' => 'docs'
        ]);
    }

    #[Route("/llms.txt", name: "llms_txt")]
    public function llmsTxt(string $docDir): Response
    {
        return $this->render('llms.txt.twig', [
            'sections' => $this->listSections($docDir),
        ], $this->createTextResponse('text/plain'));
    }

    #[Route("/llms-full.txt", name: "llms_full_txt")]
    public function llmsFullTxt(string $docDir): Response
    {
        $pages = [];
        foreach ($this->listSections($docDir) as $section) {
            foreach ($section as $data) {
                $url = $this->generateUrl('docs.view', ['page' => $data['page']], UrlGeneratorInterface::ABSOLUTE_URL);
                $pages[] = 'Source: '.$url."\n\n".$this->toMarkdown($data['page'], $data['contents']);
            }
        }

        return $this->createTextResponse('text/plain')->setContent(implode("\n\n---\n\n", $pages));
    }

    #[Route("/doc/{page}.md", name: "docs.markdown", requirements: ["page" => "[a-z0-9/\'-]+\.md"])]
    public function viewMarkdown(string $page, string $docDir): Response
    {
        $filename = $docDir.'/'.$page;

        if (!file_exists($filename)) {
            return $this->redirectMovedPage($page, 'docs.markdown');
        }

        $contents = file_get_contents($filename);
        assert(is_string($contents));
        $canonical = $this->generateUrl('docs.view', ['page' => $page], UrlGeneratorInterface::ABSOLUTE_URL);

        $response = $this->createTextResponse('text/markdown')->setContent($this->toMarkdown($page, $contents));
        $response->headers->set('Link', '<'.$canonical.'>; rel="canonical"');

        return $response;
    }

    #[Route("/doc/{page}", name: "docs.view", requirements: ["page" => "[a-z0-9/\'-]+\.md"])]
    public function viewPage(string $page, string $docDir, \Parsedown $parsedown): Response
    {
        $filename = $docDir.'/'.$page;

        if (!file_exists($filename)) {
            return $this->redirectMovedPage($page, 'docs.view');
        }

        $contents = file_get_contents($filename);
        /** @var string $content */
        $content = $parsedown->text($contents);
        $content = str_replace(['class="language-jsonc', 'class="language-json', 'class="language-js'], 'class="language-javascript', $content);
        $content = str_replace('class="language-sh', 'class="language-bash', $content);
        $content = str_replace('class="language-ini', 'class="language-clike', $content);

        $dom = new \DOMDocument();
        $dom->loadHtml('<?xml encoding="UTF-8">' . $content);
        $xpath = new \DOMXPath($dom);

        $toc = [];
        $ids = [];

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
        $legacyIds = [];
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
                    $toc[++$h1] = ['title' => $title, 'id' => $id, 'desc' => $desc];
                break;

                case 'h2':
                    $toc[$h1][++$h2] = ['title' => $title, 'id' => $id, 'desc' => $desc];
                break;

                case 'h3':
                    $toc[$h1][$h2][++$h3] = ['title' => $title, 'id' => $id, 'desc' => $desc];
                break;

                case 'h4':
                    $toc[$h1][$h2][$h3][++$h4] = ['title' => $title, 'id' => $id, 'desc' => $desc];
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

        return $this->render('doc.show.html.twig', [
            'doc' => $content,
            'file' => $page,
            'page' => $page == '00-intro.md' ? 'getting-started' : 'docs',
            'toc' => $toc,
            'title' => $firstTitle
        ]);
    }

    private function redirectMovedPage(string $page, string $route): RedirectResponse
    {
        if (!isset(self::MOVED_PAGES[$page])) {
            throw $this->createNotFoundException('Requested page was not found.');
        }

        [$target, $fragment] = self::MOVED_PAGES[$page];
        $params = ['page' => $target];
        if ($fragment !== null) {
            $params['_fragment'] = $fragment;
        }

        return $this->redirectToRoute($route, $params, 301);
    }

    private function createTextResponse(string $type): Response
    {
        $response = new Response('', 200, ['Content-Type' => $type.'; charset=UTF-8']);
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * @return array{Book: array<string, Page>, Articles: array<string, Page>, FAQs: array<string, Page>}
     */
    private function listSections(string $docDir): array
    {
        return [
            'Book' => $this->listPages($docDir),
            'Articles' => $this->listPages($docDir.'/articles', 'articles/'),
            'FAQs' => $this->listPages($docDir.'/faqs', 'faqs/'),
        ];
    }

    /**
     * @return array<string, Page> keyed by display name
     */
    private function listPages(string $dir, string $prefix = ''): array
    {
        $finder = new Finder();
        $finder->files()
            ->in($dir)
            ->sortByName()
            ->depth(0);

        $pages = [];

        foreach ($finder as $file) {
            $filename = basename($file->getPathname(), '.md');
            $page = $prefix.$filename.'.md';

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
            $pages[$displayName] = [
                'page' => $page,
                'metadata' => $metadata,
                'contents' => $contents,
            ];
        }

        return $pages;
    }

    /**
     * Returns the page source without its metadata comment, with doc links pointing to the absolute
     * markdown URLs so they still resolve when served outside /doc/ or concatenated in llms-full.txt
     */
    private function toMarkdown(string $page, string $contents): string
    {
        $contents = Preg::replace('{^<!--.*?-->\n+}s', '', $contents);

        // code fences and spans are skipped, then inline [text](target) and reference [n]: target
        // links are rewritten, except external, absolute and non-.md ones
        return Preg::replaceCallback(
            '{(?:^ *(`{3,}|~{3,})[\s\S]*?(?:^ *\1[`~]* *$|\z)|(`+)[\s\S]*?\2)(*SKIP)(*FAIL)'
            .'|(\]\(|^ {0,3}\[[^\]]+\]:[ \t]*)(?!\w+:|/)([a-z0-9/._\'-]+\.md(?=[)#\s]|$))?(#[^)\s]*)?}m',
            function (array $match) use ($page): string {
                $target = $match[4] ?? '';
                $fragment = $match[5] ?? '';
                if ($target === '' && $fragment === '') {
                    return (string) $match[0];
                }

                $resolved = $page;
                if ($target !== '') {
                    $dir = dirname($page);
                    $resolved = Path::canonicalize(($dir === '.' ? '' : $dir.'/').$target);
                }

                // also rejects targets climbing out of the doc dir, which canonicalize leaves as ../
                if (!Preg::isMatch('{^[a-z0-9/\'-]+\.md$}', $resolved)) {
                    return (string) $match[0];
                }

                return $match[3].$this->generateUrl('docs.markdown', ['page' => $resolved], UrlGeneratorInterface::ABSOLUTE_URL).$fragment;
            },
            $contents
        );
    }
}
