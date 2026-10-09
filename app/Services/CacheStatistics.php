<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CacheStatistics
{
    public function report(CarbonImmutable $from, CarbonImmutable $to, string $interval, string $service, array $points): array
    {
        $query = fn (string $table) => DB::table($table)
            ->where('bucket_at', '>=', $from->utc()->format('Y-m-d H:i:s'))
            ->where('bucket_at', '<', $to->utc()->format('Y-m-d H:i:s'))
            ->when($service !== 'all', fn ($query) => $query->where('service', $service));
        $sums = implode(', ', array_map(fn ($field) => 'SUM('.$field.') AS '.$field, CacheMonitor::FIELDS));
        $rows = $query('cache_metric_buckets')->selectRaw('service, usage, SUBSTR(bucket_at, 1, 13) AS utc_hour, '.$sums)
            ->groupBy('service', 'usage')->groupByRaw('SUBSTR(bucket_at, 1, 13)')->get();
        $histograms = $query('cache_latency_buckets')->selectRaw('service, usage, kind, upper_us, SUM(samples) AS samples')
            ->groupBy('service', 'usage', 'kind', 'upper_us')->orderBy('upper_us')->get();
        $totals = array_fill_keys(CacheMonitor::FIELDS, 0); $usages = [];
        $hits = array_fill_keys($points, 0); $misses = $hits;
        foreach ($rows as $row) {
            $identity = $row->service.'|'.$row->usage;
            $usages[$identity] ??= ['service' => $row->service, 'usage' => $row->usage] + array_fill_keys(CacheMonitor::FIELDS, 0);
            foreach (CacheMonitor::FIELDS as $field) {
                $totals[$field] += (int) $row->$field;
                $usages[$identity][$field] += (int) $row->$field;
            }
            $date = CarbonImmutable::parse($row->utc_hour.':00:00', 'UTC');
            $point = $interval === 'day' ? $date->timezone('Europe/Paris')->format('Y-m-d') : $date->format('Y-m-d H');
            if (isset($hits[$point])) { $hits[$point] += (int) $row->hits; $misses[$point] += (int) $row->misses; }
        }
        $totals = $this->decorate($totals, $histograms);
        foreach ($usages as &$usage) {
            $usage = $this->decorate($usage, $histograms->where('service', $usage['service'])->where('usage', $usage['usage']));
        }
        unset($usage);
        ksort($usages);
        return ['totals' => $totals, 'usages' => array_values($usages), 'hits' => array_values($hits), 'misses' => array_values($misses),
            'firstRecordedAt' => DB::table('cache_metric_buckets')->min('bucket_at')];
    }

    private function decorate(array $values, $histograms): array
    {
        $lookups = $values['hits'] + $values['misses'];
        $values['hit_rate'] = $lookups ? 100 * $values['hits'] / $lookups : null;
        foreach (['hit' => 'hits', 'miss' => 'misses'] as $kind => $count) {
            $values[$kind.'_avg_ms'] = $values[$count] ? $values[$kind.'_us'] / $values[$count] / 1000 : null;
            $bins = $histograms->where('kind', $kind)->groupBy('upper_us')->map(fn ($group) => $group->sum('samples'))->sortKeys();
            foreach ([50, 95] as $percentile) {
                $target = (int) ceil($bins->sum() * $percentile / 100); $samples = 0;
                $values[$kind.'_p'.$percentile.'_ms'] = null;
                foreach ($bins as $upper => $number) {
                    $samples += $number;
                    if ($samples >= $target) { $values[$kind.'_p'.$percentile.'_ms'] = (int) $upper / 1000; break; }
                }
            }
        }
        return $values;
    }
}
