<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// M1 (ACC-01..15, blueprint §6.2).
// role: pembeli | admin_toko. origin: normal | developer_simulation.
// Emails are stored lowercased so the SQLite lower(email) unique index and
// the ACC-01 case-insensitive uniqueness rule agree.
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'origin', 'is_active',
        'deactivated_at', 'deactivation_reason', 'simulation_run_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)),
        );
    }

    public function isPembeli(): bool
    {
        return $this->role === 'pembeli';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin_toko';
    }

    public function isSimulationAccount(): bool
    {
        return $this->origin === 'developer_simulation';
    }

    public function scopePembeli(Builder $query): void
    {
        $query->where('role', 'pembeli');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
