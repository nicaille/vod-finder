<?php

namespace App\Http\Controllers;

use App\Services\{RecommendationContent, TmdbService};
class ContentController extends Controller
{
    public function show(string $type, int $id, RecommendationContent $service, TmdbService $tmdb)
    {
        $content = $service->get($type, $id);
        abort_unless($content, 503, 'La fiche est momentanément indisponible.');
        $details = $type === 'person' ? $tmdb->getPersonProfile($id) : $tmdb->getDetails($id, $type);
        $credits = $type === 'person' ? $tmdb->getPersonCombinedCredits($id) : $details['credits'] ?? [];
        $works = collect(array_merge($credits['cast'] ?? [], $credits['crew'] ?? []))->filter(fn($work) => in_array($work['media_type'] ?? null, ['movie', 'tv'], true))->unique(fn($work) => $work['media_type'] . ':' . $work['id'])->take(24);
        $userLists = auth()->check() ? auth()->user()->lists()->orderBy('name')->get() : collect();
        $isFavorite = auth()->check() && auth()->user()->favorites()->where('tmdb_id', $id)->where('type', $type)->exists();
        $inUserList = auth()->check() && auth()->user()->lists()->whereHas('items', fn ($query) => $query->where('tmdb_id', $id)->where('type', $type))->exists();
        $isTv = $type === 'tv';
        return view('social.content', compact('type', 'id', 'content', 'details', 'works', 'userLists', 'isFavorite', 'inUserList', 'isTv'));
    }
}
