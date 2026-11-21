<?php

declare(strict_types=1);

namespace CaravelPay\Laravel\Http\Middleware;

use CaravelPay\Exception\SignatureVerificationException;
use CaravelPay\Laravel\Caravel;
use CaravelPay\Laravel\WebhookHandler;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "caravel.webhook" middleware: rejects requests that aren't signed by one of your Caravel Pay
 * merchants (401), puts the verified CaravelPay\Event on the request as "caravel_event".
 * Use it on your own webhook route too: Route::post('/hooks/caravel', ...)->middleware('caravel.webhook').
 */
class VerifyCaravelSignature
{
    public function __construct(private WebhookHandler $handler)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $urls = Caravel::callbackUrls((array) config('caravel', []), static fn ($path) => url($path));
        // The URL this request reached counts too, so a custom route signs with its own path.
        $urls[] = $request->fullUrl();
        try {
            $event = $this->handler->verify((string) $request->getContent(), $request->headers->all(), $urls);
        } catch (SignatureVerificationException $e) {
            return new JsonResponse(['error' => $e->getReason()], $e->getHttpStatus(), ['Cache-Control' => 'no-store']);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => 'not_connected'], 503, ['Cache-Control' => 'no-store']);
        }
        $request->attributes->set('caravel_event', $event);
        return $next($request);
    }
}
