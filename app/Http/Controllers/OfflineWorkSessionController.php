<?php

namespace App\Http\Controllers;

use App\Enums\BehaviorEventType;
use App\Models\Task;
use App\Models\WorkLog;
use App\Models\WorkSession;
use App\Services\BehaviorEventLogger;
use App\Services\BehaviorIdentityService;
use App\Services\PlanOwnershipService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfflineWorkSessionController extends Controller
{
    public function sync(
        Request $request,
        BehaviorIdentityService $identity,
        BehaviorEventLogger $logger,
        PlanOwnershipService $ownership,
    ) {
        $validated = $request->validate([
            'client_session_id' => ['required', 'uuid'],
            'task_id' => ['required', 'integer', 'min:1'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after:started_at'],
            'actual_seconds' => ['required', 'integer', 'min:1', 'max:31536000'],
            'intended_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'adjustment_reason' => ['nullable', 'in:away_break,away_end,manual'],
            'adjustment_seconds' => ['nullable', 'integer', 'min:0', 'max:31536000'],
            'manual_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
        ]);

        $task = Task::with('plan')->findOrFail($validated['task_id']);
        $ownership->authorizeTask($request, $task);

        $actorToken = $identity->resolve($request);
        $startedAt = Carbon::parse($validated['started_at']);
        $endedAt = Carbon::parse($validated['ended_at']);
        $wallSeconds = max(1, $startedAt->diffInSeconds($endedAt));
        $actualSeconds = min((int) $validated['actual_seconds'], $wallSeconds);
        $actualMinutes = max(1, (int) round($actualSeconds / 60));
        $metadata = ['synced_from_offline' => true];

        if (! empty($validated['adjustment_reason'])) {
            $metadata['timer_adjustments'] = [[
                'action' => $validated['adjustment_reason'],
                'adjustment_seconds' => (int) ($validated['adjustment_seconds'] ?? 0),
                'manual_minutes' => isset($validated['manual_minutes']) ? (int) $validated['manual_minutes'] : null,
                'recorded_active_seconds' => $actualSeconds,
                'recorded_wall_seconds' => $wallSeconds,
                'adjusted_at' => now()->toIso8601String(),
            ]];
        }

        $created = false;
        $session = DB::transaction(function () use (
            $request,
            $task,
            $actorToken,
            $validated,
            $startedAt,
            $endedAt,
            $actualSeconds,
            $actualMinutes,
            $metadata,
            &$created,
        ) {
            // Unique client_session_id makes retries from reconnects/tabs idempotent.
            $session = WorkSession::query()->createOrFirst([
                'client_session_id' => $validated['client_session_id'],
            ], [
                'actor_token' => $actorToken,
                'browser_session_id' => $request->session()->getId(),
                'plan_id' => $task->plan_id,
                'task_id' => $task->id,
                'status' => 'completed',
                'intended_minutes' => $validated['intended_minutes'] ?? null,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'actual_seconds' => $actualSeconds,
                'paused_seconds' => max(0, $startedAt->diffInSeconds($endedAt) - $actualSeconds),
                'source' => 'offline',
                'needs_plan_update' => true,
                'metadata' => $metadata,
            ]);

            if (! $session->wasRecentlyCreated) {
                abort_unless(
                    (int) $session->task_id === (int) $task->id
                        && hash_equals((string) $session->actor_token, $actorToken),
                    409,
                    'この同期IDは別の作業で使用されています。',
                );

                return $session;
            }

            $created = true;
            WorkLog::create([
                'plan_id' => $task->plan_id,
                'task_id' => $task->id,
                'work_session_id' => $session->id,
                'task_title_snapshot' => $task->title,
                'worked_on' => $endedAt->toDateString(),
                'actual_minutes' => $actualMinutes,
                'progress_delta_percent' => 0,
                'progress_before_percent' => $task->progress_percent,
                'progress_after_percent' => $task->progress_percent,
                'remaining_minutes_before' => $task->remaining_minutes,
                'remaining_minutes_after' => $task->remaining_minutes,
                'memo' => 'オフライン中に記録し、接続復帰後に同期',
                'outcome' => 'オフライン作業セッションを同期',
            ]);

            return $session;
        });

        if ($created) {
            $logger->record($actorToken, BehaviorEventType::WorkCompleted, $request, $task->plan, $task, [
                'work_session_id' => $session->id,
                'duration_seconds' => $actualSeconds,
                'actual_minutes' => $actualMinutes,
                'source' => 'offline_sync',
                'client_session_id' => $validated['client_session_id'],
                'timer_adjusted' => ! empty($validated['adjustment_reason']),
                'timer_action' => $validated['adjustment_reason'] ?? 'normal',
            ]);
        }

        return response()->json([
            'ok' => true,
            'work_session_id' => $session->id,
            'already_synced' => ! $created,
        ]);
    }
}
