<?php

namespace App\Simulation;

use App\Models\Cart;
use App\Models\Developer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentHttpTrace;
use App\Models\PaymentStatusTransition;
use App\Models\SimulationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// M4 simulator (SIM-38/39/40, blueprint §9.9). Deletes simulation
// data in ONE transaction. Every query is scoped by
// origin='developer_simulation' (plus simulation_run_id for a
// per-run reset) — a normal order/account id can never reach a
// delete here (SIM-40), and the panel has no write access to normal
// data at all (SIM-41). The audit table is NEVER touched (SIM-36);
// the reset itself is recorded via SimulationAudit::recordAction
// inside the same transaction (only a completed reset is audited).
final class SimulationResetService
{
    public function __construct(
        private SimulationAudit $audit,
    ) {}

    /**
     * Delete everything one run created (SIM-38). The run must be
     * 'berhasil' — a 'gagal' run created no data, and a 'direset' run
     * has nothing left to delete.
     *
     * @return array{scope:string, run_id:string, deleted:array<string,int>, accounts_deleted:list<int>, accounts_deactivated:list<array{id:int, name:string, email:string}>}
     */
    public function resetRun(SimulationRun $run, Developer $developer): array
    {
        if ($run->status !== 'berhasil') {
            throw ValidationException::withMessages([
                'run' => 'Run ini berstatus '.$run->status.' dan tidak dapat direset.',
            ]);
        }

        return DB::transaction(function () use ($run, $developer) {
            $deleted = $this->deleteSimulationData($run->id);
            $accounts = $this->deleteOrDeactivateAccounts($run->id);

            $this->audit->recordRunReset($run);

            $summary = $this->summary('run', $run->id, $deleted, $accounts);
            $this->audit->recordAction($developer, 'reset_run', $run->id, $summary, 'berhasil');

            return $summary;
        });
    }

    /**
     * Delete ALL simulation data (SIM-39). Normal-origin row counts
     * are identical before and after — tested in SimulationM4Test.
     * Only 'berhasil' runs become 'direset' ('gagal' runs created no
     * data, so they stay 'gagal').
     *
     * Assumes no run is in flight when called (the panel does not
     * offer reset while a run is 'running').
     */
    public function resetAll(Developer $developer): array
    {
        return DB::transaction(function () use ($developer) {
            $deleted = $this->deleteSimulationData(null);
            $accounts = $this->deleteOrDeactivateAccounts(null);

            $runs = SimulationRun::query()->where('status', 'berhasil')->get();

            foreach ($runs as $run) {
                $this->audit->recordRunReset($run);
            }

            $summary = $this->summary('all', null, $deleted, $accounts);
            $this->audit->recordAction($developer, 'reset_all', null, $summary, 'berhasil');

            return $summary;
        });
    }

    /**
     * Blueprint §9.9 delete order: events → traces → transitions →
     * histories → payments → order_items → orders. Each delete() return
     * value is the row count, used in the reset report.
     *
     * @return array<string,int>
     */
    private function deleteSimulationData(?string $runId): array
    {
        $scope = fn ($query) => $query
            ->where('origin', 'developer_simulation')
            ->when($runId !== null, fn ($query) => $query->where('simulation_run_id', $runId));

        return [
            'events' => $scope(Event::query())->delete(),
            'payment_http_traces' => $scope(PaymentHttpTrace::query())->delete(),
            'payment_status_transitions' => $scope(PaymentStatusTransition::query())->delete(),
            'order_status_histories' => $scope(OrderStatusHistory::query())->delete(),
            'payments' => $scope(Payment::query())->delete(),
            'order_items' => $scope(OrderItem::query())->delete(),
            'orders' => $scope(Order::query())->delete(),
        ];
    }

    /**
     * For each sim account in scope: delete it ONLY when it has no
     * remaining data at all — the sim data was already deleted above,
     * so anything left is a normal-origin row (resetAll) or a
     * normal-origin row / another run's sim row (resetRun). The
     * remaining rows reference the user with ON DELETE RESTRICT, so
     * deleting the account would fail; instead it is deactivated and
     * reported (U-23).
     *
     * A cart is deleted BEFORE its account (carts.user_id is
     * ON DELETE RESTRICT — rencana §5 R8); cart_items cascade from
     * the cart. Addresses of a deleted account cascade via FK;
     * addresses/carts of a deactivated account are kept.
     *
     * @return array{deleted:list<int>, deactivated:list<array{id:int, name:string, email:string}>}
     */
    private function deleteOrDeactivateAccounts(?string $runId): array
    {
        $accounts = User::query()
            ->where('origin', 'developer_simulation')
            ->when($runId !== null, fn ($query) => $query->where('simulation_run_id', $runId))
            ->orderBy('id')
            ->get();

        $deleted = [];
        $deactivated = [];

        foreach ($accounts as $account) {
            $hasRemainingData = Order::query()->where('user_id', $account->id)->exists()
                || Event::query()->where('user_id', $account->id)->exists()
                || Payment::query()->where('user_id', $account->id)->exists();

            if ($hasRemainingData) {
                $account->update([
                    'is_active' => false,
                    'deactivated_at' => now(),
                    'deactivation_reason' => 'Akun dipertahankan saat reset simulasi: masih memiliki data di luar cakupan reset.',
                ]);

                $deactivated[] = [
                    'id' => $account->id,
                    'name' => $account->name,
                    'email' => $account->email,
                ];

                continue;
            }

            Cart::query()->where('user_id', $account->id)->delete();
            $account->delete();

            $deleted[] = $account->id;
        }

        return ['deleted' => $deleted, 'deactivated' => $deactivated];
    }

    private function summary(string $scope, ?string $runId, array $deleted, array $accounts): array
    {
        return [
            'scope' => $scope,
            'run_id' => $runId,
            'deleted' => $deleted,
            'accounts_deleted' => $accounts['deleted'],
            'accounts_deactivated' => $accounts['deactivated'],
        ];
    }
}
