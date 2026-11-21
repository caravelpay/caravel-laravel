<?php

declare(strict_types=1);

namespace CaravelPay\Laravel\Console;

use CaravelPay\Client;
use CaravelPay\Exception\CaravelPayException;
use CaravelPay\Laravel\Caravel;
use Illuminate\Console\Command;

/**
 * php artisan caravel:connect 0xWallet [--site=https://shop.example] [--email=you@shop.example]
 *
 * Calls POST /v1/connect and prints the .env lines. Every call creates a NEW merchant (new id and
 * secret); keep the old credentials in CARAVEL_PREVIOUS_CREDENTIALS until their open checkouts finish.
 */
class ConnectCommand extends Command
{
    protected $signature = 'caravel:connect
        {wallet : Your Polygon payout wallet (0x…)}
        {--site= : Your site\'s HTTPS address (default: APP_URL)}
        {--email= : Optional contact for operational notices}
        {--force : Connect again even though credentials are configured}';

    protected $description = 'Get Caravel Pay credentials (POST /v1/connect) and print the .env lines';

    public function handle(): int
    {
        $config = (array) config('caravel', []);
        $configured = trim((string) ($config['merchant_id'] ?? ''));
        if ('' !== $configured && !$this->option('force')
            && !$this->confirm('Credentials for ' . $configured . ' are already configured. Connecting again creates a NEW merchant. Continue?', false)) {
            return self::FAILURE;
        }
        $wallet = trim((string) $this->argument('wallet'));
        if (!preg_match('/^0x[0-9a-fA-F]{40}$/D', $wallet)) {
            $this->error('Enter a Polygon address that starts with 0x (40 hexadecimal characters after it).');
            return self::FAILURE;
        }
        $site = rtrim(trim((string) ($this->option('site') ?: config('app.url'))), '/');
        if (0 !== stripos($site, 'https://')) {
            $this->error('The site address must start with https:// (pass --site=https://your-site.example).');
            return self::FAILURE;
        }
        $params = ['site_url' => $site, 'payout_address' => $wallet, 'platform' => 'api', 'plugin_version' => 'laravel-' . Caravel::VERSION];
        $email = trim((string) $this->option('email'));
        if ('' !== $email) {
            $params['email'] = $email;
        }
        $options = array_intersect_key(app(Caravel::class)->options(), array_flip(['api_base', 'transport', 'clock']));
        try {
            $result = Client::connect($params, $options);
        } catch (CaravelPayException $e) {
            $this->error('Connecting failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info('Connected: merchant ' . $result['merchant_id'] . ' (status ' . $result['status'] . ', fee ' . $result['fee_rate'] . ').');
        $this->line('Add these lines to .env on your server. The secret is shown only this once:');
        $this->line('CARAVEL_MERCHANT_ID=' . $result['merchant_id']);
        $this->line('CARAVEL_SECRET=' . $result['secret']);
        if ('' !== $configured && '' !== (string) ($config['secret'] ?? '')) {
            $previous = array_filter(array_merge([$configured . ':' . $config['secret']], explode(',', (string) ($config['previous_credentials'] ?? ''))));
            $this->line('CARAVEL_PREVIOUS_CREDENTIALS=' . implode(',', array_unique($previous)));
            $this->warn('Keep CARAVEL_PREVIOUS_CREDENTIALS until checkouts created with the old credentials have finished (about a day).');
        }
        return self::SUCCESS;
    }
}
