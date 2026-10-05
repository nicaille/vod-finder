<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WatchlistController;
use App\Http\Controllers\MediaListController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\AccountController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Page principale (formulaire)
Route::get('/', [SearchController::class, 'index'])->name('search.index');

// API JSON search + autocomplete
Route::get('/search', [SearchController::class, 'search'])->name('search.api');
Route::get('/autocomplete', [SearchController::class, 'autocomplete'])->name('search.autocomplete');

// Popin (HTML via AJAX)
Route::get('/title/{type}/{id}', [SearchController::class, 'show'])->name('title.show');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::get('/series', [\App\Http\Controllers\SeriesFollowController::class, 'index'])->name('series.index');
    Route::post('/series', [\App\Http\Controllers\SeriesFollowController::class, 'store'])->name('series.store');
    Route::patch('/series/follows/{follow}', [\App\Http\Controllers\SeriesFollowController::class, 'update'])->name('series.update');
    Route::delete('/series/follows/{follow}', [\App\Http\Controllers\SeriesFollowController::class, 'destroy'])->name('series.destroy');
    Route::patch('/series/alerts/{alert}/read', [\App\Http\Controllers\SeriesFollowController::class, 'read'])->name('series.alerts.read');

    // Profil Breeze
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Watchlist
    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('/watchlist/toggle', [WatchlistController::class, 'toggle'])->name('watchlist.toggle');

    // Mes listes
    Route::get('/lists', [MediaListController::class, 'index'])->name('lists.index');

    // CRUD Listes
    Route::post('/lists', [MediaListController::class, 'store'])->name('lists.store');
    Route::put('/lists/{list}', [MediaListController::class, 'update'])->name('lists.update');
    Route::delete('/lists/{list}', [MediaListController::class, 'destroy'])->name('lists.destroy');

    // Items dans une liste
    Route::post('/lists/{list}/items', [MediaListController::class, 'addItem'])->name('lists.items.store');
    Route::delete('/lists/{list}/items/{item}', [MediaListController::class, 'destroyItem'])->name('lists.items.destroy');

    // Favoris
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    
    // Mon compte
    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/account', [AccountController::class, 'update'])->name('account.update');
});

require __DIR__ . '/auth.php';
