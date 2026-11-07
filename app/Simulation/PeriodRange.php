<?php

namespace App\Simulation;

use Carbon\CarbonImmutable;

// M4 simulator (SIM-10/16/29). Immutable value object for a resolved
// simulation period: WITA calendar dates mapped to the UTC instants
// [start 00:00:00 WITA, end 23:59:59.999 WITA].
final readonly class PeriodRange
{
    public function __construct(
        public string $startDate, // Y-m-d, WITA
        public string $endDate, // Y-m-d, WITA
        public CarbonImmutable $startUtc,
        public CarbonImmutable $endUtc,
        public int $days, // Inclusive day count.
    ) {}

    public function contains(CarbonImmutable $instant): bool
    {
        return $instant >= $this->startUtc && $instant <= $this->endUtc;
    }

    /** Whole seconds covered by the range (end - start). */
    public function spanSeconds(): int
    {
        return $this->endUtc->getTimestamp() - $this->startUtc->getTimestamp();
    }
}
