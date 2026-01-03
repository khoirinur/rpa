<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    'name' => env('APP_NAME', 'Laravel'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),

    'asset_url' => env('ASSET_URL'),

    'timezone' => 'Asia/Jakarta',

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'company_name' => env('company_name', 'PT Surya Kencana Slaughterhouse'),
    'company_address' => env('company_address', 'Jl. Totok Kerot, Suko, Menang, Kec. Pagu Kab. Kediri Jawa Timur 64183 Indonesia'),
    'company_city' => env('company_city', 'Kediri'),
    'company_phone' => env('company_phone', '+62 812 1579 9522'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    'providers' => ServiceProvider::defaultProviders()->merge([
        // Package Service Providers...
    ])->merge([
        // Application Service Providers...
        App\Providers\AppServiceProvider::class,
        App\Providers\Filament\AdminPanelProvider::class,
    ])->merge([
        // Added Service Providers (Do not remove this line)...
    ])->toArray(),

    'aliases' => Facade::defaultAliases()->merge([
        // 'Example' => App\Facades\Example::class,
    ])->toArray(),

];
