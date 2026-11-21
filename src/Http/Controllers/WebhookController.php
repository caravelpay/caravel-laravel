<?php

declare(strict_types=1);

namespace CaravelPay\Laravel\Http\Controllers;

use CaravelPay\Event;
use CaravelPay\Laravel\WebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST caravel/webhook (behind the caravel.webhook middleware): re-fetch, then dispatch the event. */
class WebhookController
{
    public function __construct(private WebhookHandler $handler)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $event = $request->attributes->get('caravel_event');
        if (!$event instanceof Event) {
            return new JsonResponse(['error' => 'unverified'], 401, ['Cache-Control' => 'no-store']);
        }
        $result = $this->handler->handle($event);
        return new JsonResponse($result['body'], $result['status'], ['Cache-Control' => 'no-store']);
    }
}
