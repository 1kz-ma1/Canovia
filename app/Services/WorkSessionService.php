<?php

namespace App\Services;

use App\Models\WorkSession;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class WorkSessionService
{
    public function pause(WorkSession $session): WorkSession
    {
        if ($session->status !== 'active') {
            throw ValidationException::withMessages(['session' => '作業中のセッションだけ一時停止できます。']);
        }

        $session->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        return $session->refresh();
    }

    public function resume(WorkSession $session): WorkSession
    {
        if ($session->status !== 'paused' || ! $session->paused_at) {
            throw ValidationException::withMessages(['session' => '一時停止中のセッションだけ再開できます。']);
        }

        $additionalPause = max(0, (int) $session->paused_at->diffInSeconds(now()));
        $session->update([
            'status' => 'active',
            'paused_seconds' => (int) $session->paused_seconds + $additionalPause,
            'paused_at' => null,
        ]);

        return $session->refresh();
    }

    /**
     * Finish a work session. V37 accepts explicit adjustments so elapsed wall
     * time is never assumed to be work time after a long app/browser absence.
     *
     * Supported options:
     * - ended_at: CarbonInterface — finish at a past time ("I stopped when I left")
     * - additional_paused_seconds: int — exclude reviewed break/away time
     * - manual_active_seconds: int — explicit corrected work time
     */
    public function finish(WorkSession $session, string $status = 'completed', array $options = []): array
    {
        if (! in_array($session->status, ['active', 'paused'], true)) {
            throw ValidationException::withMessages(['session' => 'この作業セッションはすでに終了しています。']);
        }

        $metrics = $this->previewFinish($session, $options);

        $session->update([
            'status' => $status,
            'ended_at' => $metrics['ended_at'],
            'paused_at' => null,
            'paused_seconds' => $metrics['paused_seconds'],
            'actual_seconds' => $metrics['active_seconds'],
        ]);

        return [
            'active_seconds' => $metrics['active_seconds'],
            'wall_seconds' => $metrics['wall_seconds'],
            'paused_seconds' => $metrics['paused_seconds'],
            'actual_minutes' => max(1, (int) ceil($metrics['active_seconds'] / 60)),
            'ended_at' => $metrics['ended_at'],
        ];
    }

    public function previewFinish(WorkSession $session, array $options = []): array
    {
        $now = now();
        $endedAt = $options['ended_at'] ?? $now;
        if (! $endedAt instanceof CarbonInterface) {
            $endedAt = $now;
        }

        if ($endedAt->lt($session->started_at)) {
            $endedAt = $session->started_at->copy()->addSecond();
        }
        if ($endedAt->gt($now)) {
            $endedAt = $now;
        }

        $wallSeconds = max(1, (int) $session->started_at->diffInSeconds($endedAt));
        $pausedSeconds = max(0, (int) $session->paused_seconds);

        if ($session->status === 'paused' && $session->paused_at && $session->paused_at->lt($endedAt)) {
            $pausedSeconds += max(0, (int) $session->paused_at->diffInSeconds($endedAt));
        }

        $additionalPaused = max(0, (int) ($options['additional_paused_seconds'] ?? 0));
        $pausedSeconds += $additionalPaused;
        $pausedSeconds = min($pausedSeconds, max(0, $wallSeconds - 1));

        $manualActiveSeconds = $options['manual_active_seconds'] ?? null;
        if ($manualActiveSeconds !== null) {
            $activeSeconds = min($wallSeconds, max(1, (int) $manualActiveSeconds));
            // Keep accounting internally consistent: time explicitly removed by
            // the user is treated as reviewed non-work time rather than silently
            // disappearing from wall-time metrics.
            $pausedSeconds = max(0, $wallSeconds - $activeSeconds);
        } else {
            $activeSeconds = max(1, $wallSeconds - $pausedSeconds);
        }

        return [
            'active_seconds' => $activeSeconds,
            'wall_seconds' => $wallSeconds,
            'paused_seconds' => $pausedSeconds,
            'ended_at' => $endedAt,
        ];
    }

    public function durationConfirmationThresholdSeconds(WorkSession $session): int
    {
        $intended = (int) ($session->intended_minutes ?? 0);
        $minutes = $intended > 0 ? max($intended * 2, 90) : 120;

        return $minutes * 60;
    }

    public function activeSeconds(WorkSession $session, ?CarbonInterface $at = null): int
    {
        if ($session->ended_at && $session->actual_seconds !== null) {
            return max(0, (int) $session->actual_seconds);
        }

        $at ??= now();
        $end = $session->ended_at ?? $at;
        $paused = (int) $session->paused_seconds;

        if ($session->status === 'paused' && $session->paused_at) {
            $paused += max(0, (int) $session->paused_at->diffInSeconds($at));
        }

        return max(0, (int) $session->started_at->diffInSeconds($end) - $paused);
    }
}
