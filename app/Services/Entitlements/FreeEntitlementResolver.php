<?php

namespace App\Services\Entitlements;

use App\Contracts\EntitlementResolver;
use App\Data\FeatureAccessDecision;
use App\Enums\EntitlementSource;
use App\Enums\FeatureKey;
use App\Models\User;

final class FreeEntitlementResolver implements EntitlementResolver
{
    public function source(): EntitlementSource
    {
        return EntitlementSource::Free;
    }

    public function priority(): int
    {
        return 0;
    }

    public function resolve(?User $actor, FeatureKey $feature, array $context = []): ?FeatureAccessDecision
    {
        $configured = config('entitlements.features.'.$feature->value.'.free');
        $defaultAllow = (string) config('entitlements.default_policy', 'allow') === 'allow';
        $free = $configured === null ? $defaultAllow : (bool) $configured;

        if (! $free) {
            return null;
        }

        return FeatureAccessDecision::allow(
            $feature,
            EntitlementSource::Free,
            'free_access',
        );
    }
}
