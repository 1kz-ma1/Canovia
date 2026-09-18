<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        URL::forceScheme('https');

        $canonicalUrl = rtrim((string) config('canovia.canonical_url', ''), '/');
        if ($canonicalUrl !== '') {
            URL::forceRootUrl($canonicalUrl);
        }
    }
}
