<?php

use App\Http\Middleware\EnsureMobileAppVersion;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->api(append: [
            EnsureMobileAppVersion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('streak:send-morning-reminders')->hourly();
        $schedule->command('subscriptions:send-expiry-reminders')
            ->dailyAt('09:00')
            ->timezone('Africa/Lagos');
        $schedule->command('marketing:send-study-tips')
            ->weeklyOn(2, '10:00')
            ->timezone('Africa/Lagos');
        $schedule->command('marketing:send-reengagement')
            ->dailyAt('11:00')
            ->timezone('Africa/Lagos');
    })->create();
