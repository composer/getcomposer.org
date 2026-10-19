<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;

class DocsControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createHttpsClient();
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
        $client = static::createHttpsClient();
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
        $client = static::createHttpsClient();
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
        static::createHttpsClient()->request('GET', $path);

        self::assertResponseStatusCodeSame(301);
        self::assertResponseRedirects($target);
    }

    public function testMissingPage(): void
    {
        static::createHttpsClient()->request('GET', '/doc/99-nope.md');

        self::assertResponseStatusCodeSame(404);
    }
}
