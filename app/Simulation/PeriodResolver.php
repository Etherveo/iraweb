<?php

namespace App\Simulation;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

// M4 simulator (SIM-10/16/28/29, blueprint §9.1). Turns WITA calendar
// dates into the UTC instant range [start 00:00:00, end 23:59:59.999]
// and enforces the SIM-28 period rules. Pure: no database, no config
// reads in the instance methods (use fromConfig() for the Laravel wiring).
final class PeriodResolver
{
    public function __construct(
        private string $timezone = 'Asia/Makassar',
        private int $maxPeriodDays = 366,
    ) {}

    /** Build from the live config (simulation.max_period_days, ira.reporting_timezone). */
    public static function fromConfig(): self
    {
        return new self(
            (string) config('ira.reporting_timezone', 'Asia/Makassar'),
            (int) config('simulation.max_period_days', 366),
        );
    }

    /**
     * @throws InvalidArgumentException on any SIM-28 violation, with an
     *   Indonesian message naming the offending value.
     */
    public function resolve(string $startDate, string $endDate): PeriodRange
    {
        $start = $this->parseDate($startDate, 'tanggal mulai', '00:00:00.000000');
        $end = $this->parseDate($endDate, 'tanggal akhir', '23:59:59.999000');

        if ($startDate > $endDate) {
            throw new InvalidArgumentException("Tanggal mulai ({$startDate}) harus sama dengan atau sebelum tanggal akhir ({$endDate}).");
        }

        $todayWita = CarbonImmutable::now($this->timezone)->toDateString();

        if ($endDate > $todayWita) {
            throw new InvalidArgumentException("Tanggal akhir ({$endDate}) tidak boleh di masa depan (WITA). Hari ini: {$todayWita}.");
        }

        // Inclusive day count: same-day period is 1 day, not 0.
        $days = $start->diffInDays($end) + 1;

        if ($days > $this->maxPeriodDays) {
            throw new InvalidArgumentException("Panjang periode {$days} hari melebihi batas {$this->maxPeriodDays} hari.");
        }

        return new PeriodRange(
            $startDate,
            $endDate,
            $start->setTimezone('UTC'),
            $end->setTimezone('UTC'),
            $days,
        );
    }

    private function parseDate(string $value, string $label, string $time): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('Y-m-d H:i:s.u', "{$value} {$time}", $this->timezone);

        // createFromFormat is lenient (it accepts 2026-02-30 by rolling
        // over); the round-trip check rejects anything but strict Y-m-d.
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("Format {$label} tidak valid: '{$value}' (harus YYYY-MM-DD).");
        }

        return $parsed;
    }
}
