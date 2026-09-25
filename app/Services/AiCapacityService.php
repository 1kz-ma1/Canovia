<?php

namespace App\Services;

use App\Enums\ProductKey;
use App\Models\User;

class AiCapacityService
{
    public function __construct(
        private readonly ProductGrantService $grants,
    ) {}

    public function tierFor(?User $user): string
    {
        if ($user && $this->grants->hasEffectiveProduct($user, ProductKey::AiCapacityBoost)) {
            return 'boosted';
        }

        return (string) config('economy.ai_capacity.default', 'standard');
    }

    public function policyFor(?User $user): array
    {
        $tier = $this->tierFor($user);

        return [
            'tier' => $tier,
            'policy' => (array) config('economy.ai_capacity.tiers.'.$tier, []),
        ];
    }
}
