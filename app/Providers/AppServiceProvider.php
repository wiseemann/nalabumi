<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Vite::createAssetPathsUsing(function (string $path, ?bool $secure) {
            return '/' . ltrim($path, '/');
        });
    }
}
