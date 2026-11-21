<?php

declare(strict_types=1);

namespace CaravelPay\Laravel\Events;

/**
 * A payment needs a manual look (for example one sent after expiry). Hold the order; a follow-up event comes if it resolves.
 */
final class CheckoutReview extends CheckoutEvent
{
}
