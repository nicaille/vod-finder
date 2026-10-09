<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    // protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('search', fn (Request $r) => [
            Limit::perMinute(60)->by('ip:'.$r->ip()),
            Limit::perMinute($r->user() ? 40 : 20)->by('visitor:'.($r->user()?->id ?? $r->ip())),
        ]);
        RateLimiter::for('autocomplete', fn (Request $r) => [
            Limit::perMinute(120)->by('ip:'.$r->ip()),
            Limit::perMinute(60)->by('visitor:'.($r->user()?->id ?? $r->ip())),
        ]);
        RateLimiter::for('member', fn (Request $r) => Limit::perMinute(120)->by('user:'.$r->user()->id));
        RateLimiter::for('admin', function (Request $r) {
            $limits = [Limit::perMinute(120)->by('read:'.$r->user()->id)];
            if (!$r->isMethod('GET') && !$r->isMethod('HEAD')) $limits[] = Limit::perMinute(20)->by('write:'.$r->user()->id);
            return $limits;
        });
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(20)->by('ip:'.$r->ip()),
            Limit::perMinute(10)->by('email:'.$this->emailKey($r)),
        ]);
        RateLimiter::for('registration', fn (Request $r) => [
            Limit::perMinute(3)->by('minute:'.$r->ip()),
            Limit::perHour(10)->by('hour:'.$r->ip()),
        ]);
        RateLimiter::for('password-recovery', fn (Request $r) => [
            Limit::perMinute(5)->by('ip:'.$r->ip()),
            Limit::perHour(5)->by('email:'.$this->emailKey($r)),
        ]);
        RateLimiter::for('password-confirmation', fn (Request $r) => Limit::perMinute(5)->by('user:'.$r->user()->id));
    }

    private function emailKey(Request $request): string
    {
        $email = $request->input('email');
        return hash('sha256', is_string($email) ? mb_strtolower(trim($email)) : 'invalid');
    }
}
