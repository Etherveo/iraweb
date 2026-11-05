<?php

namespace App\Console\Commands;

use App\Models\Developer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

// ACC-05: admin_toko and developer accounts are provisioned via artisan
// or seeder only — there is deliberately NO public registration for them.
// Password comes from --password (env/script use) or an interactive secret
// prompt; it is hashed immediately, never printed, never logged.
// Re-running for an existing email does NOT overwrite without --force
// plus an interactive confirmation.
class ProvisionAccountCommand extends Command
{
    protected $signature = 'ira:provision-account
        {role : admin_toko or developer}
        {email : account email}
        {name : account name}
        {--password= : password (if omitted, prompted interactively)}
        {--force : overwrite an existing account without confirmation}';

    protected $description = 'Provision an admin_toko or developer account (ACC-05)';

    public function handle(): int
    {
        $role = $this->argument('role');
        if (! in_array($role, ['admin_toko', 'developer'], true)) {
            $this->error('Role harus admin_toko atau developer.');

            return self::FAILURE;
        }

        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = trim((string) $this->argument('name'));

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Nama dan email yang valid wajib diisi.');

            return self::FAILURE;
        }

        $existing = $role === 'developer'
            ? Developer::query()->whereRaw('lower(email) = ?', [$email])->first()
            : User::query()->whereRaw('lower(email) = ?', [$email])->first();

        // Provisioning must never silently promote a buyer to admin: email
        // uniqueness is per table, but within users the existing account
        // must already be an admin_toko account to be overwritten.
        if ($existing instanceof User && $existing->role !== 'admin_toko') {
            $this->error('Email ini sudah dipakai akun pembeli. Provisioning tidak mengubah role pembeli menjadi admin.');

            return self::FAILURE;
        }

        if ($existing !== null && ! $this->option('force')) {
            $this->error('Akun dengan email ini sudah ada. Jalankan ulang dengan --force dan konfirmasi untuk menimpa.');

            return self::FAILURE;
        }

        if ($existing !== null && $this->option('force') && ! $this->confirm('Akun sudah ada. Timpa kata sandi akun ini?')) {
            $this->info('Dibatalkan. Akun tidak diubah.');

            return self::SUCCESS;
        }

        $password = $this->option('password');
        if (! is_string($password) || $password === '') {
            $password = $this->secret('Kata sandi (minimal 8 karakter)');
        }

        if (! is_string($password) || mb_strlen($password) < 8) {
            $this->error('Kata sandi minimal 8 karakter.');

            return self::FAILURE;
        }

        $hashed = Hash::make($password);

        if ($role === 'developer') {
            Developer::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $hashed],
            );
        } else {
            User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => $hashed, 'role' => 'admin_toko', 'origin' => 'normal', 'is_active' => true],
            );
        }

        // Deliberately do NOT print the password, its hash, or any secret.
        $this->info("Akun {$role} ({$email}) berhasil diprovisioning.");

        return self::SUCCESS;
    }
}
