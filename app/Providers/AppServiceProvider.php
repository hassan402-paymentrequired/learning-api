<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();

        $this->resolveFrontendUrl();
    }

    /**
     * Ensure app.frontend_url is set.
     * Prefer FRONTEND_URL; otherwise derive from APP_URL (admin.* → apex domain).
     */
    private function resolveFrontendUrl(): void
    {
        $configured = config('app.frontend_url');
        if (is_string($configured) && trim($configured) !== '') {
            config(['app.frontend_url' => rtrim($configured, '/')]);

            return;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        $host = parse_url($appUrl, PHP_URL_HOST);
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'https';

        if (is_string($host) && str_starts_with($host, 'admin.')) {
            config(['app.frontend_url' => $scheme . '://' . substr($host, strlen('admin.'))]);

            return;
        }

        config([
            'app.frontend_url' => $appUrl !== '' ? $appUrl : 'http://localhost:5173',
        ]);
    }
}
