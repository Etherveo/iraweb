<?php

namespace App\Payments;

use LogicException;

// M3b PAY-24/PAY-55/PAY-57, blueprint §8.6/§8.9. The registry of known
// payment providers and drivers. Today only 'simulator' exists: any
// other provider on the webhook route → 404, any other driver (or an
// empty one) → the application fails to start with a clear message
// (fail fast, called from AppServiceProvider::boot).
class ProviderRegistry
{
    public const KNOWN_PROVIDERS = ['simulator'];

    public const KNOWN_DRIVERS = ['simulator'];

    public static function isKnownProvider(string $provider): bool
    {
        return in_array($provider, self::KNOWN_PROVIDERS, true);
    }

    // Fail fast: empty or unknown driver. Called at boot AND at
    // adapter resolution, so the message is the same in both places.
    public static function validateDriver(string $driver): string
    {
        if ($driver === '' || ! in_array($driver, self::KNOWN_DRIVERS, true)) {
            throw new LogicException(
                'PAYMENT_DRIVER tidak dikenal atau kosong: "'.$driver.'". '.
                'Nilai yang didukung: simulator. Periksa .env PAYMENT_DRIVER.'
            );
        }

        return $driver;
    }

    public static function adapterClassFor(string $driver): string
    {
        self::validateDriver($driver);

        return SimulatorPaymentAdapter::class;
    }
}
