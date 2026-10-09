<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class CacheMonitor
{
    public const USAGES = [
        'search' => 'Recherche', 'autocomplete' => 'Autocomplétion', 'details' => 'Fiches',
        'availability' => 'Disponibilités', 'suggestions' => 'Suggestions', 'calendar' => 'Calendrier des épisodes',
        'people' => 'Personnes et filmographies', 'releases' => 'Dernières sorties', 'catalog' => 'Référentiels',
    ];
    public const FIELDS = ['hits', 'misses', 'missing', 'expired', 'failures', 'concurrent', 'avoided', 'unknown_hits', 'hit_us', 'miss_us', 'saved_us'];
    private array $metrics = [];
    private array $latencies = [];

    public function record(string $service, string $usage, bool $hit, int $durationUs, array $extra = []): void
    {
        if (!in_array($service, ApiMonitor::SERVICES, true) || !isset(self::USAGES[$usage])) return;
        $identity = ['service' => $service, 'usage' => $usage, 'bucket_at' => now()->utc()->startOfMinute()->format('Y-m-d H:i:s')];
        $key = implode('|', $identity);
        $this->metrics[$key] ??= $identity + array_fill_keys(self::FIELDS, 0);
        $this->metrics[$key][$hit ? 'hits' : 'misses']++;
        $this->metrics[$key][$hit ? 'hit_us' : 'miss_us'] += max(0, $durationUs);
        foreach ($extra as $field => $value) if (in_array($field, self::FIELDS, true)) $this->metrics[$key][$field] += max(0, (int) $value);
        // Bounded histograms keep approximate percentiles without retaining individual requests.
        $upper = 1000;
        while ($upper < $durationUs && $upper < 1_048_576_000) $upper *= 2;
        $upper = max($upper, $durationUs);
        $histogram = $identity + ['kind' => $hit ? 'hit' : 'miss', 'upper_us' => $upper];
        $histogramKey = implode('|', $histogram);
        $this->latencies[$histogramKey] ??= $histogram + ['samples' => 0];
        $this->latencies[$histogramKey]['samples']++;
    }

    public function flush(): void
    {
        $metrics = $this->metrics; $latencies = $this->latencies;
        $this->metrics = []; $this->latencies = [];
        if (!$metrics && !$latencies) return;
        ksort($metrics); ksort($latencies);
        try {
            DB::transaction(function () use ($metrics, $latencies) {
                foreach ($metrics as $row) $this->increment('cache_metric_buckets', $row, self::FIELDS);
                foreach ($latencies as $row) $this->increment('cache_latency_buckets', $row, ['samples']);
            });
        } catch (Throwable) {
            // Observability must not delay or fail user operations when the database is unavailable.
        }
    }

    private function increment(string $table, array $row, array $fields): void
    {
        $identity = array_diff_key($row, array_flip($fields));
        DB::table($table)->insertOrIgnore($identity + array_fill_keys($fields, 0));
        $updates = [];
        foreach ($fields as $field) $updates[$field] = DB::raw($field.' + '.(int) $row[$field]);
        DB::table($table)->where($identity)->update($updates);
    }
}
