<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class RequestSubscriber implements EventSubscriberInterface
{
    public function __construct(private string $environment)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // return the subscribed events, their methods and priorities
        return [
            KernelEvents::REQUEST => [
                ['forceTls', 1000],
            ],
            KernelEvents::RESPONSE => [
                ['setHsts', 0],
            ],
        ];
    }

    public function forceTls(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->environment === 'dev') {
            return;
        }

        $req = $event->getRequest();
        // skip SSL & non-GET/HEAD requests
        if ($req->isSecure() || !$req->isMethodSafe()) {
            return;
        }

        $event->setResponse(new RedirectResponse('https://'.substr($req->getUri(), 7)));
    }

    public function setHsts(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $resp = $event->getResponse();

        if (!$resp->headers->has('Strict-Transport-Security')) {
            $resp->headers->set('Strict-Transport-Security', 'max-age=31104000');
        }
    }
}
