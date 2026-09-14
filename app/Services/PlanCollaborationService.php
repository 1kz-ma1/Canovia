<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanMember;
use App\Models\User;
use Illuminate\Support\Str;

class PlanCollaborationService
{
    /**
     * Collaboration ownership is intentionally centralized here so a future
     * Premium entitlement can be inserted without rewriting controllers.
     */
    public function canOwnCollaborativePlan(User $user): bool
    {
        return true;
    }

    public function enable(Plan $plan): void
    {
        if (! $plan->user_id) {
            return;
        }

        $plan->forceFill([
            'is_collaborative' => true,
            'collaboration_join_code' => $plan->collaboration_join_code ?: $this->uniqueJoinCode(),
            'collaboration_share_token' => $plan->collaboration_share_token ?: $this->uniqueShareToken(),
        ])->save();
    }

    public function disable(Plan $plan): void
    {
        $plan->forceFill(['is_collaborative' => false])->save();
    }

    public function regenerateInvite(Plan $plan): void
    {
        $plan->forceFill([
            'collaboration_join_code' => $this->uniqueJoinCode(),
            'collaboration_share_token' => $this->uniqueShareToken(),
        ])->save();
    }

    public function addViewer(Plan $plan, User $user, ?User $invitedBy = null): PlanMember
    {
        if ((int) $plan->user_id === (int) $user->id) {
            throw new \InvalidArgumentException('Owner does not need a membership row.');
        }

        return PlanMember::firstOrCreate(
            ['plan_id' => $plan->id, 'user_id' => $user->id],
            [
                'role' => PlanMember::ROLE_VIEWER,
                'invited_by_user_id' => $invitedBy?->id,
                'joined_at' => now(),
            ]
        );
    }

    private function uniqueJoinCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $body = collect(range(1, 6))
                ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
                ->implode('');
            $code = 'CNV-' . $body;
        } while (Plan::where('collaboration_join_code', $code)->exists());

        return $code;
    }

    private function uniqueShareToken(): string
    {
        do {
            $token = Str::random(48);
        } while (Plan::where('collaboration_share_token', $token)->exists());

        return $token;
    }
}
