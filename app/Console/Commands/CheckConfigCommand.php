<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// M0 config validation. Run: php artisan ira:check-config
// Exits non-zero on the first failing check so CI / laptop setup fails
// loudly instead of starting with a broken configuration.
// No Python is checked here: the Python analytics service is deferred
// pending Ren's script decision and is not an M0/M1 dependency.
class CheckConfigCommand extends Command
{
    protected $signature = 'ira:check-config';
    protected $description = 'Validate M0 configuration (SQLite, keys, Ira settings)';

    public function handle(): int
    {
        $failures = [];

        if (config('database.default') !== 'sqlite') {
            $failures[] = 'DB_CONNECTION harus sqlite, saat ini: '.(string) config('database.default');
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $failures[] = 'Koneksi SQLite gagal: '.$e->getMessage();
        }

        if ((string) config('app.key') === '') {
            $failures[] = 'APP_KEY kosong. Jalankan: php artisan key:generate';
        }

        if ((string) config('ira.reporting_timezone') !== 'Asia/Makassar') {
            $failures[] = 'REPORTING_TIMEZONE harus Asia/Makassar.';
        }

        if ((string) config('ira.sim_account_email_domain') === '') {
            $failures[] = 'SIM_ACCOUNT_EMAIL_DOMAIN kosong.';
        }

        // Storage timezone must stay UTC; reporting is WITA (blueprint A-11).
        if ((string) config('app.timezone') !== 'UTC') {
            $failures[] = 'app.timezone harus UTC untuk penyimpanan; pelaporan memakai REPORTING_TIMEZONE.';
        }

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        if ($failures !== []) {
            return self::FAILURE;
        }

        $this->info('M0 config OK: SQLite, APP_KEY, UTC storage, WITA reporting, SIM domain.');

        return self::SUCCESS;
    }
}
