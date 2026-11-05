<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// M3b PAY-34: expire overdue menunggu_pembayaran orders every
// minute (idempotent, batch ≤ 200, via the signed internal webhook
// for orders with a pending payment). Runs with
// `php artisan schedule:run` (cron).
Schedule::command('ira:expire-overdue-orders')->everyMinute();
