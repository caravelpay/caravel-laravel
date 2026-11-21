<?php

declare(strict_types=1);

namespace CaravelPay\Laravel\Events;

/**
 * No payment within 60 minutes. Cancel the order or release stock. Rarely a CheckoutPaid follows.
 */
final class CheckoutExpired extends CheckoutEvent
{
}
