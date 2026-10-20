<?php

namespace App\Tests\Controller;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

class ChangelogControllerTest extends WebTestCase
{
    public function testChangelogFromFile(): void
    {
        $client = static::createClient();
        $this->mockGitHub(new MockResponse('', ['http_code' => 500]));
        $crawler = $client->request('GET', '/changelog/2.10.3');

        self::assertResponseIsSuccessful();
        $content = self::getContent($client);
        self::assertStringContainsString('Fixed a crash in the installer', $content);
        self::assertStringNotContainsString('Fixed something else', $content);
        self::assertStringContainsString('2026-10-01', $crawler->text());
        self::assertStringContainsString('href="../doc/06-config.md"', $content);
        self::assertStringContainsString('href="../upgrade/UPGRADE-2.0.md"', $content);
    }

    public function testChangelogFallsBackToGitHubRelease(): void
    {
        $client = static::createClient();
        $http = $this->mockGitHub(new JsonMockResponse([
            'body' => "* Released from GitHub\n",
            'created_at' => '2026-01-15T10:00:00Z',
        ]));
        $crawler = $client->request('GET', '/changelog/2.9.0');

        self::assertResponseIsSuccessful();
        self::assertSame(1, $http->getRequestsCount());
        self::assertStringContainsString('Released from GitHub', $crawler->text());
        self::assertStringContainsString('2026-01-15', $crawler->text());
    }

    public function testChangelogRedirectsToGitHubWhenRateLimited(): void
    {
        $client = static::createClient();
        $this->mockGitHub(new MockResponse('', ['http_code' => 403, 'response_headers' => ['x-ratelimit-remaining' => '0']]));
        $client->request('GET', '/changelog/2.9.0');

        self::assertResponseRedirects('https://github.com/composer/composer/releases/tag/2.9.0');
    }

    public function testChangelogNotFound(): void
    {
        $client = static::createClient();
        $this->mockGitHub(new MockResponse('', ['http_code' => 404, 'response_headers' => ['x-ratelimit-remaining' => '42']]));
        $client->request('GET', '/changelog/0.0.1');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUpgrade(): void
    {
        $client = static::createClient();
        $client->request('GET', '/upgrade/UPGRADE-2.0.md');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('href="../doc/03-cli.md"', self::getContent($client));
    }

    public function testUpgradeRejectsOtherFiles(): void
    {
        $client = static::createClient();
        $client->request('GET', '/upgrade/CHANGELOG.md');

        self::assertResponseStatusCodeSame(404);
    }

    private function mockGitHub(MockResponse $response): MockHttpClient
    {
        $http = new MockHttpClient($response);
        static::getContainer()->set('http_client', $http);

        return $http;
    }
}
