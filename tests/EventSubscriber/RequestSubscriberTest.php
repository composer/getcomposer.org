<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\RequestSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RequestSubscriberTest extends TestCase
{
    public function testForceTlsRedirectsInsecureGetRequests(): void
    {
        $event = $this->createRequestEvent(Request::create('http://getcomposer.org/download/?foo=bar'));

        (new RequestSubscriber('prod'))->forceTls($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('https://getcomposer.org/download/?foo=bar', $response->getTargetUrl());
    }

    /**
     * @return iterable<string, array{string, Request, int}>
     */
    public static function provideRequestsNotRedirected(): iterable
    {
        yield 'dev env' => ['dev', Request::create('http://getcomposer.org/'), HttpKernelInterface::MAIN_REQUEST];
        yield 'already https' => ['prod', Request::create('https://getcomposer.org/'), HttpKernelInterface::MAIN_REQUEST];
        yield 'unsafe method' => ['prod', Request::create('http://getcomposer.org/', 'POST'), HttpKernelInterface::MAIN_REQUEST];
        yield 'sub request' => ['prod', Request::create('http://getcomposer.org/'), HttpKernelInterface::SUB_REQUEST];
    }

    #[DataProvider('provideRequestsNotRedirected')]
    public function testForceTlsSkips(string $env, Request $request, int $type): void
    {
        $event = $this->createRequestEvent($request, $type);

        (new RequestSubscriber($env))->forceTls($event);

        self::assertNull($event->getResponse());
    }

    public function testSetHsts(): void
    {
        $event = $this->createResponseEvent(new Response());

        (new RequestSubscriber('prod'))->setHsts($event);

        self::assertSame('max-age=63072000; includeSubDomains; preload', $event->getResponse()->headers->get('Strict-Transport-Security'));
    }

    public function testSetHstsKeepsExistingHeader(): void
    {
        $event = $this->createResponseEvent(new Response('', 200, ['Strict-Transport-Security' => 'max-age=0']));

        (new RequestSubscriber('prod'))->setHsts($event);

        self::assertSame('max-age=0', $event->getResponse()->headers->get('Strict-Transport-Security'));
    }

    public function testSetHstsSkipsSubRequests(): void
    {
        $event = $this->createResponseEvent(new Response(), HttpKernelInterface::SUB_REQUEST);

        (new RequestSubscriber('prod'))->setHsts($event);

        self::assertFalse($event->getResponse()->headers->has('Strict-Transport-Security'));
    }

    private function createRequestEvent(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
    }

    private function createResponseEvent(Response $response, int $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), Request::create('https://getcomposer.org/'), $type, $response);
    }
}
