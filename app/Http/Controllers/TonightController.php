<?php

namespace App\Http\Controllers;

use App\Services\TonightService;
use App\Support\TonightPreferences;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TonightController extends Controller
{
    public function index(Request $request, TonightService $service)
    {
        $data = $request->validate([
            'suggest' => ['sometimes', 'in:1'], 'type' => ['sometimes', 'in:all,movie,tv'],
            'minutes' => ['sometimes', 'integer', Rule::in(TonightPreferences::DURATIONS)],
            'genre' => ['sometimes', Rule::in(array_keys(TonightPreferences::GENRES))],
            'mood' => ['sometimes', Rule::in(array_keys(TonightPreferences::MOODS))],
        ]);
        $preferences = array_replace(['type' => 'all', 'minutes' => 120, 'genre' => 'any', 'mood' => 'any'], $data);
        $preferences['minutes'] = (int) $preferences['minutes'];
        $suggestions = $request->has('suggest') ? $service->suggest($request->user(), $preferences) : ['results' => [], 'state' => 'initial'];
        return view('tonight', compact('preferences', 'suggestions'));
    }
}
