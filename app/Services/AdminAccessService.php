<?php

namespace App\Services;

use Illuminate\Http\Request;

class AdminAccessService
{
    public const SESSION_KEY = 'canovia_admin_authenticated';
    public const LEGACY_SESSION_KEY = 'feedback_admin_authenticated';

    public function authorized(Request $request): bool
    {
        if (
            (bool) $request->session()->get(self::SESSION_KEY, false)
            || (bool) $request->session()->get(self::LEGACY_SESSION_KEY, false)
        ) {
            return true;
        }

        $adminEmail = trim((string) config('canovia.admin_email', ''));
        $userEmail = trim((string) ($request->user()?->email ?? ''));

        return $adminEmail !== ''
            && $userEmail !== ''
            && mb_strtolower($adminEmail) === mb_strtolower($userEmail);
    }

    public function passwordConfigured(): bool
    {
        return filled(config('canovia.admin_password'));
    }

    public function passwordMatches(string $password): bool
    {
        $expected = (string) config('canovia.admin_password', '');

        return $expected !== '' && hash_equals($expected, $password);
    }

    public function markAuthenticated(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, true);

        // Keep the old key during the transition so an already-open Feedback
        // admin tab and older deployments remain compatible.
        $request->session()->put(self::LEGACY_SESSION_KEY, true);
        $request->session()->regenerate();
    }
}
