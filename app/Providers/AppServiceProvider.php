<?php

namespace App\Providers;

use App\Services\Entitlements\FreeEntitlementResolver;
use App\Services\FeatureAccessService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FreeEntitlementResolver::class);
        $this->app->tag([FreeEntitlementResolver::class], 'canovia.entitlement_resolvers');

        $this->app->singleton(
            FeatureAccessService::class,
            fn ($app) => new FeatureAccessService($app->tagged('canovia.entitlement_resolvers')),
        );
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
