<?php

declare(strict_types=1);

namespace CaravelPay\Laravel\Events;

/**
 * The full amount arrived and both payout transfers confirmed. Fulfil the order (it may follow CheckoutExpired when a payment sent before expiry confirmed late: fulfil it then too).
 */
final class CheckoutPaid extends CheckoutEvent
{
}
