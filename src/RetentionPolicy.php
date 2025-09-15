<?php
namespace AutoDBVault;

final class RetentionPolicy
{
    private int $dailyDays;

    public function __construct(int $dailyDays)
    {
        $this->dailyDays = $dailyDays;
    }

    /**
     * Decide which files to delete, which to keep.
     * Rules:
     *   - Keep today's all hourly backups.
     *   - Keep last N days’ final backup of the day (23:00:00).
     *   - Delete everything else.
     *
     * @param string[] $allFiles Sorted asc by name
     * @param \DateTimeImmutable $now
     * @return array{keep: string[], delete: string[]}
     */
    public function evaluate(array $allFiles, \DateTimeImmutable $now): array
    {
        $keep = [];
        $delete = [];

        $byDate = [];
        foreach ($allFiles as $f) {
            if (preg_match('/_(\d{8})_(\d{6})\.sql\.gz$/', $f, $m)) {
                $date = $m[1]; // YYYYMMDD
                $time = $m[2]; // HHMMSS
                $byDate[$date][] = ['file' => $f, 'time' => $time];
            }
        }

        // normalize sorting
        foreach ($byDate as &$arr) {
            usort($arr, fn($a, $b) => strcmp($a['time'], $b['time']));
        }

        $today = $now->format('Ymd');

        // keep all today's files
        if (isset($byDate[$today])) {
            foreach ($byDate[$today] as $entry) {
                $keep[] = $entry['file'];
            }
        }

        // keep last N days’ last backup (excluding today)
        for ($i = 1; $i <= $this->dailyDays; $i++) {
            $day = $now->sub(new \DateInterval("P{$i}D"))->format('Ymd');
            if (isset($byDate[$day])) {
                $lastEntry = end($byDate[$day]); // last backup of that day
                $keep[] = $lastEntry['file'];
            }
        }

        // delete everything else
        foreach ($allFiles as $f) {
            if (!in_array($f, $keep, true)) {
                $delete[] = $f;
            }
        }

        return [
            'keep' => array_values(array_unique($keep)),
            'delete' => array_values(array_unique($delete))
        ];
    }
}
