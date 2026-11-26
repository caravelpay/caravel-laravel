<?php

declare(strict_types=1);

namespace CaravelPay\Laravel;

use CaravelPay\Amount;
use CaravelPay\Client;
use CaravelPay\Currency;
use CaravelPay\FeeMode;
use CaravelPay\Http\TransportInterface;

/**
 * The "caravel" service (and Caravel facade): SDK clients for the configured credentials.
 *
 *   Caravel::createCheckout(['order_id' => '1042', 'amount' => Caravel::amount($order->total), ...]);
 *   Caravel::createCheckout(['order_id' => '1043', 'amount' => Caravel::amount($order->total, 'EUR'), 'currency' => 'EUR', ...]);
 *   Caravel::getCheckout('chk_…');          // signed GET: the truth about a checkout
 *   Caravel::client('mch_old…')->getCheckout($id);   // with replaced credentials
 */
class Caravel
{
    public const VERSION = '1.2.2';

    private Credentials $credentials;
    /** @var array<string, mixed> */
    private array $options;
    /** @var array<string, Client> */
    private array $clients = [];
    /** Who pays the fees by default for this app's checkouts ("caravel.fee_mode"). */
    private string $feeMode;
    /** Margin added to checkouts that aren't priced in US dollars, in basis points ("caravel.fx_buffer", a percentage). */
    private int $fxBufferBps;

    /**
     * @param array<string, mixed>     $config    The "caravel" config.
     * @param TransportInterface|null $transport Custom HTTP transport (tests).
     * @param callable|null           $clock     Clock (tests).
     */
    public function __construct(array $config, ?TransportInterface $transport = null, ?callable $clock = null)
    {
        $this->credentials = Credentials::fromConfig($config);
        $this->feeMode = FeeMode::normalize($config['fee_mode'] ?? null);
        $this->fxBufferBps = Currency::bufferBps($config['fx_buffer'] ?? 0);
        $this->options = array_filter([
            'api_base' => trim((string) ($config['api_base'] ?? '')) ?: null,
            'timeout' => isset($config['timeout']) ? (int) $config['timeout'] : null,
            'user_agent' => 'CaravelPay-Laravel/' . self::VERSION,
            'transport' => $transport,
            'clock' => $clock,
        ], static fn ($value) => null !== $value);
    }

    public function credentials(): Credentials
    {
        return $this->credentials;
    }

    /** @return array<string, mixed> SDK client options (without credentials). */
    public function options(): array
    {
        return $this->options;
    }

    /** An SDK client for the current merchant, or for a replaced one that is still configured. */
    public function client(?string $merchantId = null): Client
    {
        $merchantId = $merchantId ?? $this->credentials->current();
        $secret = '' !== $merchantId ? $this->credentials->secretFor($merchantId) : null;
        if (null === $secret) {
            throw new \RuntimeException('Caravel Pay is not configured for merchant "' . $merchantId . '". Set CARAVEL_MERCHANT_ID and CARAVEL_SECRET (php artisan caravel:connect).');
        }
        return $this->clients[$merchantId] ??= new Client($merchantId, $secret, $this->options);
    }

    /** Who pays the fees for checkouts that don't say so themselves: "seller" (the default), "split" or "buyer". */
    public function feeMode(): string
    {
        return $this->feeMode;
    }

    /** The configured exchange-rate buffer in basis points (CARAVEL_FX_BUFFER is a percentage: "1.5" is 150). 0 by default. */
    public function fxBufferBps(): int
    {
        return $this->fxBufferBps;
    }

    /**
     * Create a checkout. Without a fee_mode of its own it uses the configured one (CARAVEL_FEE_MODE);
     * the default, "seller", adds nothing to the request.
     *
     * Send `amount` and `currency` as your app has them. A checkout that isn't priced in US dollars is
     * converted once, when it is created: the answer keeps your amount and currency and adds `usd_amount`
     * and `fx_rate`. A configured buffer (CARAVEL_FX_BUFFER) is added to those checkouts unless the
     * checkout sets `fx_buffer_bps` itself; a US dollar checkout sends exactly what it sent before.
     *
     * @return array<string, mixed>
     */
    public function createCheckout(array $params, array $options = []): array
    {
        if (!array_key_exists('fee_mode', $params)) {
            $params = FeeMode::apply($params, $this->feeMode);
        }
        $currency = $params['currency'] ?? Currency::USD;
        if ($this->fxBufferBps > 0 && Currency::USD !== $currency && !array_key_exists('fx_buffer_bps', $params)) {
            $params['fx_buffer_bps'] = $this->fxBufferBps;
        }
        return $this->client()->createCheckout($params, $options);
    }

    /**
     * The current rate for a currency, and with an amount what it is in US dollars right now. For a line
     * shown to the buyer before the checkout exists; the checkout's own `usd_amount` is what is charged.
     * Uses the configured buffer unless one is given.
     *
     * @return array<string, mixed>
     */
    public function fxQuote(string $currency, ?string $amount = null, ?int $bufferBps = null): array
    {
        return $this->client()->fxQuote($currency, $amount, $bufferBps ?? (null === $amount ? 0 : $this->fxBufferBps));
    }

    /**
     * One line for buyers before they are redirected: "You'll be charged in US dollars: about $48.60. …".
     * '' for a US dollar total. Never throws: without a rate at hand the line leaves the estimate out.
     *
     * @param string|int|float $total Total in your currency.
     */
    public function fxNotice($total, string $currency): string
    {
        if (Currency::USD === $currency || !Currency::isSupported($currency)) {
            return '';
        }
        $estimate = null;
        try {
            $quote = $this->fxQuote($currency, Currency::format($total, $currency));
            $estimate = isset($quote['usd_amount']) && is_string($quote['usd_amount']) ? $quote['usd_amount'] : null;
        } catch (\Throwable $e) {
            $estimate = null;
        }
        return Currency::buyerNotice($currency, $estimate);
    }

    /** @return array<string, mixed> */
    public function getCheckout(string $checkoutId, ?string $merchantId = null): array
    {
        return $this->client($merchantId)->getCheckout($checkoutId);
    }

    /** @return array<string, mixed> */
    public function listCheckouts(array $params = []): array
    {
        return $this->client()->listCheckouts($params);
    }

    /** @return array<string, mixed> */
    public function getMerchant(): array
    {
        return $this->client()->getMerchant();
    }

    /**
     * A platform total as the API's string in the currency's own decimals: "49.9" → "49.90", and for a
     * currency without decimals 5000 or "5000.00" → "5000" (JPY, KRW).
     */
    public static function amount($total, string $currency = Currency::USD): string
    {
        return Currency::USD === $currency ? Amount::normalize($total) : Currency::format($total, $currency);
    }

    /**
     * The webhook URLs the signature may be over: the package route plus any configured extras.
     *
     * @param array<string, mixed> $config The "caravel" config.
     * @param callable             $url    url() helper.
     * @return string[]
     */
    public static function callbackUrls(array $config, callable $url): array
    {
        $urls = [(string) $url((string) ($config['webhook']['path'] ?? 'caravel/webhook'))];
        foreach ((array) ($config['webhook']['urls'] ?? []) as $extra) {
            if (is_string($extra) && '' !== trim($extra)) {
                $urls[] = trim($extra);
            }
        }
        return array_values(array_unique($urls));
    }
}
