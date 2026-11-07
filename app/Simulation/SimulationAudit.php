<?php

namespace App\Simulation;

use App\Models\Developer;
use App\Models\SimulationAuditLog;
use App\Models\SimulationRun;
use InvalidArgumentException;

// M4 simulator (SIM-35/36, blueprint §6.2/§9.8). The ONLY place that
// writes simulation_runs and simulation_audit_logs, and the only place
// that changes a run's status — the SimulationRun docblock forbids
// updating a run's status directly anywhere else.
//
// One action = one run (SIM-02): recordRunStart opens it as 'running';
// recordRunFinish/recordRunFailed close it and write the single audit
// row carrying the outcome. recordAction writes the audit row for
// actions that are not a run themselves (resets). The audit table is
// append-only: nothing in this class updates or deletes audit rows,
// and the reset service never touches it (SIM-36).
final class SimulationAudit
{
    /**
     * Open a run (status 'running'). Idempotent on (developer,
     * idempotency_key) — a repeated submit with the same key returns
     * the existing run instead of creating a second one (SIM-26);
     * callers check ->wasRecentlyCreated and the run's status to
     * decide whether the action was already executed.
     */
    public function recordRunStart(
        Developer $developer,
        string $action,
        array $params,
        ?int $seed,
        string $idempotencyKey,
        ?string $periodStart = null,
        ?string $periodEnd = null,
    ): SimulationRun {
        if (! in_array($action, SimulationRun::ACTIONS, true)) {
            throw new InvalidArgumentException("Aksi simulasi tidak dikenal: '{$action}'.");
        }

        $existing = SimulationRun::query()
            ->where('developer_id', $developer->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return SimulationRun::query()->create([
            'developer_id' => $developer->id,
            'action' => $action,
            'params' => $params,
            'seed' => $seed,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'running',
            'counts' => [],
            'error_message' => null,
            'idempotency_key' => $idempotencyKey,
            'started_at' => now(),
            'finished_at' => null,
            'reset_at' => null,
        ]);
    }

    /** Close a run as 'berhasil' with its counts, and write the audit row. */
    public function recordRunFinish(SimulationRun $run, array $counts): void
    {
        $run->status = 'berhasil';
        $run->finished_at = now();
        $run->counts = $counts;
        $run->save();

        $this->writeAuditRow($run, 'berhasil', array_merge(
            ['counts' => $counts],
            $this->contextSummary($run),
        ));
    }

    /** Close a run as 'gagal' with the failure message, and write the audit row. */
    public function recordRunFailed(SimulationRun $run, string $message): void
    {
        $run->status = 'gagal';
        $run->finished_at = now();
        $run->error_message = $message;
        $run->save();

        $this->writeAuditRow($run, 'gagal', array_merge(
            ['error' => $message],
            $this->contextSummary($run),
        ));
    }

    /** Mark a run 'direset' (SIM-38/39). No audit row here — the reset's
     * own recordAction call carries the audit entry. */
    public function recordRunReset(SimulationRun $run): void
    {
        $run->status = 'direset';
        $run->reset_at = now();
        $run->save();
    }

    /** Write a free-form audit row (resets; outcome is usually 'berhasil'). */
    public function recordAction(
        Developer $developer,
        string $action,
        ?string $runId,
        array $summary,
        string $outcome,
    ): SimulationAuditLog {
        return SimulationAuditLog::query()->create([
            'developer_id' => $developer->id,
            'action' => $action,
            'run_id' => $runId,
            'summary' => $summary,
            'outcome' => $outcome,
            'occurred_at' => now(),
        ]);
    }

    private function writeAuditRow(SimulationRun $run, string $outcome, array $summary): void
    {
        SimulationAuditLog::query()->create([
            'developer_id' => $run->developer_id,
            'action' => $run->action,
            'run_id' => $run->id,
            'summary' => $summary,
            'outcome' => $outcome,
            'occurred_at' => now(),
        ]);
    }

    /** Period + seed context that every run audit row carries (blueprint §6.2). */
    private function contextSummary(SimulationRun $run): array
    {
        return [
            'period_start' => $run->period_start?->format('Y-m-d'),
            'period_end' => $run->period_end?->format('Y-m-d'),
            'seed' => $run->seed,
        ];
    }
}
