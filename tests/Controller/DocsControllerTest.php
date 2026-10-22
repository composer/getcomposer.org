<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;

class DocsControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/doc/');

        self::assertResponseIsSuccessful();
        $text = $crawler->text();
        // title from the first heading, after the metadata comment
        self::assertStringContainsString('Introduction', $text);
        self::assertStringContainsString('Basic usage', $text);
        self::assertStringContainsString('Authentication for privately hosted packages', $text);
        self::assertStringContainsString('Why can\'t Composer load repositories recursively?', $text);
        self::assertCount(1, $crawler->filter('a[href="/doc/articles/handling-private-packages.md"]'));
        self::assertCount(1, $crawler->filter('a[href="/doc/faqs/why-cant-composer-load-repositories-recursively.md"]'));
    }

    public function testViewPage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/doc/01-basic-usage.md');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'Basic usage');

        // GitHub-compatible ids, including duplicate suffixes
        self::assertCount(1, $crawler->filter('h2#installing-dependencies-how-to-install'));
        self::assertCount(1, $crawler->filter('h3#commit-your-lock-file'));
        self::assertCount(1, $crawler->filter('h3#commit-your-lock-file-1'));
        self::assertCount(1, $crawler->filter('h2#installing-dependencies-how-to-install > a.anchor[href="#installing-dependencies-how-to-install"]'));

        // legacy ids are kept as aliases, but only when they differ from the new ones
        self::assertCount(1, $crawler->filter('a#installing-dependencies'));
        self::assertCount(0, $crawler->filter('a#commit-your-lock-file'));

        self::assertCount(1, $crawler->filter('code.language-javascript'));
        self::assertCount(1, $crawler->filter('code.language-bash'));
        self::assertCount(1, $crawler->filter('p.prev-next'));
    }

    public function testIntroIsGettingStartedPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/doc/00-intro.md');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#dependency-management');
        self::assertSelectorExists('#system-requirements');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideMovedPages(): iterable
    {
        yield 'satis' => ['/doc/articles/handling-private-packages-with-satis.md', '/doc/articles/handling-private-packages.md'];
        yield 'http-basic' => ['/doc/articles/http-basic-authentication.md', '/doc/articles/authentication-for-private-packages.md#http-basic'];
        yield 'faq apostrophe' => ['/doc/faqs/why-can\'t-composer-load-repositories-recursively.md', '/doc/faqs/why-cant-composer-load-repositories-recursively.md'];
    }

    #[DataProvider('provideMovedPages')]
    public function testMovedPagesRedirect(string $path, string $target): void
    {
        static::createClient()->request('GET', $path);

        self::assertResponseStatusCodeSame(301);
        self::assertResponseRedirects($target);
    }

    public function testMissingPage(): void
    {
        static::createClient()->request('GET', '/doc/99-nope.md');

        self::assertResponseStatusCodeSame(404);
    }

    public function testViewPageLinksToMarkdown(): void
    {
        static::createClient()->request('GET', '/doc/01-basic-usage.md');

        self::assertSelectorExists('link[rel="alternate"][type="text/markdown"][href="/doc/01-basic-usage.md.md"]');
    }

    public function testMarkdown(): void
    {
        $client = static::createClient();
        $client->request('GET', '/doc/01-basic-usage.md.md');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/markdown; charset=UTF-8');
        self::assertResponseHeaderSame('Link', '<http://localhost/doc/01-basic-usage.md>; rel="canonical"');

        $content = self::getContent($client);
        // metadata comment is stripped and relative links are made absolute
        self::assertStringStartsWith("# Basic usage\n", $content);
        self::assertStringContainsString('[config docs](http://localhost/doc/06-config.md)', $content);
        self::assertStringContainsString('[Libraries](http://localhost/doc/02-libraries.md)', $content);
        self::assertStringContainsString('[README](https://example.org/README.md)', $content);
        self::assertStringContainsString('[1]: http://localhost/doc/articles/handling-private-packages.md#setup', $content);
        self::assertStringContainsString('[below](http://localhost/doc/01-basic-usage.md#details)', $content);
        self::assertStringContainsString('`^[a-z0-9]([_.-]?[a-z0-9]+)*$`', $content);
    }

    public function testMarkdownResolvesParentLinks(): void
    {
        $client = static::createClient();
        $client->request('GET', '/doc/faqs/why-cant-composer-load-repositories-recursively.md.md');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('[schema](http://localhost/doc/04-schema.md#repositories)', self::getContent($client));
    }

    public function testMissingMarkdown(): void
    {
        static::createClient()->request('GET', '/doc/99-nope.md.md');

        self::assertResponseStatusCodeSame(404);
    }

    public function testLlmsTxt(): void
    {
        $client = static::createClient();
        $client->request('GET', '/llms.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');

        $content = self::getContent($client);
        self::assertStringStartsWith("# Composer\n", $content);
        self::assertStringContainsString("## Book\n\n- [Introduction](http://localhost/doc/00-intro.md.md)\n- [Basic usage](http://localhost/doc/01-basic-usage.md.md): Learn the basics\n", $content);
        self::assertStringContainsString('- [Handling private packages](http://localhost/doc/articles/handling-private-packages.md.md)', $content);
        self::assertStringContainsString('- [Why can\'t Composer load repositories recursively?](http://localhost/doc/faqs/why-cant-composer-load-repositories-recursively.md.md)', $content);
        self::assertStringContainsString('http://localhost/llms-full.txt', $content);
    }

    public function testLlmsFullTxt(): void
    {
        $client = static::createClient();
        $client->request('GET', '/llms-full.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');

        $content = self::getContent($client);
        self::assertStringStartsWith("Source: http://localhost/doc/00-intro.md\n\n# Introduction\n", $content);
        self::assertStringContainsString("\n\n---\n\nSource: http://localhost/doc/01-basic-usage.md\n\n# Basic usage\n", $content);
        self::assertStringContainsString('Source: http://localhost/doc/faqs/why-cant-composer-load-repositories-recursively.md', $content);
        self::assertStringNotContainsString('tagline:', $content);
    }
}
