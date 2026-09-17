<?php

namespace App\Services;

use App\Models\FutureMemo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FutureMemoService
{
    public const GUEST_COOKIE = 'canovia_future_memo_token';

    public function all(Request $request, bool $onlyAiEnabled = false): Collection
    {
        $query = FutureMemo::query();

        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        } else {
            $token = $request->cookie(self::GUEST_COOKIE);
            if (! is_string($token) || $token === '') {
                return collect();
            }
            $query->whereNull('user_id')->where('guest_token_hash', hash('sha256', $token));
        }

        if ($onlyAiEnabled) {
            $query->where('use_for_ai', true);
        }

        return $query->orderBy('sort_order')->latest('updated_at')->latest('id')->get();
    }

    public function create(Request $request, array $attributes): FutureMemo
    {
        $attributes['user_id'] = $request->user()?->id;
        $attributes['guest_token_hash'] = null;

        if (! $request->user()) {
            $token = $this->guestToken($request, create: true);
            $attributes['guest_token_hash'] = hash('sha256', $token);
        }

        $attributes['sort_order'] = (int) ($attributes['sort_order'] ?? FutureMemo::query()
            ->when($request->user(), fn ($q) => $q->where('user_id', $request->user()->id))
            ->when(! $request->user(), function ($q) use ($request) {
                $token = $this->guestToken($request, create: false);
                $q->whereNull('user_id')->where('guest_token_hash', $token ? hash('sha256', $token) : '__none__');
            })
            ->max('sort_order')) + 1;

        return FutureMemo::create($attributes);
    }

    public function authorize(Request $request, FutureMemo $memo): void
    {
        if ($request->user() && $memo->user_id !== null && (int) $memo->user_id === (int) $request->user()->id) {
            return;
        }

        if ($memo->user_id === null) {
            $token = $this->guestToken($request, create: false);
            if ($token && is_string($memo->guest_token_hash) && hash_equals($memo->guest_token_hash, hash('sha256', $token))) {
                return;
            }
        }

        abort(403, 'この未来メモを編集する権限がありません。');
    }

    public function claimGuestMemos(Request $request, User $user): int
    {
        $token = $this->guestToken($request, create: false);
        if (! $token) {
            return 0;
        }

        $count = FutureMemo::query()
            ->whereNull('user_id')
            ->where('guest_token_hash', hash('sha256', $token))
            ->update([
                'user_id' => $user->id,
                'guest_token_hash' => null,
                'updated_at' => now(),
            ]);

        if ($count > 0) {
            cookie()->queue(cookie()->forget(self::GUEST_COOKIE));
        }

        return $count;
    }

    public function promptContext(Request $request, ?array $ids = null): string
    {
        $memos = $this->all($request, true);
        if ($ids !== null) {
            $wanted = collect($ids)->map(fn ($id) => (int) $id)->filter()->all();
            $memos = $memos->whereIn('id', $wanted)->values();
        }

        if ($memos->isEmpty()) {
            return '未登録。必要な場合は現在の会話で本人へ確認してください。';
        }

        return $memos->map(fn (FutureMemo $memo) => sprintf(
            '- [%s / %s] %s',
            $memo->kindLabel(),
            $memo->categoryLabel(),
            trim($memo->content)
        ))->implode("\n");
    }

    private function guestToken(Request $request, bool $create): ?string
    {
        $existing = $request->cookie(self::GUEST_COOKIE);
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        if (! $create) {
            return null;
        }

        $token = Str::random(64);
        cookie()->queue(self::GUEST_COOKIE, $token, 60 * 24 * 365, '/', null, app()->environment('production') || $request->isSecure(), true, false, 'lax');

        return $token;
    }
}
