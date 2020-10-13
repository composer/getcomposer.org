<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class RequestSubscriber implements EventSubscriberInterface
{
    private $environment;
    
    public function __construct(string $environment)
    {
        $this->environment = $environment;
    }

    public static function getSubscribedEvents()
    {
        // return the subscribed events, their methods and priorities
        return [
            KernelEvents::REQUEST => [
                ['setupTideways', 10000],
                ['forceTls', 1000],
            ],
            KernelEvents::RESPONSE => [
                ['setHsts', 1000],
            ],
        ];
    }

    public function setupTideways(RequestEvent $event)
    {
        if (!$event->isMasterRequest() || !class_exists('Tideways\Profiler')) {
            return;
        }
    
        $req = $event->getRequest();
        $actionName = $req->get('_route');
        if (strpos($actionName, '__') === 0) {
            $actionName = $req->get('_controller');
        }
        \Tideways\Profiler::setTransactionName($req->getMethod().' '.$actionName);
    }

    public function forceTls(RequestEvent $event)
    {
        if (!$event->isMasterRequest() || $this->environment === 'dev') {
            return;
        }
    
        $req = $event->getRequest();
        // skip SSL & non-GET/HEAD requests
        if (strtolower($req->server->get('HTTPS')) == 'on' || strtolower($req->headers->get('X_FORWARDED_PROTO')) == 'https' || !$req->isMethodSafe()) {
            return;
        }

        $event->setResponse(new RedirectResponse('https://'.substr($req->getUri(), 7)));
    }
    
    public function setHsts(ResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
    
        $resp = $event->getResponse();

        if (!$resp->headers->has('Strict-Transport-Security')) {
            $resp->headers->set('Strict-Transport-Security', 'max-age=31104000');
        }
    }
}