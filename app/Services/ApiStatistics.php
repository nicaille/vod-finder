<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ApiStatistics
{
    public function report(CarbonImmutable $from, CarbonImmutable $to, string $interval, string $service): array
    {
        $services = $service === 'all' ? ApiMonitor::SERVICES : [$service];
        // Aggregate in SQL before loading rows; both SQLite and MySQL support SUBSTR.
        $rows = DB::table('api_request_buckets')->where('bucket_at', '>=', $from->utc()->format('Y-m-d H:i:s'))
            ->where('bucket_at', '<', $to->utc()->format('Y-m-d H:i:s'))->whereIn('service', $services)
            ->selectRaw('service, SUBSTR(bucket_at, 1, 13) AS utc_hour, SUM(requests) AS requests, SUM(failures) AS failures')
            ->groupBy('service')->groupByRaw('SUBSTR(bucket_at, 1, 13)')->orderBy('utc_hour')->get();

        $key = fn (CarbonImmutable $date) => $interval === 'day'
            ? $date->timezone('Europe/Paris')->format('Y-m-d') : $date->utc()->format('Y-m-d H');
        $points = [];
        $cursor = $interval === 'day' ? $from->startOfDay() : $from->utc()->startOfHour();
        while ($cursor->lt($to)) {
            $local = $cursor->timezone('Europe/Paris');
            $points[$key($cursor)] = $interval === 'day' ? $local->format('d/m/Y') : $local->format('d/m/Y H:i P');
            $cursor = $interval === 'day' ? $cursor->addDay() : $cursor->addHour();
        }
        $totals = []; $datasets = [];
        foreach ($services as $name) {
            $totals[$name] = ['requests' => 0, 'failures' => 0];
            $values = array_fill_keys(array_keys($points), ['requests' => 0, 'failures' => 0]);
            foreach ($rows->where('service', $name) as $row) {
                $bucket = $key(CarbonImmutable::parse($row->utc_hour.':00:00', 'UTC'));
                foreach (['requests', 'failures'] as $metric) {
                    $totals[$name][$metric] += (int) $row->$metric;
                    if (isset($values[$bucket])) $values[$bucket][$metric] += (int) $row->$metric;
                }
            }
            $datasets[] = ['label' => $name, 'requests' => array_column($values, 'requests'), 'failures' => array_column($values, 'failures')];
        }
        return ['labels' => array_values($points), 'datasets' => $datasets, 'totals' => $totals,
            'firstRecordedAt' => DB::table('api_request_buckets')->min('bucket_at')];
    }
}
