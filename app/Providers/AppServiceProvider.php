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
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Http\Client\Events\ResponseReceived::class, function ($event) {
            app(\App\Services\ApiMonitor::class)->record($event->request->url(), $event->response->status());
        });
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Http\Client\Events\ConnectionFailed::class, function ($event) {
            app(\App\Services\ApiMonitor::class)->record($event->request->url(), null);
        });
    }
}
