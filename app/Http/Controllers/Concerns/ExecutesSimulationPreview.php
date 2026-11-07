<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Developer;
use App\Models\Product;
use App\Models\SimulationRun;
use App\Models\User;
use App\Simulation\SimulationAudit;
use App\Simulation\SimulationExecutor;
use App\Simulation\SimulationPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// M4 simulator (SIM-16/25/26, D5). Shared preview → execute plumbing
// for the three simulation actions (user-first, period-first, funnel).
// The plan is NEVER stored in the session (a 500-transaction plan is
// too big): the preview renders every input as hidden fields, and the
// execute action rebuilds the plan deterministically from (params,
// seed) and compares its hash with the preview hash.
trait ExecutesSimulationPreview
{
    /**
     * The acting developer (the route group guarantees
     * auth:developer, but the service signatures need a Developer,
     * not a nullable Authenticatable).
     */
    protected function actingDeveloper(Request $request): Developer
    {
        $developer = $request->user('developer');

        abort_if($developer === null, 403);

        return $developer;
    }

    /**
     * Data shared by the three simulation forms: the simulation
     * accounts (for the picker, D8), the active products (for the
     * product scope), the config defaults, and the period quick picks
     * (WITA dates, computed server-side so the browser timezone
     * cannot shift them).
     */
    protected function simulatorFormData(): array
    {
        return [
            'accounts' => User::query()->where('origin', 'developer_simulation')->orderBy('id')->get(),
            'products' => Product::query()->where('is_active', true)->with('category')->orderBy('id')->get(),
            'defaultMode' => (string) config('simulation.default_mode', 'deterministic'),
            'defaultSeed' => (int) config('simulation.default_seed', 20261101),
            'maxTransactions' => (int) config('simulation.max_transactions_per_request', 500),
            'statusMix' => config('simulation.status_mix', ['selesai' => 80, 'dibatalkan' => 10, 'kedaluwarsa' => 10]),
            'funnelViewToCart' => (int) config('simulation.funnel_view_to_cart', 40),
            'funnelCartToBooking' => (int) config('simulation.funnel_cart_to_booking', 60),
            'quickPicks' => $this->periodQuickPicks(),
        ];
    }

    /** [today, this week (Mon–Sun), this month] in WITA, Y-m-d. */
    protected function periodQuickPicks(): array
    {
        $tz = (string) config('ira.reporting_timezone', 'Asia/Makassar');
        $today = Carbon::now($tz);

        return [
            'today' => [
                $today->format('Y-m-d'),
                $today->format('Y-m-d'),
            ],
            'week' => [
                $today->copy()->startOfWeek()->format('Y-m-d'),
                $today->copy()->endOfWeek()->format('Y-m-d'),
            ],
            'month' => [
                $today->copy()->startOfMonth()->format('Y-m-d'),
                $today->copy()->endOfMonth()->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Flatten a nested input array into ['field[sub]' => value] pairs
     * for hidden <input> elements, so the execute action receives the
     * exact input the preview was built from.
     *
     * @return array<string, string>
     */
    protected function flattenForHiddenInputs(array $input, string $prefix = ''): array
    {
        $flat = [];

        foreach ($input as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix.'['.$key.']';

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenForHiddenInputs($value, $name));
            } elseif ($value !== null) {
                $flat[$name] = (string) $value;
            }
        }

        return $flat;
    }

    /**
     * Assemble the data the shared preview page needs: the plan, the
     * account/product models it references, the skipped inactive ids,
     * and the confirm/back routes.
     */
    protected function previewData(
        SimulationPlan $plan,
        array $params,
        array $input,
        string $confirmRoute,
        string $backRoute,
        string $actionLabel,
    ): array {
        $accounts = User::query()
            ->whereIn('id', $params['user_ids'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $products = Product::query()
            ->whereIn('id', array_column($params['products'], 'id'))
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        return [
            'plan' => $plan,
            'params' => $params,
            'accounts' => $accounts,
            'products' => $products,
            'statusBreakdown' => array_count_values(array_column($plan->orders, 'final_status')),
            'skippedInactiveIds' => $params['skipped_inactive_ids'] ?? [],
            'idempotencyKey' => (string) Str::uuid(),
            'confirmRoute' => $confirmRoute,
            'backRoute' => $backRoute,
            'actionLabel' => $actionLabel,
            'hiddenInputs' => $this->flattenForHiddenInputs($input),
        ];
    }

    /**
     * Execute a hash-verified plan (SIM-26/27): the rebuilt plan's
     * hash must equal the preview hash, otherwise the params changed
     * and a new preview is required. Opens the run, replays a
     * finished run on a repeated submit with the same key, and runs
     * the executor (which marks the run berhasil/gagal itself).
     */
    protected function executeVerifiedPlan(
        SimulationAudit $audit,
        SimulationExecutor $executor,
        Developer $developer,
        string $action,
        array $params,
        SimulationPlan $plan,
        string $idempotencyKey,
        string $paramsHash,
    ): SimulationRun {
        if ($plan->paramsHash !== $paramsHash) {
            throw ValidationException::withMessages([
                'params_hash' => 'Parameter berubah setelah pratinjau. Buat pratinjau baru (SIM-26).',
            ]);
        }

        // The hash rides along inside the stored params so a repeated
        // submit can be recognised without recomputing it: the stored
        // params went through a JSON round-trip, so a recomputed hash
        // would not match (e.g. Carbon instants serialize differently
        // than the planner canonicalizes them).
        $storedParams = array_merge($params, ['params_hash' => $paramsHash]);

        $run = $audit->recordRunStart(
            $developer,
            $action,
            $storedParams,
            $params['seed'],
            $idempotencyKey,
            $params['period_start'],
            $params['period_end'],
        );

        if (! $run->wasRecentlyCreated) {
            if (($run->params['params_hash'] ?? null) !== $paramsHash) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Kunci ini sudah dipakai untuk parameter lain. Buat pratinjau baru.',
                ]);
            }

            if ($run->status === 'berhasil') {
                return $run;
            }

            throw ValidationException::withMessages([
                'idempotency_key' => 'Run dengan kunci ini sudah ada (status: '.$run->status.'). Buat pratinjau baru untuk mengulang.',
            ]);
        }

        $executor->execute($plan, $run);

        return $run->fresh() ?? $run;
    }
}
