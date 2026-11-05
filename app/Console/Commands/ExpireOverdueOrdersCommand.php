<?php

namespace App\Console\Commands;

use App\Services\OrderExpiryService;
use Illuminate\Console\Command;

// M3a/M3b PAY-32/PAY-34/PAY-36. The scheduled job (wired to run
// every minute from routes/console.php). Overdue orders WITH a
// pending payment expire through the signed internal webhook, so the
// expired callback is a real recorded HTTP event; orders without a
// pending payment (e.g. after a failed payment) expire directly.
// Idempotent: already-expired orders are skipped.
// Run: php artisan ira:expire-overdue-orders
class ExpireOverdueOrdersCommand extends Command
{
    protected $signature = 'ira:expire-overdue-orders';

    protected $description = 'Expire overdue menunggu_pembayaran orders (release stock, cancel events)';

    public function handle(OrderExpiryService $expiry): int
    {
        $count = $expiry->expireOverdueViaWebhook();

        $this->info('Pesanan kedaluwarsa diproses: '.$count.'.');

        return self::SUCCESS;
    }
}
