<?php

namespace App\Services;

use App\Enums\ProductKey;
use App\Models\User;

class AiCapacityService
{
    public function __construct(
        private readonly ProductGrantService $grants,
        private readonly AdminAccessService $adminAccess,
        private readonly AdminPreviewContext $adminPreview,
    ) {}

    public function tierFor(?User $user): string
    {
        if ($user && $this->adminAccess->isSuperAdmin($user)) {
            // Admin mode is intentionally unrestricted for product verification.
            // Free/Premium preview uses the normal standard capacity because
            // Premium Core does not include AI Capacity Boost.
            return $this->adminPreview->mode($user) === null
                ? 'boosted'
                : (string) config('economy.ai_capacity.default', 'standard');
        }

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
