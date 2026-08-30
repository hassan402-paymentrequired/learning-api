<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Force update
    |--------------------------------------------------------------------------
    |
    | When true, mobile apps below the minimum version are blocked in-app
    | and receive HTTP 426 from the API (when X-App-Platform is sent).
    |
    */

    'force_update' => env('MOBILE_FORCE_UPDATE', true),

    /*
    |--------------------------------------------------------------------------
    | Store availability
    |--------------------------------------------------------------------------
    |
    | Single source of truth for whether each store listing is live. Flip
    | MOBILE_ANDROID_AVAILABLE to true (and set a real MOBILE_ANDROID_STORE_URL)
    | once the Android app clears Play Store review — no web/mobile redeploy
    | needed, both read this from GET /api/app-version.
    |
    */

    'ios_available' => env('MOBILE_IOS_AVAILABLE', true),

    'android_available' => env('MOBILE_ANDROID_AVAILABLE', false),

    'ios_min_version' => env('MOBILE_IOS_MIN_VERSION', '1.0.3'),

    'android_min_version' => env('MOBILE_ANDROID_MIN_VERSION', '1.0.3'),

    'ios_store_url' => env(
        'MOBILE_IOS_STORE_URL',
        'https://apps.apple.com/us/app/stepra-prep/id6789680121'
    ),

    'android_store_url' => env(
        'MOBILE_ANDROID_STORE_URL',
        'https://play.google.com/store/apps/details?id=com.oluwafemiomoope.stepra'
    ),

    'message' => env(
        'MOBILE_UPDATE_MESSAGE',
        'A new version of Stepra is available. Please update to continue.'
    ),

];
