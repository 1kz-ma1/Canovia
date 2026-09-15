<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanActivityLog;
use App\Models\User;

class PlanActivityService
{
    public function record(
        Plan $plan,
        ?User $user,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        array $metadata = [],
    ): void {
        if (! $plan->is_collaborative) {
            return;
        }

        PlanActivityLog::create([
            'plan_id' => $plan->id,
            'user_id' => $user?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
