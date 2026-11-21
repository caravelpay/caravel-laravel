<?php

declare(strict_types=1);

namespace CaravelPay\Laravel;

use Illuminate\Contracts\Cache\Repository;

/** EventStore on a Laravel cache store (Cache::add is atomic on every driver Laravel ships). */
final class CacheEventStore implements EventStore
{
    public function __construct(private Repository $cache, private int $seconds = 172800)
    {
    }

    public function claim(string $eventId): bool
    {
        return (bool) $this->cache->add('caravel:event:' . $eventId, 1, $this->seconds);
    }

    public function release(string $eventId): void
    {
        $this->cache->forget('caravel:event:' . $eventId);
    }
}
