<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

final class AdminPreviewContext
{
    public const SESSION_KEY = 'canovia_admin_preview_mode';

    public function __construct(
        private readonly AdminAccessService $adminAccess,
    ) {}

    public function mode(?User $user = null): ?string
    {
        $user ??= request()->user();

        if (! $this->adminAccess->isSuperAdmin($user)) {
            return null;
        }

        $request = request();
        if (! $request->hasSession()) {
            return null;
        }

        $mode = $request->session()->get(self::SESSION_KEY);

        return in_array($mode, ['free', 'premium'], true) ? $mode : null;
    }

    public function set(Request $request, string $mode): void
    {
        abort_unless($this->adminAccess->authorized($request), 403);

        if ($mode === 'admin') {
            $request->session()->forget(self::SESSION_KEY);

            return;
        }

        abort_unless(in_array($mode, ['free', 'premium'], true), 422);
        $request->session()->put(self::SESSION_KEY, $mode);
    }
}
