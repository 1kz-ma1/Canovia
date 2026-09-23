<?php

namespace App\Contracts;

use App\Data\FeatureAccessDecision;
use App\Enums\EntitlementSource;
use App\Enums\FeatureKey;
use App\Models\User;

interface EntitlementResolver
{
    public function source(): EntitlementSource;

    /**
     * Larger values run first. Future Premium/Coin/Gift/Sponsor resolvers can
     * therefore describe the strongest applicable access source before Free.
     */
    public function priority(): int;

    /**
     * Return a grant when this source gives the actor access.
     * Return null when this source has no applicable entitlement.
     *
     * @param array<string, mixed> $context
     */
    public function resolve(?User $actor, FeatureKey $feature, array $context = []): ?FeatureAccessDecision;
}
