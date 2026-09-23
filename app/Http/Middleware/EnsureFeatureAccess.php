<?php

namespace App\Http\Middleware;

use App\Enums\FeatureKey;
use App\Services\FeatureAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureFeatureAccess
{
    public function __construct(
        private readonly FeatureAccessService $access,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $featureKey = FeatureKey::tryFrom($feature);
        abort_unless($featureKey !== null, 500, 'Unknown Canovia feature key.');

        $this->access->authorizeUse($request->user(), $featureKey, [
            'route_name' => $request->route()?->getName(),
        ]);

        return $next($request);
    }
}
