<?php

namespace App\Providers;

use App\Models\Cart;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
