<?php

namespace App\Services;

use App\Data\UserBehaviorBaselineData;
use App\Data\UserStateData;
use App\Enums\BehaviorEventType;
use App\Enums\UserBehaviorState;
use App\Models\BehaviorEvent;
use App\Models\User;
use App\Models\UserStateSnapshot;
use App\Models\WorkSession;
use Illuminate\Support\Collection;

class DashboardPresentationService
{
    public function __construct(
        private readonly PlanProgressService $progressService,
        private readonly RecommendationService $recommendationService,
        private readonly DashboardGuidanceService $guidanceService,
        private readonly RoadmapService $roadmapService,
    ) {}

    public function build(
        Collection $plans,
        string $actorToken,
        UserBehaviorBaselineData $baseline,
        UserStateData $state,
        array $excludedTaskIds = [],
        array $editablePlanIds = [],
        ?User $actor = null,
    ): array {
        $plans = collect($plans->all());
        $previousSessions = WorkSession::with(['plan', 'task'])
            ->where('actor_token', $actorToken)
            ->whereIn('status', ['completed', 'interrupted'])
            ->whereNotNull('task_id')
            ->latest('ended_at')
            ->get()
            ->groupBy('plan_id')
            ->map(fn ($sessions) => $sessions->first());

        $editablePlanIds = collect($editablePlanIds)->map(fn ($id) => (int) $id)->flip();
        $guidanceDeck = $this->guidanceService->build(
            $plans,
            $state,
            $actorToken,
            $editablePlanIds->keys()->all(),
            $actor,
        );
        $planTabs = $plans->map(function ($plan) use ($previousSessions, $editablePlanIds, $guidanceDeck) {
            $progress = $this->progressService->calculate($plan);
            $todayMinutes = (int) $plan->workLogs
                ->filter(fn ($log) => $log->worked_on?->isToday())
                ->sum('actual_minutes');
            $canEdit = $editablePlanIds->has((int) $plan->id);
            $planGuidance = $guidanceDeck->first(
                fn (array $guidance) => (int) $guidance['plan']->id === (int) $plan->id
            );
            $recommendation = $canEdit ? data_get($planGuidance, 'adaptive') : null;
            $previousSession = $previousSessions->get($plan->id);
            $roadmap = $this->roadmapService->build(
                $plan,
                $recommendation?->task?->id,
                $previousSession?->task_id,
            );

            return [
                'plan' => $plan,
                'progress' => $progress,
                'today_minutes' => $todayMinutes,
                'previous_session' => $previousSession,
                'recent_logs' => $plan->workLogs->sortByDesc('worked_on')->take(3)->values(),
                'recommendation' => $recommendation,
                'roadmap' => $roadmap,
                'can_edit' => $canEdit,
            ];
        })->values();

        $recentActivity = $planTabs
            ->flatMap(fn (array $item) => $item['recent_logs']->map(fn ($log) => ['plan' => $item['plan'], 'log' => $log]))
            ->sortByDesc(fn (array $item) => sprintf('%s-%010d', $item['log']->worked_on?->format('Y-m-d') ?? '0000-00-00', $item['log']->id))
            ->take(6)
            ->values();

        $totalDailyRequired = (int) $planTabs->sum(fn ($item) => $item['progress']['daily_required_minutes']);
        $todayMinutes = (int) $planTabs->sum('today_minutes');
        $remainingMinutes = (int) $planTabs->sum(fn ($item) => $item['progress']['remaining_minutes']);
        // Compatibility alias: consumers that still read dashboard['recommendation']
        // receive the adaptive advice for the objective first Guidance Task.
        // Home no longer lets behavioral scoring replace the selected Task.
        $recommendation = data_get($guidanceDeck->first(), 'adaptive');
        $attentionPlans = $planTabs
            ->filter(fn ($item) => in_array($item['progress']['status'], ['遅れ気味', '期限切れ', '作業時間不足'], true))
            ->sortByDesc(fn ($item) => $item['progress']['daily_required_minutes'])
            ->take(3)
            ->values();
        $pendingPlanUpdates = WorkSession::with(['plan', 'task'])
            ->where('actor_token', $actorToken)
            ->where('needs_plan_update', true)
            ->whereIn('status', ['completed', 'interrupted'])
            ->latest('ended_at')
            ->take(5)
            ->get();
        $activeWorkSession = WorkSession::with(['plan', 'task'])
            ->where('actor_token', $actorToken)
            ->whereIn('status', ['active', 'paused'])
            ->latest('started_at')
            ->first();
        $trend = UserStateSnapshot::query()
            ->where('actor_token', $actorToken)
            ->latest('snapshot_date')
            ->take(14)
            ->get()
            ->sortBy('snapshot_date')
            ->values();
        $activeDays = BehaviorEvent::query()
            ->where('actor_token', $actorToken)
            ->where('event_type', BehaviorEventType::WorkStarted->value)
            ->where('occurred_at', '>=', now()->subDays((int) config('recommendations.baseline_days', 28)))
            ->get()
            ->toBase()
            ->map(fn ($event) => $event->occurred_at->toDateString())
            ->unique()
            ->count();
        $analysisReady = $baseline->sampleCount >= (int) config('recommendations.analysis_min_samples', 5)
            && $activeDays >= (int) config('recommendations.analysis_min_days', 3);
        $trendReady = $analysisReady && $trend->count() >= (int) config('recommendations.trend_min_days', 3);

        $streakDays = $this->streakDays($actorToken);
        $processHighlights = $this->processHighlights($actorToken, $baseline, $todayMinutes, $totalDailyRequired, $streakDays);
        $uiMode = $this->uiMode($state, $analysisReady);

        return [
            'plans' => $plans,
            'plan_tabs' => $planTabs,
            'recent_activity' => $recentActivity,
            'total_daily_required_minutes' => $totalDailyRequired,
            'today_minutes' => $todayMinutes,
            'remaining_minutes' => $remainingMinutes,
            'recommendation' => $recommendation,
            'guidance_deck' => $guidanceDeck,
            'attention_plans' => $attentionPlans,
            'baseline' => $baseline,
            'state' => $state,
            'active_work_session' => $activeWorkSession,
            'pending_plan_updates' => $pendingPlanUpdates,
            'trend' => $trend,
            'analysis_ready' => $analysisReady,
            'trend_ready' => $trendReady,
            'active_days' => $activeDays,
            'streak_days' => $streakDays,
            'process_highlights' => $processHighlights,
            'process_message' => $processHighlights[0] ?? null,
            'ui_mode' => $uiMode,
        ];
    }

    private function processHighlights(
        string $actorToken,
        UserBehaviorBaselineData $baseline,
        int $todayMinutes,
        int $dailyRequiredMinutes,
        int $streakDays,
    ): array {
        if ($todayMinutes <= 0) {
            return [];
        }

        $highlights = [];
        $started = BehaviorEvent::query()
            ->where('actor_token', $actorToken)
            ->where('event_type', BehaviorEventType::WorkStarted->value)
            ->where('occurred_at', '>=', today())
            ->oldest('occurred_at')
            ->first();
        $latency = (int) data_get($started?->metadata, 'start_latency_seconds', 0);

        if ($baseline->sampleCount >= 5 && $latency > max(120, $baseline->startLatencySeconds * 1.3)) {
            $highlights[] = "開始まで普段より時間がかかりましたが、その後{$todayMinutes}分取り組めています。";
        }

        if ($streakDays >= 2) {
            $highlights[] = "今日も取り組みが続き、{$streakDays}日連続で作業を開始できています。";
        }

        $sessions = WorkSession::query()
            ->where('actor_token', $actorToken)
            ->where('started_at', '>=', today())
            ->whereIn('status', ['completed', 'interrupted'])
            ->get()
            ->toBase();
        $qualified = $sessions->filter(fn ($session) => (int) $session->actual_seconds >= (int) config('recommendations.min_focus_session_seconds', 120));

        if ($qualified->count() >= 2) {
            $highlights[] = "今日は{$qualified->count()}回に分けて、合計{$todayMinutes}分を積み上げています。";
        }

        if ($qualified->sum('paused_seconds') > 0 && $todayMinutes >= 10) {
            $highlights[] = '途中で休憩を挟みながら、作業時間を積み上げられています。';
        }

        if ($dailyRequiredMinutes > 0 && $todayMinutes < $dailyRequiredMinutes) {
            $highlights[] = "今日の目安にはまだ届いていませんが、{$todayMinutes}分の実績は残せています。";
        } elseif ($dailyRequiredMinutes > 0 && $todayMinutes >= $dailyRequiredMinutes) {
            $highlights[] = '今日の作業目安に到達しています。追加で進める場合も無理に増やす必要はありません。';
        }

        return collect($highlights)->unique()->take(2)->values()->all();
    }

    private function uiMode(UserStateData $state, bool $analysisReady): string
    {
        if (! $analysisReady || $state->confidence < 0.3) {
            return 'balanced';
        }

        return match ($state->state) {
            UserBehaviorState::LowReadiness,
            UserBehaviorState::Overloaded,
            UserBehaviorState::Undecided => 'guided',
            UserBehaviorState::Focused => 'expanded',
            default => 'balanced',
        };
    }

    private function streakDays(string $actorToken): int
    {
        $dates = BehaviorEvent::query()
            ->where('actor_token', $actorToken)
            ->where('event_type', BehaviorEventType::WorkStarted->value)
            ->where('occurred_at', '>=', today()->subDays(60))
            ->get()
            ->toBase()
            ->map(fn ($event) => $event->occurred_at->toDateString())
            ->unique()
            ->flip();
        $streak = 0;

        for ($day = today(); $day->gte(today()->subDays(60)); $day->subDay()) {
            if (! $dates->has($day->toDateString())) {
                if ($streak === 0 && $day->isToday()) {
                    continue;
                }
                break;
            }
            $streak++;
        }

        return $streak;
    }
}
