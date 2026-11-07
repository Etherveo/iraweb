<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// M4 simulator (SIM-35, blueprint §6.2). One row per simulation run.
// id is a ULID. No created_at/updated_at columns — the lifecycle is
// tracked by started_at/finished_at/reset_at, so timestamps are off.
// Status changes go through SimulationAudit, never update() directly.
class SimulationRun extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public const ACTIONS = [
        'create_accounts', 'tx_user_first',
        'tx_period_first', 'funnel_events',
    ];

    public const STATUSES = ['running', 'berhasil', 'gagal', 'direset'];

    protected $fillable = [
        'developer_id', 'action', 'params', 'seed', 'period_start',
        'period_end', 'status', 'counts', 'error_message',
        'idempotency_key', 'started_at', 'finished_at', 'reset_at',
    ];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'counts' => 'array',
            'seed' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'reset_at' => 'datetime',
        ];
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(SimulationAuditLog::class, 'run_id');
    }
}
