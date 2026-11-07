<?php

namespace App\Providers;

use App\Models\Cart;
use App\Payments\PaymentAdapter;
use App\Payments\ProviderRegistry;
use App\Payments\QrRenderer;
use App\Payments\TextQrRenderer;
use App\Simulation\BiodataGenerator;
use App\Simulation\PythonBiodataGenerator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // M3b PAY-55/PAY-57: the payment adapter is chosen by
        // PAYMENT_DRIVER at resolution time (never by a direct class
        // reference in checkout/order/analytics code).
        $this->app->bind(PaymentAdapter::class, function ($app) {
            return $app->make(ProviderRegistry::adapterClassFor(
                (string) config('payment.driver', 'simulator')
            ));
        });

        // M3b PAY-58: QR rendering. TextQrRenderer is the fallback
        // (URL as labelled text) while Ren's QR script is pending;
        // swap this binding when the real QR implementation lands —
        // nothing else changes (see QrRenderer).
        $this->app->bind(QrRenderer::class, TextQrRenderer::class);

        // M4 SIM-06: the biodata generator is chosen by binding, never
        // by a direct class reference in controllers — Pest rebinds
        // this to SequentialBiodataGenerator so tests never shell out
        // to Python.
        $this->app->bind(BiodataGenerator::class, PythonBiodataGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // M3b PAY-57: fail fast on an empty or unknown payment
        // driver, so a misconfigured .env is caught at start, not
        // at the first checkout.
        ProviderRegistry::validateDriver((string) config('payment.driver', 'simulator'));

        // M3a CRT-04: the header shows the buyer's cart item count.
        // Only for the web guard with role pembeli; guests, admins and
        // developers get 0 (the nav does not show the cart link for
        // them anyway). One small query per page render, no caching:
        // the count must never be stale after a cart change.
        View::composer('layouts.app', function ($view): void {
            $count = 0;
            $user = Auth::guard('web')->user();

            if ($user !== null && $user->isPembeli()) {
                $cart = Cart::query()->where('user_id', $user->id)->first();
                $count = $cart !== null ? (int) $cart->items()->sum('quantity') : 0;
            }

            $view->with('cartItemCount', $count);
        });
    }
}
