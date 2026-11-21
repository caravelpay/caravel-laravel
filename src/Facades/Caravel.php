<?php

declare(strict_types=1);

namespace CaravelPay\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array createCheckout(array $params, array $options = [])
 * @method static array getCheckout(string $checkoutId, ?string $merchantId = null)
 * @method static array listCheckouts(array $params = [])
 * @method static array getMerchant()
 * @method static \CaravelPay\Client client(?string $merchantId = null)
 * @method static string amount($total)
 *
 * @see \CaravelPay\Laravel\Caravel
 */
class Caravel extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'caravel';
    }
}
