<?php

namespace App\Console\Commands;

use App\Services\OrderExpiryService;
use Illuminate\Console\Command;

// M3a PAY-32/PAY-34 (on-access variant). There is NO scheduled job in
// M3a: expiry normally happens when an order page is opened (PAY-35).
// This command is the manual/batch equivalent (and the hook the M3b
// scheduler will call). Idempotent: already-expired orders are skipped.
// Run: php artisan ira:expire-overdue-orders
class ExpireOverdueOrdersCommand extends Command
{
    protected $signature = 'ira:expire-overdue-orders';

    protected $description = 'Expire overdue menunggu_pembayaran orders (release stock, cancel events)';

    public function handle(OrderExpiryService $expiry): int
    {
        $count = $expiry->expireOverdue();

        $this->info('Pesanan kedaluwarsa diproses: '.$count.'.');

        return self::SUCCESS;
    }
}
