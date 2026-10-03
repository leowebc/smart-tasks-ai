<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_ORIGINS = [
        'http://localhost:4200',
        'http://127.0.0.1:4200',
        'https://smart-tasks-ai.onrender.com',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 250],
            KernelEvents::RESPONSE => ['onResponse', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isMethod('OPTIONS') || !$this->isAllowed($request)) {
            return;
        }

        $event->setResponse($this->apply($request, new Response('', Response::HTTP_NO_CONTENT)));
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->isAllowed($event->getRequest())) {
            return;
        }

        $this->apply($event->getRequest(), $event->getResponse());
    }

    private function isAllowed(Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        return is_string($origin) && in_array($origin, self::ALLOWED_ORIGINS, true);
    }

    private function apply(Request $request, Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', (string) $request->headers->get('Origin'));
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $response->headers->set('Access-Control-Max-Age', '3600');

        return $response;
    }
}
