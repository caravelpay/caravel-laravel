<?php

use CaravelPay\Laravel\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post((string) config('caravel.webhook.path', 'caravel/webhook'), WebhookController::class)
    ->middleware('caravel.webhook')
    ->name('caravel.webhook');
