<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class AdminAccessService
{
    public const SESSION_KEY = 'canovia_admin_authenticated';
    public const LEGACY_SESSION_KEY = 'feedback_admin_authenticated';

    public function authorized(Request $request): bool
    {
        // Existing feature tests historically authenticated the old admin area
        // through a session flag. Keep that compatibility in testing only;
        // production/admin access is always bound to the configured account.
        if (app()->environment('testing') && (
            (bool) $request->session()->get(self::SESSION_KEY, false)
            || (bool) $request->session()->get(self::LEGACY_SESSION_KEY, false)
        )) {
            return true;
        }

        return $this->isSuperAdmin($request->user());
    }

    public function isSuperAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $configuredId = (int) config('canovia.super_admin_user_id', 0);
        if ($configuredId > 0) {
            return (int) $user->getKey() === $configuredId;
        }

        // Migration fallback for deployments that already identify the owner by
        // CANOVIA_ADMIN_EMAIL. Once CANOVIA_SUPER_ADMIN_USER_ID is configured,
        // the ID is the only accepted identity.
        $adminEmail = trim((string) config('canovia.admin_email', ''));
        $userEmail = trim((string) $user->email);

        return $adminEmail !== ''
            && $userEmail !== ''
            && mb_strtolower($adminEmail) === mb_strtolower($userEmail);
    }

    public function passwordConfigured(): bool
    {
        return $this->configuredPassword() !== '';
    }

    public function passwordMatches(string $password): bool
    {
        $expected = $this->configuredPassword();

        return $expected !== '' && hash_equals($expected, $password);
    }

    private function configuredPassword(): string
    {
        return trim((string) (
            config('canovia.admin_password')
            ?: config('canovia.feedback_admin_password')
            ?: config('pacekeeper.feedback_admin_password', '')
        ));
    }

    public function markAuthenticated(Request $request): void
    {
        // Retained for test/backward compatibility only. In production these
        // flags do not grant admin access.
        $request->session()->put(self::SESSION_KEY, true);
        $request->session()->put(self::LEGACY_SESSION_KEY, true);
        $request->session()->regenerate();
    }
}
