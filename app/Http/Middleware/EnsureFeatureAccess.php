<?php

namespace App\Http\Middleware;

use App\Enums\FeatureKey;
use App\Services\FeatureAccessService;
use Closure;
use Illuminate\Database\Eloquent\Model;
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

        $context = [
            'route_name' => $request->route()?->getName(),
        ];

        foreach (['plan', 'task'] as $parameter) {
            $value = $request->route($parameter);

            if ($value instanceof Model) {
                $context[$parameter.'_id'] = (int) $value->getKey();
            } elseif (is_numeric($value)) {
                $context[$parameter.'_id'] = (int) $value;
            }
        }

        $this->access->authorizeUse($request->user(), $featureKey, $context);

        return $next($request);
    }
}
