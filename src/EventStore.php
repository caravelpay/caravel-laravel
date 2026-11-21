<?php

declare(strict_types=1);

namespace CaravelPay\Laravel;

/**
 * Processed webhook event ids. claim() is atomic: only one request processes an event; a failed
 * attempt releases it so Caravel Pay's retry is processed.
 */
interface EventStore
{
    /** True when this request may process the event (it wasn't claimed yet). */
    public function claim(string $eventId): bool;

    public function release(string $eventId): void;
}
