<?php

namespace App\Services;

use App\Contracts\EntitlementResolver;
use App\Data\FeatureAccessDecision;
use App\Enums\FeatureKey;
use App\Models\User;

final class FeatureAccessService
{
    /** @var array<int, EntitlementResolver> */
    private array $resolvers;

    /**
     * @param iterable<EntitlementResolver> $resolvers
     */
    public function __construct(iterable $resolvers)
    {
        $items = is_array($resolvers) ? $resolvers : iterator_to_array($resolvers);

        usort(
            $items,
            fn (EntitlementResolver $left, EntitlementResolver $right) => $right->priority() <=> $left->priority()
        );

        $this->resolvers = $items;
    }

    /**
     * The single boundary feature code should use when it needs to know whether
     * an actor may use a published feature. Feature flags are intentionally not
     * evaluated here.
     *
     * @param array<string, mixed> $context
     */
    public function resolveAccess(?User $actor, FeatureKey $feature, array $context = []): FeatureAccessDecision
    {
        foreach ($this->resolvers as $resolver) {
            $decision = $resolver->resolve($actor, $feature, $context);

            if ($decision?->allowed) {
                return $decision;
            }
        }

        return FeatureAccessDecision::deny($feature);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function canUse(?User $actor, FeatureKey $feature, array $context = []): bool
    {
        return $this->resolveAccess($actor, $feature, $context)->allowed;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function authorizeUse(?User $actor, FeatureKey $feature, array $context = []): FeatureAccessDecision
    {
        $decision = $this->resolveAccess($actor, $feature, $context);

        abort_unless($decision->allowed, 403, 'この機能を利用する権限がありません。');

        return $decision;
    }
}
