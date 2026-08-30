<?php

namespace App\Services;

use Illuminate\Http\Request;

class MobileAppVersionService
{
    public function publicConfig(): array
    {
        return [
            'ios_available' => (bool) config('mobile_app.ios_available'),
            'android_available' => (bool) config('mobile_app.android_available'),
            'ios_min_version' => config('mobile_app.ios_min_version'),
            'android_min_version' => config('mobile_app.android_min_version'),
            'ios_store_url' => $this->storeUrlForPlatform('ios'),
            'android_store_url' => $this->storeUrlForPlatform('android'),
            'force_update' => (bool) config('mobile_app.force_update'),
            'message' => config('mobile_app.message'),
        ];
    }

    public function isPlatformAvailable(string $platform): bool
    {
        return match ($platform) {
            'ios' => (bool) config('mobile_app.ios_available'),
            'android' => (bool) config('mobile_app.android_available'),
            default => false,
        };
    }

    public function isUpdateRequired(string $platform, string $version): bool
    {
        if (! config('mobile_app.force_update')) {
            return false;
        }

        $minVersion = $this->minVersionForPlatform($platform);

        if (! $minVersion || $version === '') {
            return false;
        }

        return version_compare($version, $minVersion, '<');
    }

    public function minVersionForPlatform(string $platform): ?string
    {
        return match ($platform) {
            'ios' => config('mobile_app.ios_min_version'),
            'android' => config('mobile_app.android_min_version'),
            default => null,
        };
    }

    public function storeUrlForPlatform(string $platform): ?string
    {
        if (! $this->isPlatformAvailable($platform)) {
            return null;
        }

        return match ($platform) {
            'ios' => config('mobile_app.ios_store_url'),
            'android' => config('mobile_app.android_store_url'),
            default => null,
        };
    }

    /**
     * @return array{platform: string, version: string, update_required: bool, min_version: string|null, store_url: string|null}|null
     */
    public function resolveFromRequest(Request $request): ?array
    {
        $platform = strtolower((string) $request->header('X-App-Platform', ''));
        $version = (string) $request->header('X-App-Version', '');

        if (! in_array($platform, ['ios', 'android'], true) || $version === '') {
            return null;
        }

        return [
            'platform' => $platform,
            'version' => $version,
            'update_required' => $this->isUpdateRequired($platform, $version),
            'min_version' => $this->minVersionForPlatform($platform),
            'store_url' => $this->storeUrlForPlatform($platform),
        ];
    }
}
