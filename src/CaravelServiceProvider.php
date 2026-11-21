<?php

declare(strict_types=1);

namespace CaravelPay\Laravel;

use CaravelPay\Laravel\Console\ConnectCommand;
use CaravelPay\Laravel\Http\Middleware\VerifyCaravelSignature;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the "caravel" service (Caravel facade), the webhook handler, the "caravel.webhook"
 * middleware, the POST caravel/webhook route and the caravel:connect command.
 */
class CaravelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/caravel.php', 'caravel');
        $this->app->singleton(Caravel::class, static fn ($app) => new Caravel((array) $app['config']->get('caravel', [])));
        $this->app->alias(Caravel::class, 'caravel');
        $this->app->singleton(WebhookHandler::class, static function ($app) {
            $config = (array) $app['config']->get('caravel', []);
            $store = $app['cache']->store($config['webhook']['cache_store'] ?? null);
            return new WebhookHandler(
                $app->make(Caravel::class),
                static fn ($event) => $app['events']->dispatch($event),
                new CacheEventStore($store, (int) ($config['webhook']['remember_seconds'] ?? 172800))
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__ . '/../config/caravel.php' => $this->app->configPath('caravel.php')], 'caravel-config');
        $this->app['router']->aliasMiddleware('caravel.webhook', VerifyCaravelSignature::class);
        if ($this->app['config']->get('caravel.webhook.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/webhook.php');
        }
        if ($this->app->runningInConsole()) {
            $this->commands([ConnectCommand::class]);
        }
    }
}
