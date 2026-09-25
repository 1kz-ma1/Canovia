<?php

namespace App\Services\Entitlements;

use App\Contracts\EntitlementResolver;
use App\Data\FeatureAccessDecision;
use App\Enums\EntitlementSource;
use App\Enums\FeatureKey;
use App\Models\User;
use App\Services\ProductGrantService;

final class GiftProductGrantEntitlementResolver implements EntitlementResolver
{
    public function __construct(
        private readonly ProductGrantService $grants,
    ) {}

    public function source(): EntitlementSource
    {
        return EntitlementSource::Gift;
    }

    public function priority(): int
    {
        return 200;
    }

    public function resolve(?User $actor, FeatureKey $feature, array $context = []): ?FeatureAccessDecision
    {
        if (! $actor) {
            return null;
        }

        $match = $this->grants->grantForFeature($actor, $feature, ['gift']);
        if (! $match) {
            return null;
        }

        $grant = $match['grant'];

        return FeatureAccessDecision::allow(
            $feature,
            EntitlementSource::Gift,
            'product_grant',
            [
                'product_key' => $grant->product_key->value,
                'effective_product_key' => $match['effective_product']->value,
                'product_grant_id' => (int) $grant->id,
                'grant_source' => (string) $grant->source,
            ],
        );
    }
}
