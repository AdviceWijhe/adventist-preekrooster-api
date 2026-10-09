<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureRoosterOfGemeenteScope;
use App\Http\Middleware\EnsureStatistiekToegang;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TwoFactorVerified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            RateLimiter::for('two-factor', function (Request $request) {
                return Limit::perMinute(5)->by((string) ($request->user()?->id ?: $request->ip()));
            });
            RateLimiter::for('two-factor-resend', function (Request $request) {
                return Limit::perMinutes(10, 3)->by((string) ($request->user()?->id ?: $request->ip()));
            });
            RateLimiter::for('mail-test', function (Request $request) {
                return Limit::perMinute(3)->by((string) ($request->user()?->id ?: $request->ip()));
            });
        },
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('rooster:publiceer-automatisch')->dailyAt('06:00');
        $schedule->command('rooster:herinneringen')->weeklyOn(1, '08:00');
        $schedule->command('rooster:annuleer-openstaand')->dailyAt('07:00');
        $schedule->command('gebruikers:verwerk-inactiviteit')->dailyAt('05:00');
        $schedule->command('gebruikers:verwerk-avg-weigering')->dailyAt('05:15');
        $schedule->command('berichten:opschonen-prullenbak')->dailyAt('03:00');
        $schedule->command('changelog:opschonen')->dailyAt('04:00');
        $schedule->command('two-factor:opschonen')->dailyAt('03:30');
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            SecurityHeaders::class,
            SetLocale::class,
        ]);
        $middleware->web(prepend: [
            SecurityHeaders::class,
        ]);
        $middleware->alias([
            'two_factor' => TwoFactorVerified::class,
            'role' => CheckRole::class,
            'admin' => EnsureAdmin::class,
            'statistiek_toegang' => EnsureStatistiekToegang::class,
            'active' => EnsureUserIsActive::class,
            'rooster_scope' => EnsureRoosterOfGemeenteScope::class,
            'auth.session' => AuthenticateSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
