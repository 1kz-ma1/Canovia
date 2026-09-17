<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function complete(Request $request)
    {
        $version = (int) config('canovia.onboarding_version', 1);
        $user = $request->user();

        if ($user && (int) $user->onboarding_version < $version) {
            $user->forceFill([
                'onboarding_version' => $version,
                'onboarding_completed_at' => now(),
                'onboarding_skipped_at' => null,
            ])->save();
        }

        return response()->noContent();
    }

    public function skip(Request $request)
    {
        $version = (int) config('canovia.onboarding_version', 1);
        $user = $request->user();

        if ($user && (int) $user->onboarding_version < $version) {
            $user->forceFill([
                'onboarding_version' => $version,
                'onboarding_completed_at' => null,
                'onboarding_skipped_at' => now(),
            ])->save();
        }

        return response()->noContent();
    }
}
