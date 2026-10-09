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

Route::get('/about', [\App\Http\Controllers\AboutController::class, 'show'])->name('about.show');
Route::middleware(['auth', 'admin.access', 'can:manage-site', 'throttle:admin'])->group(function () {
    Route::get('/admin/health', [\App\Http\Controllers\AdminHealthController::class, 'index'])->name('admin.health');
    Route::get('/admin/api-statistics', [\App\Http\Controllers\AdminApiStatisticsController::class, 'index'])->name('admin.api-statistics');
    Route::get('/admin/logs', [\App\Http\Controllers\AdminHealthController::class, 'logs'])->name('admin.logs');
    Route::get('/admin/user-data', [\App\Http\Controllers\AdminUserDataController::class, 'index'])->name('admin.user-data.index');
    Route::get('/admin/user-data/{user}', [\App\Http\Controllers\AdminUserDataController::class, 'show'])->name('admin.user-data.show');
    Route::get('/admin', [\App\Http\Controllers\AboutController::class, 'edit'])->name('admin.index');
    Route::get('/admin/users', [\App\Http\Controllers\AdminUserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users', [\App\Http\Controllers\AdminUserController::class, 'store'])->name('admin.users.store');
    Route::delete('/admin/users/{user}', [\App\Http\Controllers\AdminUserController::class, 'destroy'])->name('admin.users.destroy');
    Route::put('/admin/about', [\App\Http\Controllers\AboutController::class, 'update'])->name('admin.about.update');
    Route::get('/admin/notifications/email', [\App\Http\Controllers\AdminNotificationMailController::class, 'edit'])->name('admin.notification-mail.edit');
    Route::put('/admin/notifications/email', [\App\Http\Controllers\AdminNotificationMailController::class, 'update'])->name('admin.notification-mail.update');
    Route::post('/admin/notifications/email/test', [\App\Http\Controllers\AdminNotificationMailController::class, 'test'])->middleware('throttle:3,1')->name('admin.notification-mail.test');
});

// Page principale (formulaire)
Route::get('/', [SearchController::class, 'index'])->name('search.index');
Route::get('/home/releases', [SearchController::class, 'recentReleases'])->middleware('throttle:30,1')->name('home.releases');

// API JSON search + autocomplete
Route::get('/search', [SearchController::class, 'search'])->middleware('throttle:search')->name('search.api');
Route::get('/autocomplete', [SearchController::class, 'autocomplete'])->middleware('throttle:autocomplete')->name('search.autocomplete');

Route::get('/content/{type}/{id}', [\App\Http\Controllers\ContentController::class, 'show'])->where('type', 'movie|tv|person')->whereNumber('id')->middleware('throttle:60,1')->name('content.show');

// Popin (HTML via AJAX)
Route::get('/title/{type}/{id}', [SearchController::class, 'show'])->where('type', 'movie|tv')->whereNumber('id')->middleware('throttle:search')->name('title.show');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'throttle:member'])->group(function () {
    Route::get('/account/watched', [\App\Http\Controllers\WatchedTitleController::class, 'index'])->name('watched.index');
    Route::post('/watched/toggle', [\App\Http\Controllers\WatchedTitleController::class, 'toggle'])->middleware('throttle:60,1')->name('watched.toggle');
    Route::get('/account/availability', [\App\Http\Controllers\AvailabilityAlertController::class, 'index'])->name('availability.index');
    Route::patch('/account/availability/{alert}/read', [\App\Http\Controllers\AvailabilityAlertController::class, 'read'])->name('availability.read');
    Route::get('/account/contacts', [\App\Http\Controllers\ContactController::class, 'index'])->name('contacts.index');
    Route::post('/account/contacts/invite', [\App\Http\Controllers\ContactController::class, 'invite'])->middleware('throttle:10,1')->name('contacts.invite');
    Route::patch('/account/contacts/{connection}', [\App\Http\Controllers\ContactController::class, 'update'])->middleware('throttle:30,1')->name('contacts.update');
    Route::post('/account/contact-link', [\App\Http\Controllers\ContactController::class, 'rotate'])->name('contacts.rotate');
    Route::get('/account/contact-qr', [\App\Http\Controllers\ContactController::class, 'qr'])->name('contacts.qr');
    Route::get('/join/{token}', [\App\Http\Controllers\ContactController::class, 'join'])->where('token','[A-Za-z0-9]{48}')->name('contacts.join');
    Route::get('/account/recommendations', [\App\Http\Controllers\RecommendationController::class, 'index'])->name('recommendations.index');
    Route::get('/account/recommend', [\App\Http\Controllers\RecommendationController::class, 'compose'])->name('recommendations.compose');
    Route::post('/account/recommendations', [\App\Http\Controllers\RecommendationController::class, 'store'])->middleware('throttle:10,1')->name('recommendations.store');
    Route::get('/account/recommendations/{recommendation}', [\App\Http\Controllers\RecommendationController::class, 'show'])->name('recommendations.show');
    Route::patch('/account/recommendations/{recommendation}', [\App\Http\Controllers\RecommendationController::class, 'update'])->name('recommendations.update');


    Route::get('/notifications/push/key', [\App\Http\Controllers\PushSubscriptionController::class, 'key'])->name('push.key');
    Route::post('/notifications/push', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->middleware('throttle:20,1')->name('push.store');
    Route::delete('/notifications/push', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.destroy');
    Route::post('/notifications/push/status', [\App\Http\Controllers\PushSubscriptionController::class, 'status'])->middleware('throttle:20,1')->name('push.status');

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
