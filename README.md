# Caravel Pay for Laravel

- Docs: <https://caravelpay.app/docs/integrations#laravel>
- Download and checksums: <https://caravelpay.app/plugins/#laravel>
- Status: beta. New: tell us if anything looks off.
- Questions: [hello@caravelpay.app](mailto:hello@caravelpay.app)

Hosted card, Apple Pay and Google Pay checkouts for Laravel apps, paid out in USDC on Polygon (or in
the token the buyer paid with), minus the 2% Caravel fee. The package wraps the Caravel Pay PHP SDK and adds:

- `config/caravel.php` (credentials from `.env`, previous credentials for in-flight checkouts);
- a `Caravel` facade (`createCheckout`, `getCheckout`, `listCheckouts`, `getMerchant`, `amount`);
- `POST /caravel/webhook` with the `caravel.webhook` middleware, which verifies the signature;
- events for the checkout's **re-fetched** status: `CheckoutPaid`, `CheckoutUnderpaid`,
  `CheckoutExpired`, `CheckoutReview`;
- `php artisan caravel:connect`, which gets your merchant id and secret.

Laravel 10, 11 or 12 on PHP 8.1+ with `curl` and `json`.

## Install

With Composer:

```sh
composer require caravelpay/caravel-laravel
php artisan vendor:publish --tag=caravel-config   # optional: copy config/caravel.php
```

Or install the release zip as a Composer artifact:

```sh
mkdir -p artifacts && cp caravel-pay-laravel.zip artifacts/
composer config repositories.caravelpay artifact ./artifacts
composer require caravelpay/caravel-laravel:1.2.1
```

Package discovery registers `CaravelPay\Laravel\CaravelServiceProvider` and the `Caravel` alias.

## Get credentials

```sh
php artisan caravel:connect 0xYourPolygonWallet --site=https://shop.example --email=you@shop.example
```

It calls `POST /v1/connect` and prints the lines for `.env`. The secret is shown only this once:

```dotenv
CARAVEL_MERCHANT_ID=mch_…
CARAVEL_SECRET=…
```

`--site` defaults to `APP_URL` and must be the HTTPS address your return, cancel and callback URLs use.
Every connect creates a **new** merchant. To change the payout wallet or rotate the secret, run it
again with `--force`: it also prints `CARAVEL_PREVIOUS_CREDENTIALS=mch_old:secret`, and you keep that
line until checkouts created with the old credentials have finished, because their webhooks stay
signed with the old secret.

## Create a checkout

```php
use CaravelPay\Laravel\Facades\Caravel;

$checkout = Caravel::createCheckout([
    'order_id' => (string) $order->id,           // unique per merchant; same id + details = same checkout
    'amount' => Caravel::amount($order->total),  // "136.41": exactly two decimals, USD
    'currency' => 'USD',
    'customer_email' => $order->email,           // optional
    'return_url' => route('orders.thanks', $order),
    'cancel_url' => route('cart'),
    'callback_url' => route('caravel.webhook'),
]);
$order->update(['caravel_checkout_id' => $checkout['id']]);
return redirect()->away($checkout['checkout_url']);
```

### Other currencies

Send `amount` and `currency` as your app has them. EUR, GBP, CAD, AUD, NZD, SEK, NOK, DKK, CHF, PLN, CZK,
HUF, RON, HKD, SGD, MXN, BRL, ZAR, ILS, INR, THB, MYR, PHP, TRY, JPY and KRW are converted to US dollars
once, when the checkout is created, and that amount is fixed for the checkout. Buyers are charged in US
dollars and you are paid in USDC. The checkout keeps your amount and currency and adds `usd_amount` and
`fx_rate`.

```php
$checkout = Caravel::createCheckout([
    'order_id' => (string) $order->id,
    'amount' => Caravel::amount($order->total, 'EUR'),  // "45.00"; Caravel::amount(5000, 'JPY') is "5000"
    'currency' => 'EUR',
    // ... the same URLs as above
]);
$checkout['usd_amount'];  // "48.60"
$checkout['fx_rate'];     // "1.08": US dollars for one euro, fixed for this checkout

// Before the checkout exists, a line for your payment page:
Caravel::fxNotice($order->total, 'EUR');  // "You'll be charged in US dollars: about $48.60. You see the exact amount before you pay."
```

`CARAVEL_FX_BUFFER` (a percentage, 0 to 5, default 0) adds a margin to converted checkouts, to cover
exchange-rate movement until you convert your USDC. Fulfil orders from the checkout's `status`; never
compare `usd_amount` with a total in your own currency. When no confirmed exchange rate is available the
API answers `503 service_unavailable`: offer another payment method or try again in a minute.

A checkout expires after 60 minutes. To let the buyer try again, use a new `order_id` such as
`1042-2` (keep the old checkout id too: a late payment can still confirm it).

## Handle the events

The webhook route verifies the signature (401 otherwise), answers duplicates with 200, re-reads the
checkout with the signed API and dispatches the event for its **current** status, so the webhook body
is never trusted. If your listener throws, the route answers 503 and Caravel Pay retries (for up to
24 hours), so make listeners idempotent:

```php
use CaravelPay\Laravel\Events\{CheckoutPaid, CheckoutUnderpaid, CheckoutExpired, CheckoutReview};

Event::listen(function (CheckoutPaid $event) {
    $order = Order::where('caravel_checkout_id', $event->checkoutId())->firstOrFail();
    $order->markPaid($event->txHash());          // also after CheckoutExpired: a late payment
});
Event::listen(fn (CheckoutUnderpaid $e) => Order::byCheckout($e->checkoutId())->hold('underpaid: ' . $e->paidAmount()));
Event::listen(fn (CheckoutReview $e) => Order::byCheckout($e->checkoutId())->hold('review'));
Event::listen(fn (CheckoutExpired $e) => Order::byCheckout($e->checkoutId())->cancelIfUnpaid());
```

| Event | Checkout status | Suggested order change |
|---|---|---|
| `CheckoutPaid` | `paid` | mark paid with `txHash()`; fulfil |
| `CheckoutUnderpaid` | `underpaid` | hold; `paidAmount()` shows what arrived |
| `CheckoutReview` | `review` | hold; a follow-up event comes if it resolves |
| `CheckoutExpired` | `expired` | cancel if still unpaid (a later `CheckoutPaid` still completes it) |

Each event has `$checkout` (the re-fetched object), `$eventId`, `$eventType`, `$merchantId` and the
helpers `checkoutId()`, `orderId()`, `status()`, `amount()`, `paidAmount()`, `txHash()`, `payouts()`.

## Configuration

| `.env` | Default | |
|---|---|---|
| `CARAVEL_MERCHANT_ID`, `CARAVEL_SECRET` | none | from `caravel:connect` |
| `CARAVEL_PREVIOUS_CREDENTIALS` | empty | `mch_A:secretA,mch_B:secretB` |
| `CARAVEL_WEBHOOK_PATH` | `caravel/webhook` | the package route |
| `CARAVEL_WEBHOOK_ENABLED` | `true` | `false` to use your own route with `->middleware('caravel.webhook')` |
| `CARAVEL_WEBHOOK_URLS` | empty | extra callback URLs you send (comma-separated; only path + query are signed) |
| `CARAVEL_WEBHOOK_CACHE_STORE` | default store | where processed event ids are kept (2 days) |
| `CARAVEL_FEE_MODE` | `seller` | who pays the fees when a checkout doesn't set `fee_mode`: `seller`, `split` (the card provider's fee is added to the buyer's total) or `buyer` (the 2% Caravel fee is added too). Card surcharges are restricted in some countries: check your local rules first |
| `CARAVEL_API_BASE` | production | staging only |

The route is registered outside the `web` middleware group, so no CSRF token applies; the HMAC
signature authenticates it. Behind a proxy that rewrites paths, list the public URL in
`CARAVEL_WEBHOOK_URLS`.

## Switching from PayGate

PayGate's Laravel package (`paygate-to/paygate-laravel`) verifies an RSA-signed `GET` callback with a
`paygate.callback` middleware and an order token. With Caravel Pay:

- the wallet goes into `caravel:connect` once, not into every payment URL;
- the callback is a signed `POST` (HMAC-SHA256 over timestamp, method, path + query and body) and the
  amount is fixed on Caravel Pay's side, so there is no order token or fees tolerance to check;
- the `caravel.webhook` middleware takes the place of `paygate.callback`, and the four events replace
  reading `value_coin` from the callback;
- both packages can be installed side by side while open PayGate payments finish.

## Download

- [Latest release](https://github.com/caravelpay/caravel-laravel/releases/latest): `caravel-pay-laravel.zip`, with `SHA256SUMS`.
- The same file is at <https://caravelpay.app/plugins/#laravel>, and its checksum is in <https://caravelpay.app/download/SHA256SUMS>.

## Docs and support

- Guide: <https://caravelpay.app/docs/integrations#laravel>
- API reference: <https://caravelpay.app/docs/>
- Questions and bug reports: [hello@caravelpay.app](mailto:hello@caravelpay.app), or open an issue here.

## Licence

MIT. See `LICENSE`.
