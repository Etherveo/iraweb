<?php

use App\Http\Controllers\Api\SimulatorGatewayController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// M3b PAY-24..27, blueprint §8.6. Versioned payment webhooks. Only
// 'simulator' is a known provider (ProviderRegistry); anything else
// → 404. Protected by the HMAC signature (X-Simulator-Signature,
// PAY-25) and rate limiting. API routes carry no CSRF middleware, so
// no exemption is needed here.
Route::post('/v1/webhooks/payments/{provider}', [WebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('api.webhooks.payments');

// M3b, blueprint §8.4. The simulator adapter's internal gateway:
// only reachable through InternalDispatcher (middleware
// InternalDispatchOnly); a real external HTTP request → 404.
Route::post('/v1/simulator/payments', [SimulatorGatewayController::class, 'create'])
    ->middleware('internal-only')
    ->name('api.simulator.payments');
