<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PwaHandoff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PwaHandoffService
{
    /**
     * Create a short-lived one-time capability that can move the current
     * Canovia identity from a browser tab into an installed PWA context.
     *
     * iOS can isolate Safari and Home Screen storage. A normal session cookie
     * therefore is not enough: the manifest/start URL needs an explicit bridge.
     */
    public function create(Request $request): string
    {
        $rawToken = Str::random(72);
        $guestPlanIds = $request->user() ? [] : $this->ownedGuestPlanIds($request);

        PwaHandoff::create([
            'token_hash' => hash('sha256', $rawToken),
            'user_id' => $request->user()?->id,
            'guest_plan_ids' => $guestPlanIds,
            'expires_at' => now()->addMinutes(30),
        ]);

        // Opportunistic cleanup keeps the table tiny without requiring a cron.
        if (random_int(1, 100) <= 3) {
            PwaHandoff::query()
                ->where(function ($query) {
                    $query->where('expires_at', '<', now()->subDay())
                        ->orWhereNotNull('used_at');
                })
                ->delete();
        }

        return $rawToken;
    }

    /**
     * Consume the bridge and establish the same account/Guest ownership in the
     * currently-open context. The token is single-use and expires quickly.
     *
     * @return array{type:string,count:int}
     */
    public function consume(Request $request, string $rawToken): array
    {
        return DB::transaction(function () use ($request, $rawToken) {
            /** @var PwaHandoff|null $handoff */
            $handoff = PwaHandoff::query()
                ->where('token_hash', hash('sha256', $rawToken))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $handoff) {
                return ['type' => 'invalid', 'count' => 0];
            }

            $result = ['type' => 'guest', 'count' => 0];

            if ($handoff->user_id) {
                $user = $handoff->user()->first();
                if ($user) {
                    // "remember" is intentional here. The installed app is a
                    // private device context and should not require login on
                    // every launch just because the session row expired.
                    Auth::guard('web')->login($user, true);
                    $request->session()->regenerate();
                    $result = ['type' => 'account', 'count' => 1];
                }
            } else {
                $planIds = array_values(array_unique(array_filter(
                    array_map('intval', $handoff->guest_plan_ids ?? []),
                    fn (int $id) => $id > 0
                )));

                $plans = Plan::query()
                    ->whereNull('user_id')
                    ->whereIn('id', $planIds)
                    ->get(['id', 'owner_token']);

                foreach ($plans as $plan) {
                    cookie()->queue(
                        'pace_keeper_owner_token_' . $plan->id,
                        $plan->owner_token,
                        60 * 24 * 365,
                        '/',
                        null,
                        app()->environment('production') || $request->isSecure(),
                        true,
                        false,
                        'lax'
                    );
                }

                $result = ['type' => 'guest', 'count' => $plans->count()];
            }

            $handoff->forceFill(['used_at' => now()])->save();

            return $result;
        });
    }

    /** @return array<int> */
    private function ownedGuestPlanIds(Request $request): array
    {
        $ids = [];

        foreach ($request->cookies->all() as $name => $token) {
            if (! preg_match('/^pace_keeper_owner_token_(\d+)$/', (string) $name, $matches)) {
                continue;
            }

            $planId = (int) $matches[1];
            if ($planId <= 0 || ! is_string($token) || $token === '') {
                continue;
            }

            $plan = Plan::query()
                ->whereKey($planId)
                ->whereNull('user_id')
                ->first(['id', 'owner_token']);

            if ($plan && is_string($plan->owner_token) && hash_equals($plan->owner_token, $token)) {
                $ids[] = $planId;
            }
        }

        return array_values(array_unique($ids));
    }
}
