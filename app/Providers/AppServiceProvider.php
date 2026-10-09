<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\App\Services\ApiMonitor::class);
        $this->app->singleton(\App\Services\CacheMonitor::class);
        $this->app->singleton(\App\Services\ApiCache::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        $this->app->terminating(fn () => app(\App\Services\CacheMonitor::class)->flush());
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Http\Client\Events\ResponseReceived::class, function ($event) {
            $headers = array_change_key_case($event->response->headers(), CASE_LOWER);
            app(\App\Services\ApiMonitor::class)->record($event->request->url(), $event->response->status(), $headers);
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Http\Client\Events\ConnectionFailed::class, function ($event) {
            app(\App\Services\ApiMonitor::class)->record($event->request->url(), null);
        });
    }
}
