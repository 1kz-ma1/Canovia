<?php

namespace App\Http\Controllers;

use App\Enums\BehaviorEventType;
use App\Models\BehaviorEvent;
use App\Models\Task;
use App\Models\WorkLog;
use App\Models\WorkSession;
use App\Services\BehaviorEventLogger;
use App\Services\BehaviorIdentityService;
use App\Services\PlanOwnershipService;
use App\Services\WorkSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkSessionController extends Controller
{
    public function start(
        Request $request,
        BehaviorIdentityService $identity,
        BehaviorEventLogger $logger,
        PlanOwnershipService $ownership,
    ) {
        $validated = $request->validate([
            'task_id' => ['required', 'integer', 'min:1'],
            'intended_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'source' => ['required', 'in:dashboard,navigation,plan,roadmap'],
        ]);
        $task = Task::with('plan')->findOrFail($validated['task_id']);
        $ownership->authorizeTask($request, $task);

        if (in_array($task->status, ['done', 'cancelled'], true)) {
            throw ValidationException::withMessages(['task_id' => '完了または中止済みのTaskは開始できません。']);
        }

        $actorToken = $identity->resolve($request);
        $activeQuery = WorkSession::query()
            ->whereIn('status', ['active', 'paused'])
            ->where('actor_token', $actorToken);
        $active = $activeQuery->latest('started_at')->first();

        if ($active) {
            return redirect()->route('work_sessions.active', $active)
                ->with('status', '進行中の作業があります。先に終了してください。');
        }

        $lastEntry = in_array($validated['source'], ['dashboard', 'navigation'], true)
            ? BehaviorEvent::query()
                ->where('actor_token', $actorToken)
                ->where('session_id', $request->session()->getId())
                ->whereIn('event_type', [
                    BehaviorEventType::DashboardViewed->value,
                    BehaviorEventType::NavigationStarted->value,
                    BehaviorEventType::RecommendationShown->value,
                ])
                ->latest('occurred_at')
                ->first()
            : null;
        $startLatency = $lastEntry
            ? min(21600, max(0, (int) $lastEntry->occurred_at->diffInSeconds(now())))
            : null;

        $workSession = DB::transaction(function () use ($request, $validated, $task, $actorToken, $logger, $startLatency) {
            $session = WorkSession::create([
                'actor_token' => $actorToken,
                'browser_session_id' => $request->session()->getId(),
                'plan_id' => $task->plan_id,
                'task_id' => $task->id,
                'status' => 'active',
                'intended_minutes' => $validated['intended_minutes'] ?? null,
                'started_at' => now(),
                'paused_seconds' => 0,
                'source' => $validated['source'],
            ]);

            if (in_array($validated['source'], ['dashboard', 'navigation'], true)) {
                $logger->record($actorToken, BehaviorEventType::RecommendationAccepted, $request, $task->plan, $task, [
                    'source' => $validated['source'],
                    'recommended_minutes' => $validated['intended_minutes'] ?? null,
                ]);
            }

            $logger->record($actorToken, BehaviorEventType::TaskStarted, $request, $task->plan, $task, [
                'source' => $validated['source'],
            ]);
            $logger->record($actorToken, BehaviorEventType::WorkStarted, $request, $task->plan, $task, [
                'source' => $validated['source'],
                'intended_minutes' => $validated['intended_minutes'] ?? null,
                'start_latency_seconds' => $startLatency,
                'work_session_id' => $session->id,
            ]);

            return $session;
        });

        $request->session()->forget(['dashboard.recommendation_excluded', 'navigation.draft']);

        return redirect()->route('work_sessions.active', $workSession);
    }

    public function active(
        Request $request,
        WorkSession $workSession,
        BehaviorIdentityService $identity,
        PlanOwnershipService $ownership,
        WorkSessionService $sessions,
    ) {
        $this->authorizeSession($request, $workSession, $identity, $ownership);
        $workSession->load(['plan', 'task']);
        $activeSeconds = $sessions->activeSeconds($workSession);

        return view('work_sessions.active', compact('workSession', 'activeSeconds'));
    }

    public function pause(
        Request $request,
        WorkSession $workSession,
        BehaviorIdentityService $identity,
        PlanOwnershipService $ownership,
        WorkSessionService $sessions,
    ) {
        $this->authorizeSession($request, $workSession, $identity, $ownership);
        $sessions->pause($workSession);

        return redirect()->route('work_sessions.active', $workSession)->with('status', '一時停止しました。');
    }

    public function resume(
        Request $request,
        WorkSession $workSession,
        BehaviorIdentityService $identity,
        PlanOwnershipService $ownership,
        WorkSessionService $sessions,
    ) {
        $this->authorizeSession($request, $workSession, $identity, $ownership);
        $sessions->resume($workSession);

        return redirect()->route('work_sessions.active', $workSession)->with('status', '作業を再開しました。');
    }

    public function complete(
        Request $request,
        WorkSession $workSession,
        BehaviorIdentityService $identity,
        BehaviorEventLogger $logger,
        PlanOwnershipService $ownership,
        WorkSessionService $sessions,
    ) {
        $this->authorizeSession($request, $workSession, $identity, $ownership);
        $workSession->load(['plan', 'task']);
        $actorToken = $identity->resolve($request);

        $metrics = DB::transaction(function () use ($request, $workSession, $actorToken, $logger, $sessions) {
            $metrics = $sessions->finish($workSession, 'completed');
            $workSession->forceFill(['needs_plan_update' => true, 'plan_updated_at' => null])->save();

            if ($workSession->plan && $workSession->task) {
                WorkLog::updateOrCreate(
                    ['work_session_id' => $workSession->id],
                    [
                        'plan_id' => $workSession->plan_id,
                        'task_id' => $workSession->task_id,
                        'task_title_snapshot' => $workSession->task->title,
                        'worked_on' => today(),
                        'actual_minutes' => $metrics['actual_minutes'],
                        'progress_delta_percent' => 0,
                        'progress_before_percent' => $workSession->task->progress_percent,
                        'progress_after_percent' => $workSession->task->progress_percent,
                        'remaining_minutes_before' => $workSession->task->remaining_minutes,
                        'remaining_minutes_after' => $workSession->task->remaining_minutes,
                        'memo' => null,
                        'outcome' => '作業セッションを終了',
                    ]
                );
            }

            $logger->record($actorToken, BehaviorEventType::WorkCompleted, $request, $workSession->plan, $workSession->task, [
                'work_session_id' => $workSession->id,
                'duration_seconds' => $metrics['active_seconds'],
                'wall_seconds' => $metrics['wall_seconds'],
                'paused_seconds' => $metrics['paused_seconds'],
                'actual_minutes' => $metrics['actual_minutes'],
                'intended_minutes' => $workSession->intended_minutes,
            ]);

            return $metrics;
        });

        if (! $workSession->plan) {
            return redirect()->route('home')->with('success', "{$metrics['actual_minutes']}分の作業を記録しました。");
        }

        return redirect()
            ->route('plans.review_assistant.show', [
                'plan' => $workSession->plan,
                'work_session_id' => $workSession->id,
            ])
            ->with('status', "{$metrics['actual_minutes']}分の作業事実を記録しました。必要なら、このままAIで計画へ意味付けできます。");
    }

    public function review(
        Request $request,
        WorkSession $workSession,
        BehaviorIdentityService $identity,
        PlanOwnershipService $ownership,
    ) {
        $this->authorizeSession($request, $workSession, $identity, $ownership);
        $workSession->load(['plan', 'task']);

        if ($workSession->status !== 'completed' || ! $workSession->plan) {
            return redirect()->route('home');
        }

        return redirect()->route('plans.review_assistant.show', [
            'plan' => $workSession->plan,
            'work_session_id' => $workSession->id,
        ]);
    }

    public function storeReview(
        Request $request,
        WorkSession $workSession,
        BehaviorIdentityService $identity,
        PlanOwnershipService $ownership,
    ) {
        $this->authorizeSession($request, $workSession, $identity, $ownership);
        $workSession->load(['plan', 'task']);

        if ($workSession->status !== 'completed' || ! $workSession->plan) {
            return redirect()->route('home');
        }

        return redirect()
            ->route('plans.review_assistant.show', [
                'plan' => $workSession->plan,
                'work_session_id' => $workSession->id,
            ])
            ->with('status', '作業結果の判断は、共通の計画更新フローへ統合されました。');
    }

    public function interrupt(
        Request $request,
        WorkSession $workSession,
        BehaviorIdentityService $identity,
        BehaviorEventLogger $logger,
        PlanOwnershipService $ownership,
        WorkSessionService $sessions,
    ) {
        $this->authorizeSession($request, $workSession, $identity, $ownership);
        $workSession->load(['plan', 'task']);
        $actorToken = $identity->resolve($request);

        $metrics = DB::transaction(function () use ($request, $workSession, $actorToken, $logger, $sessions) {
            $metrics = $sessions->finish($workSession, 'interrupted');
            if (($metrics['actual_minutes'] ?? 0) >= 2) {
                $workSession->forceFill(['needs_plan_update' => true, 'plan_updated_at' => null])->save();
            }

            if ($workSession->plan && $workSession->task && $metrics['actual_minutes'] >= 2) {
                WorkLog::updateOrCreate(
                    ['work_session_id' => $workSession->id],
                    [
                        'plan_id' => $workSession->plan_id,
                        'task_id' => $workSession->task_id,
                        'task_title_snapshot' => $workSession->task->title,
                        'worked_on' => today(),
                        'actual_minutes' => $metrics['actual_minutes'],
                        'progress_delta_percent' => 0,
                        'progress_before_percent' => $workSession->task->progress_percent,
                        'progress_after_percent' => $workSession->task->progress_percent,
                        'remaining_minutes_before' => $workSession->task->remaining_minutes,
                        'remaining_minutes_after' => $workSession->task->remaining_minutes,
                        'difficulty' => 'stuck',
                        'outcome' => '作業を中断',
                    ]
                );
            }

            $logger->record($actorToken, BehaviorEventType::WorkInterrupted, $request, $workSession->plan, $workSession->task, [
                'work_session_id' => $workSession->id,
                'duration_seconds' => $metrics['active_seconds'],
                'wall_seconds' => $metrics['wall_seconds'],
                'paused_seconds' => $metrics['paused_seconds'],
                'actual_minutes' => $metrics['actual_minutes'],
                'intended_minutes' => $workSession->intended_minutes,
            ]);

            return $metrics;
        });

        return redirect()->route('home')->with('status', "{$metrics['actual_minutes']}分で中断しました。取り組んだ記録は残っています。");
    }

    private function authorizeSession(
        Request $request,
        WorkSession $workSession,
        BehaviorIdentityService $identity,
        PlanOwnershipService $ownership,
    ): void {
        $workSession->loadMissing('plan');

        if (! $workSession->plan) {
            abort(403);
        }

        $ownership->authorizeEdit($request, $workSession->plan);

        if (! hash_equals($workSession->actor_token, $identity->resolve($request))) {
            abort(403, '他のメンバーの作業タイマーは操作できません。');
        }
    }
}
