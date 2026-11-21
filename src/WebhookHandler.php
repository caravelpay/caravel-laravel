<?php

declare(strict_types=1);

namespace CaravelPay\Laravel;

use CaravelPay\Event;
use CaravelPay\Exception\ApiException;
use CaravelPay\Exception\SignatureVerificationException;
use CaravelPay\Laravel\Events\CheckoutExpired;
use CaravelPay\Laravel\Events\CheckoutPaid;
use CaravelPay\Laravel\Events\CheckoutReview;
use CaravelPay\Laravel\Events\CheckoutUnderpaid;
use CaravelPay\Webhook;

/**
 * Framework-free webhook processing used by the middleware and controller:
 *
 * 1. verify(): signature (Caravel-* headers, path + query of the callback URL, 300 s window) with the
 *    current or a replaced secret, via the Caravel Pay PHP SDK.
 * 2. handle(): claim the event id (duplicates answer 200), re-read the checkout with signed
 *    GET /v1/checkouts/{id} as the merchant that created it, then dispatch the event for its CURRENT
 *    status. The webhook body is never trusted for the status. Failures release the claim and answer
 *    503, so Caravel Pay retries (for up to 24 hours).
 */
final class WebhookHandler
{
    private const EVENTS = [
        'paid' => CheckoutPaid::class,
        'underpaid' => CheckoutUnderpaid::class,
        'expired' => CheckoutExpired::class,
        'review' => CheckoutReview::class,
    ];

    /** @var callable */
    private $dispatch;
    /** @var callable|null */
    private $clock;

    /**
     * @param callable      $dispatch Receives the event object (Laravel's event dispatcher).
     * @param callable|null $clock    Returns unix seconds (tests).
     */
    public function __construct(private Caravel $caravel, callable $dispatch, private EventStore $events, ?callable $clock = null)
    {
        $this->dispatch = $dispatch;
        $this->clock = $clock;
    }

    /**
     * @param array<string, mixed> $headers      Request headers (any case or $_SERVER style).
     * @param string[]             $callbackUrls URLs whose path + query may be signed.
     *
     * @throws SignatureVerificationException
     * @throws \RuntimeException When no credentials are configured.
     */
    public function verify(string $body, array $headers, array $callbackUrls): Event
    {
        $secrets = $this->caravel->credentials()->secrets();
        if (!$secrets) {
            throw new \RuntimeException('not_connected');
        }
        $now = $this->clock ? (int) call_user_func($this->clock) : time();
        return Webhook::verify($body, $headers, $secrets, $callbackUrls, ['now' => $now]);
    }

    /** @return array{status:int, body:array<string, mixed>} */
    public function handle(Event $event): array
    {
        if (!$event->isKnownType()) {
            return ['status' => 200, 'body' => ['received' => true, 'ignored' => true]];
        }
        if (!$this->events->claim($event->id)) {
            return ['status' => 200, 'body' => ['received' => true, 'duplicate' => true]];
        }
        try {
            $checkout = $this->caravel->getCheckout($event->checkoutId(), $event->merchantId);
        } catch (ApiException $e) {
            if (404 === $e->getHttpStatus()) {
                return ['status' => 200, 'body' => ['received' => true, 'ignored' => true]];
            }
            $this->events->release($event->id);
            return ['status' => 503, 'body' => ['error' => 'verification_pending']];
        } catch (\Throwable $e) {
            $this->events->release($event->id);
            return ['status' => 503, 'body' => ['error' => 'verification_pending']];
        }
        $status = (string) $checkout['status'];
        if (!isset(self::EVENTS[$status])) {
            return ['status' => 200, 'body' => ['received' => true, 'result' => $status]];
        }
        $class = self::EVENTS[$status];
        try {
            call_user_func($this->dispatch, new $class($checkout, $event->id, $event->type, $event->merchantId));
        } catch (\Throwable $e) {
            $this->events->release($event->id);
            return ['status' => 503, 'body' => ['error' => 'processing_failed']];
        }
        return ['status' => 200, 'body' => ['received' => true, 'result' => $status]];
    }
}
