<?php

namespace App\Simulation;

use Carbon\CarbonInterface;
use JsonException;

// M4 simulator (SIM-25/26, blueprint §9.6). Immutable plan object:
// built once by FunnelPlanner from (params, seed), shown in the preview,
// then executed verbatim by SimulationExecutor. No setters; the only way
// to change a plan is to build a new one (and re-preview, SIM-26).
final class SimulationPlan
{
    /**
     * @param string $action 'tx_user_first' | 'tx_period_first' | 'funnel_events'.
     * @param array $params canonical planner params (JSON-serializable
     *   scalars/arrays + CarbonImmutable instants). The seed is part of
     *   $params, so it is covered by $paramsHash.
     * @param string $paramsHash sha256 of the canonical JSON of $params.
     * @param list<array> $orders planned orders (shape documented on
     *   FunnelPlanner::plan / FunnelPlanner::planFunnel).
     * @param list<array> $orphanEvents orphan view/add_to_cart events
     *   (SIM-12/24): ['type', 'user_id', 'product_id', 'occurred_at'].
     * @param array<string,int> $estimatedRows row estimates per table.
     */
    public function __construct(
        public readonly string $action,
        public readonly array $params,
        public readonly string $paramsHash,
        public readonly int $seed,
        public readonly array $orders,
        public readonly array $orphanEvents,
        public readonly array $estimatedRows,
        public readonly PeriodRange $period,
    ) {}

    /**
     * sha256 of the canonical JSON of $params (assoc keys sorted
     * recursively, Carbon instants as UTC ISO-8601). Changing any param —
     * including the seed — changes the hash and forces a re-preview
     * (SIM-26).
     */
    public static function hashParams(array $params): string
    {
        $json = json_encode(
            self::canonicalize($params),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        return hash('sha256', $json);
    }

    public function totalEstimatedRows(): int
    {
        return array_sum($this->estimatedRows);
    }

    /** Preview/serialization shape: instants as ISO-8601 UTC strings. */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'params' => self::canonicalize($this->params),
            'params_hash' => $this->paramsHash,
            'seed' => $this->seed,
            'period' => [
                'start_date' => $this->period->startDate,
                'end_date' => $this->period->endDate,
                'start' => $this->period->startUtc->toIso8601String(),
                'end' => $this->period->endUtc->toIso8601String(),
                'days' => $this->period->days,
            ],
            'orders' => self::canonicalize($this->orders),
            'orphan_events' => self::canonicalize($this->orphanEvents),
            'estimated_rows' => $this->estimatedRows,
            'total_estimated_rows' => $this->totalEstimatedRows(),
        ];
    }

    /**
     * Recursively sort assoc keys; CarbonInterface → UTC ISO-8601 string.
     * Lists keep their order (order is meaningful: it is the plan).
     */
    public static function canonicalize(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface) {
            // copy() first: setTimezone() mutates a mutable Carbon.
            return $value->copy()->setTimezone('UTC')->toIso8601String();
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(self::canonicalize(...), $value);
            }

            ksort($value);

            $out = [];

            foreach ($value as $k => $v) {
                $out[$k] = self::canonicalize($v);
            }

            return $out;
        }

        return $value;
    }
}
