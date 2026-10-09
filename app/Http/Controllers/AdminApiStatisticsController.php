<?php

namespace App\Http\Controllers;

use App\Services\ApiMonitor;
use App\Services\ApiStatistics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminApiStatisticsController extends Controller
{
    public function index(Request $request, ApiStatistics $statistics)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'to' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'interval' => ['nullable', Rule::in(['hour', 'day'])],
            'service' => ['nullable', Rule::in(['all', ...ApiMonitor::SERVICES])],
        ]);
        $now = CarbonImmutable::now('Europe/Paris');
        $from = isset($data['from']) ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['from'], 'Europe/Paris') : $now->subDays(6)->startOfDay();
        $to = isset($data['to']) ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['to'], 'Europe/Paris') : $now->addHour()->startOfHour();
        if ($to->lte($from) || $from->diffInHours($to) > 90 * 24 + 1) {
            throw ValidationException::withMessages(['to' => 'Choisis une fin après le début et une période de 90 jours maximum.']);
        }
        $interval = $data['interval'] ?? 'day';
        $service = $data['service'] ?? 'all';
        $report = $statistics->report($from, $to, $interval, $service);
        $services = ApiMonitor::SERVICES;
        return response()->view('admin.api-statistics', compact('from', 'to', 'interval', 'service', 'report', 'services'))
            ->header('Cache-Control', 'private, no-store');
    }
}
