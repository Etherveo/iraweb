<?php

namespace App\Simulation;

use InvalidArgumentException;

// M4 simulator (SIM-23, blueprint §9.4). Assigns final statuses from a
// percentage mix with the largest-remainder method, so the counts per
// status sum exactly to the transaction count. The resulting list is
// shuffled (seeded) so statuses interleave instead of blocking by status.
final class StatusAssigner
{
    public const FINAL_STATUSES = ['selesai', 'dibatalkan', 'kedaluwarsa'];

    public function __construct(private SeededRandom $random) {}

    /**
     * @param  array<string,int> $mix e.g. ['selesai'=>80,'dibatalkan'=>10,'kedaluwarsa'=>10].
     * @return list<string> one status per transaction.
     *
     * @throws InvalidArgumentException when the mix is empty, has an
     *   unknown status, a negative/out-of-range percentage, or does not
     *   sum to 100.
     */
    public function assign(int $count, array $mix): array
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Jumlah transaksi tidak boleh negatif.');
        }

        $this->validateMix($mix);

        $floors = [];
        $remainders = [];
        $assigned = 0;

        foreach ($mix as $status => $pct) {
            $quota = $count * $pct / 100;
            $floors[$status] = (int) floor($quota);
            $remainders[$status] = $quota - $floors[$status];
            $assigned += $floors[$status];
        }

        // Remaining seats (< number of statuses, since each remainder < 1)
        // go to the largest remainders; ties keep the mix's key order.
        $ranked = [];

        foreach (array_keys($mix) as $index => $status) {
            $ranked[] = ['status' => $status, 'remainder' => $remainders[$status], 'index' => $index];
        }

        usort($ranked, fn (array $a, array $b): int => ($b['remainder'] <=> $a['remainder']) ?: ($a['index'] <=> $b['index']));

        $remaining = $count - $assigned;

        for ($i = 0; $i < $remaining; $i++) {
            $floors[$ranked[$i]['status']]++;
        }

        $statuses = [];

        foreach ($floors as $status => $n) {
            for ($i = 0; $i < $n; $i++) {
                $statuses[] = $status;
            }
        }

        return $this->random->shuffle($statuses);
    }

    /** @param array<string,int> $mix */
    private function validateMix(array $mix): void
    {
        if ($mix === []) {
            throw new InvalidArgumentException('Campuran status akhir tidak boleh kosong.');
        }

        $sum = 0;

        foreach ($mix as $status => $pct) {
            if (! in_array($status, self::FINAL_STATUSES, true)) {
                throw new InvalidArgumentException("Status akhir tidak dikenal: '{$status}'.");
            }

            if (! is_int($pct) || $pct < 0 || $pct > 100) {
                throw new InvalidArgumentException("Persentase status '{$status}' harus bilangan bulat 0–100.");
            }

            $sum += $pct;
        }

        if ($sum !== 100) {
            throw new InvalidArgumentException("Campuran status akhir harus berjumlah 100% (saat ini {$sum}%).");
        }
    }
}
