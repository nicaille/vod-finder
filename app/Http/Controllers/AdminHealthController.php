<?php

namespace App\Http\Controllers;

use App\Models\{ApiHealth, TaskRun, AlertDelivery, SocialDelivery, AvailabilityDelivery, User, WatchlistItem, TrackedSeries};
use App\Services\AdminLogReader;
use Illuminate\Http\Request;

class AdminHealthController extends Controller
{
    public function index()
    {
        $apis = ApiHealth::orderBy('service')->get();
        $tasks = TaskRun::orderBy('name')->get();
        $deliveries = collect(['Épisodes' => AlertDelivery::class, 'Contacts et recommandations' => SocialDelivery::class, 'Disponibilités' => AvailabilityDelivery::class])->map(function ($model) {
            return [
                'sent' => $model::whereNotNull('sent_at')->count(),
                'retry' => $model::whereNull('sent_at')->whereBetween('attempts', [1, 4])->count(),
                'exhausted' => $model::whereNull('sent_at')->where('attempts', '>=', 5)->count(),
            ];
        });
        $counts = ['Utilisateurs' => User::count(), 'Titres en playlist' => WatchlistItem::count(), 'Séries suivies' => TrackedSeries::whereHas('follows')->count()];
        $lastAvailabilityCheck = WatchlistItem::max('availability_checked_at');
        return response()->view('admin.health', compact('apis', 'tasks', 'deliveries', 'counts', 'lastAvailabilityCheck'))->header('Cache-Control', 'private, no-store');
    }

    public function logs(Request $request, AdminLogReader $reader)
    {
        $data = $request->validate(['file' => ['nullable', 'string', 'max:100'], 'level' => ['nullable', 'in:all,debug,info,notice,warning,error,critical,alert,emergency']]);
        $files = $reader->files();
        $file = $data['file'] ?? (in_array('laravel.log', $files, true) ? 'laravel.log' : ($files[0] ?? null));
        $level = $data['level'] ?? 'all';
        $text = $file ? $reader->read($file, $level) : 'Aucun fichier de logs disponible.';
        return response()->view('admin.logs', compact('files', 'file', 'level', 'text'))->header('Cache-Control', 'private, no-store');
    }
}
