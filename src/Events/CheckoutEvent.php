<?php

declare(strict_types=1);

namespace CaravelPay\Laravel\Events;

/**
 * Base of the four events. $checkout is the checkout re-read with signed GET /v1/checkouts/{id}
 * after the webhook was verified, so its status is current and can be trusted. Listeners should be
 * idempotent (match on checkoutId() / orderId()): an event can be dispatched again after a failure.
 */
abstract class CheckoutEvent
{
    /**
     * @param array<string, mixed> $checkout   The re-fetched checkout object.
     * @param string               $eventId    Webhook event id (evt_…).
     * @param string               $eventType  Webhook type (checkout.paid…).
     * @param string               $merchantId Merchant that created the checkout.
     */
    public function __construct(
        public readonly array $checkout,
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly string $merchantId
    ) {
    }

    public function checkoutId(): string
    {
        return (string) $this->checkout['id'];
    }

    /** Your order_id as sent when the checkout was created. */
    public function orderId(): string
    {
        return (string) ($this->checkout['order_id'] ?? '');
    }

    public function status(): string
    {
        return (string) $this->checkout['status'];
    }

    public function amount(): string
    {
        return (string) $this->checkout['amount'];
    }

    public function paidAmount(): string
    {
        return (string) ($this->checkout['paid_amount'] ?? '0.00');
    }

    /** The payout transaction hash once paid, else null. */
    public function txHash(): ?string
    {
        return isset($this->checkout['payout']['tx_hash']) ? (string) $this->checkout['payout']['tx_hash'] : null;
    }

    /** @return array<int, array{amount:string, asset:string, tx_hash:string}> Every payout transfer. */
    public function payouts(): array
    {
        if (isset($this->checkout['payouts']) && is_array($this->checkout['payouts'])) {
            return $this->checkout['payouts'];
        }
        return isset($this->checkout['payout']) ? [$this->checkout['payout']] : [];
    }
}
