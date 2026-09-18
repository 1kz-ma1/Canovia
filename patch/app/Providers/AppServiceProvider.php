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
        if (config('performance.enabled')) {
            \Illuminate\Support\Facades\DB::listen(function (\Illuminate\Database\Events\QueryExecuted $event): void {
                $metrics = request()->attributes->get(\App\Support\RequestPerformance::ATTRIBUTE);
                if ($metrics instanceof \App\Support\RequestPerformance) {
                    $metrics->add($event);
                }
            });
        }

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
