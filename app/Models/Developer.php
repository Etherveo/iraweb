<?php

namespace App\Models;

use Database\Factories\DeveloperFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// ACC-04: separate table and guard from users (pembeli/admin_toko).
// Keputusan 2026-11-05: login disatukan — developers gain role 'dev' so
// developer routes can declare role:dev like every other protected route
// (ACC-08). Guards/tables stay separate; only the login FORM is unified.
// There is deliberately NO is_active column here; ACC-07 inactive checks
// apply to the users table. Developer deactivation is not an M1 feature.
class Developer extends Authenticatable
{
    /** @use HasFactory<DeveloperFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    public function isDeveloper(): bool
    {
        return $this->role === 'dev';
    }

    protected $hidden = ['password', 'remember_token'];

    // Guard is determined by config/auth.php ('developer'), not by the model.

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
