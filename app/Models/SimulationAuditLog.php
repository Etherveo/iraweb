<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// M4 simulator (SIM-36, blueprint §6.2). Append-only audit log: no
// updated_at column, and no update/delete code path exists for this
// model (a reset never touches this table). occurred_at is the single
// timestamp, so timestamps are off.
class SimulationAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'developer_id', 'action', 'run_id', 'summary', 'outcome', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function developer(): BelongsTo
    {
        return $this->belongsTo(Developer::class);
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(SimulationRun::class, 'run_id');
    }
}
