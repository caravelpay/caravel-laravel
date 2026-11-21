<?php

declare(strict_types=1);

namespace CaravelPay\Laravel\Events;

/**
 * Less than the amount arrived (paidAmount()). Hold the order and contact the buyer; they can top up before the checkout expires.
 */
final class CheckoutUnderpaid extends CheckoutEvent
{
}
