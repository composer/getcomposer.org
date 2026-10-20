<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;

class HomeControllerTest extends WebTestCase
{
    public function testIndex(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        self::assertSelectorTextContains('.latest', '2.10.3');
        self::assertCount(1, $crawler->filter('.preview'));
        self::assertSelectorTextContains('.preview', '2.11.0-RC1');
        self::assertSelectorTextContains('.preview', 'minor version');
    }

    public function testSponsor(): void
    {
        static::createClient()->request('GET', '/sponsor/');

        self::assertResponseIsSuccessful();
    }

    public function testDownload(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/download/', server: ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0)']);

        self::assertResponseIsSuccessful();
        $content = self::getContent($client);

        // all versions are listed newest first
        $positions = array_map(fn (string $v) => strpos($content, 'Changelog for version '.$v), ['2.11.0-RC1', '2.10.3', '2.2.30', '1.10.27']);
        self::assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        self::assertSame($sorted, $positions);

        self::assertStringContainsString(hash('sha256', "fake phar 2.10.3\n"), $content);
        self::assertStringContainsString('2.2.x', $crawler->text());
        self::assertStringContainsString('Critical security fixes only', $crawler->text());
        self::assertStringContainsString('End of life', $crawler->text());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideChannelPaths(): iterable
    {
        yield 'snapshot' => ['/composer.phar', 'fake snapshot phar'];
        yield 'stable' => ['/download/latest-stable/composer.phar', 'fake phar 2.10.3'];
        yield 'preview' => ['/download/latest-preview/composer.phar', 'fake phar 2.11.0-RC1'];
        yield '1.x' => ['/download/latest-1.x/composer.phar', 'fake phar 1.10.27'];
        yield '2.x' => ['/download/latest-2.x/composer.phar', 'fake phar 2.10.3'];
        yield '2.2 LTS' => ['/download/latest-2.2.x/composer.phar', 'fake phar 2.2.30'];
        yield 'stable bc' => ['/composer-stable.phar', 'fake phar 2.10.3'];
        yield 'preview bc' => ['/composer-preview.phar', 'fake phar 2.11.0-RC1'];
        yield '1.x bc' => ['/composer-1.phar', 'fake phar 1.10.27'];
        yield '2.x bc' => ['/composer-2.phar', 'fake phar 2.10.3'];
    }

    #[DataProvider('provideChannelPaths')]
    public function testDownloadChannel(string $path, string $expected): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=composer.phar');
        self::assertSame($expected."\n", self::getContent($client));
    }

    #[DataProvider('provideChannelPaths')]
    public function testDownloadChannelSha256(string $path, string $expected): void
    {
        $client = static::createClient();
        $client->request('GET', $path.'.sha256');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertSame(hash('sha256', $expected."\n"), self::getContent($client));
    }

    #[DataProvider('provideChannelPaths')]
    public function testDownloadChannelSha256Sum(string $path, string $expected): void
    {
        $client = static::createClient();
        $client->request('GET', $path.'.sha256sum');

        self::assertResponseIsSuccessful();
        self::assertSame(hash('sha256', $expected."\n").'  composer.phar'."\n", self::getContent($client));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideSignaturePaths(): iterable
    {
        yield 'stable' => ['/download/latest-stable/composer.phar.asc', '2.10.3'];
        yield 'preview' => ['/download/latest-preview/composer.phar.asc', '2.11.0-RC1'];
        yield '2.x' => ['/download/latest-2.x/composer.phar.asc', '2.10.3'];
        yield '2.2 LTS' => ['/download/latest-2.2.x/composer.phar.asc', '2.2.30'];
        yield 'specific' => ['/download/2.5.0/composer.phar.asc', '2.5.0'];
    }

    #[DataProvider('provideSignaturePaths')]
    public function testDownloadSignature(string $path, string $version): void
    {
        static::createClient()->request('GET', $path);

        self::assertResponseRedirects('https://github.com/composer/composer/releases/download/'.$version.'/composer.phar.asc');
    }

    public function testUnknownVersionDownload(): void
    {
        $client = static::createClient();
        $client->request('GET', '/download/9.9.9/composer.phar');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('Version Not Found', self::getContent($client));
    }

    public function testSchema(): void
    {
        $client = static::createClient();
        $client->request('GET', '/schema.json');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', '*');
        self::assertJsonStringEqualsJsonFile(__DIR__.'/../Fixtures/composer/res/composer-schema.json', self::getContent($client));
    }

    public function testShortcutRedirect(): void
    {
        static::createClient()->request('GET', '/xdebug');

        self::assertResponseStatusCodeSame(301);
        self::assertResponseRedirects('/doc/articles/troubleshooting.md#xdebug-impact-on-composer');
    }
}
