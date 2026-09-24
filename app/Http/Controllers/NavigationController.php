<?php

namespace App\Http\Controllers;

use App\Enums\BehaviorEventType;
use App\Models\Task;
use App\Services\BehaviorEventLogger;
use App\Services\BehaviorIdentityService;
use App\Services\NavigationFlowService;
use App\Services\PlanOwnershipService;
use App\Services\PlanToolService;
use App\Services\RecommendationService;
use App\Services\UserBehaviorService;
use App\Services\UserStateService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NavigationController extends Controller
{
    private const SESSION_KEY = 'navigation.draft';

    public function index(
        Request $request,
        BehaviorIdentityService $identity,
        PlanOwnershipService $ownership,
        UserBehaviorService $behaviorService,
        UserStateService $stateService,
        NavigationFlowService $flowService,
        RecommendationService $recommendationService,
        PlanToolService $toolService,
        BehaviorEventLogger $logger,
    ) {
        $actorToken = $identity->resolve($request);
        $plans = $ownership->ownedPlans($request, [
            'tasks' => fn ($query) => $query->with(['prerequisite', 'resources', 'artifacts'])->orderBy('sort_order')->orderBy('id'),
            'resources',
            'workLogs' => fn ($query) => $query->latest('worked_on')->latest('id'),
        ]);
        $plans = $plans->filter(fn ($plan) => $ownership->canEdit($request, $plan))->values();
        $baseline = $behaviorService->baseline($actorToken);
        $state = $stateService->calculate($actorToken, $baseline, $plans);

        if ($request->boolean('all')) {
            $request->session()->forget(self::SESSION_KEY);
        } elseif ($request->filled('plan_id')) {
            $contextPlan = $plans->firstWhere('id', (int) $request->integer('plan_id'));

            if ($contextPlan) {
                // DashboardでPlanを見てから「今日やること」へ移った行動自体を
                // 「このPlanを進めたい」という弱い入力として引き継ぐ。
                $request->session()->put(self::SESSION_KEY, [
                    'step' => 'recommendation',
                    'intent' => 'decide',
                    'minutes' => 0,
                    'scope_plan_id' => $contextPlan->id,
                    'selection_steps' => 0,
                    'excluded_task_ids' => [],
                ]);
            }
        }

        $draft = $request->session()->get(self::SESSION_KEY);

        if (! is_array($draft)) {
            $draft = [
                'step' => 'recommendation',
                'intent' => 'decide',
                'minutes' => 0,
                'selection_steps' => 0,
                'excluded_task_ids' => [],
            ];
            $request->session()->put(self::SESSION_KEY, $draft);
        }

        if ($request->boolean('configure')) {
            $scopePlanId = $draft['scope_plan_id'] ?? null;
            $draft = [
                'step' => 'intent',
                'selection_steps' => 0,
                'excluded_task_ids' => [],
            ];

            if ($scopePlanId) {
                $draft['scope_plan_id'] = (int) $scopePlanId;
            }

            $request->session()->put(self::SESSION_KEY, $draft);
        }
        $scopePlan = ! empty($draft['scope_plan_id'])
            ? $plans->firstWhere('id', (int) $draft['scope_plan_id'])
            : null;
        $recommendation = null;
        $recommendations = collect();

        if (($draft['step'] ?? null) === 'recommendation') {
            $recommendationPlans = $scopePlan && ($draft['intent'] ?? null) !== 'preferred'
                ? collect([$scopePlan])
                : $plans;
            $candidateExclusions = collect($draft['excluded_task_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all();

            // Keep the first recommendation decisive, but prepare up to two nearby alternatives
            // for the mobile swipe deck. Each next candidate excludes the ones before it.
            for ($index = 0; $index < 3; $index++) {
                $candidate = $recommendationService->recommend(
                    $recommendationPlans,
                    $state,
                    timeBudgetMinutes: ! empty($draft['minutes']) ? (int) $draft['minutes'] : null,
                    excludedTaskIds: $candidateExclusions,
                    intent: $draft['intent'] ?? null,
                    actorToken: $actorToken,
                    preferredPlanId: $draft['preferred_plan_id'] ?? null,
                );

                if (! $candidate) {
                    break;
                }

                $recommendations->push($candidate);
                $candidateExclusions[] = (int) $candidate->task->id;
            }

            $recommendation = $recommendations->first();

            if ($recommendation) {
                $logger->recordOnce(
                    $actorToken,
                    BehaviorEventType::RecommendationShown,
                    $request,
                    $recommendation->plan,
                    $recommendation->task,
                    ['source' => 'navigation', 'priority_score' => $recommendation->priorityScore],
                    withinMinutes: 2,
                );
            }
        }

        $executionByTask = $recommendations
            ->mapWithKeys(function ($candidate) use ($toolService, $request) {
                $tools = collect($toolService->forTask(
                    $candidate->plan,
                    $candidate->task,
                    true,
                    $request->user(),
                ));

                $primary = $tools->first(
                    fn (array $tool) => (bool) ($tool['primary_eligible'] ?? false)
                        && (bool) ($tool['recommended'] ?? false)
                );

                return [
                    (int) $candidate->task->id => [
                        'tools' => $tools->values()->all(),
                        'primary' => $primary,
                    ],
                ];
            })
            ->all();

        return view('navigation.index', [
            'plans' => $plans,
            'state' => $state,
            'draft' => $draft,
            'intentOptions' => $flowService->intentOptions($state),
            'timeOptions' => $flowService->timeOptions($state),
            'recommendation' => $recommendation,
            'recommendations' => $recommendations,
            'scopePlan' => $scopePlan,
            'executionByTask' => $executionByTask,
        ]);
    }

    public function chooseIntent(
        Request $request,
        BehaviorIdentityService $identity,
        PlanOwnershipService $ownership,
        UserBehaviorService $behaviorService,
        UserStateService $stateService,
        NavigationFlowService $flowService,
        BehaviorEventLogger $logger,
    ) {
        $actorToken = $identity->resolve($request);
        $plans = $ownership->ownedPlans($request, ['tasks', 'workLogs'])
            ->filter(fn ($plan) => $ownership->canEdit($request, $plan))
            ->values();
        $state = $stateService->calculate($actorToken, $behaviorService->baseline($actorToken), $plans);
        $validated = $request->validate([
            'intent' => ['required', Rule::in(array_keys($flowService->intentOptions($state)))],
        ]);
        $existingDraft = $request->session()->get(self::SESSION_KEY, []);
        $scopePlanId = is_array($existingDraft) ? ($existingDraft['scope_plan_id'] ?? null) : null;

        $nextDraft = [
            'step' => 'time',
            'intent' => $validated['intent'],
            'started_at' => now()->toIso8601String(),
            'selection_steps' => 1,
            'excluded_task_ids' => [],
        ];

        if ($scopePlanId && $validated['intent'] !== 'preferred') {
            $nextDraft['scope_plan_id'] = (int) $scopePlanId;
        }

        $request->session()->put(self::SESSION_KEY, $nextDraft);
        $logger->record($actorToken, BehaviorEventType::NavigationStarted, $request, metadata: [
            'intent' => $validated['intent'],
        ]);

        return redirect()->route('navigation.index');
    }

    public function chooseTime(Request $request, PlanOwnershipService $ownership, BehaviorIdentityService $identity, BehaviorEventLogger $logger)
    {
        $draft = $request->session()->get(self::SESSION_KEY);

        if (! is_array($draft) || ($draft['step'] ?? null) !== 'time') {
            return redirect()->route('navigation.index');
        }

        $validated = $request->validate([
            'minutes' => ['required', 'integer', Rule::in([0, 15, 30, 60])],
            'preferred_plan_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (($draft['intent'] ?? null) === 'preferred') {
            $plan = $ownership->ownedPlans($request)
                ->filter(fn ($candidate) => $ownership->canEdit($request, $candidate))
                ->firstWhere('id', (int) ($validated['preferred_plan_id'] ?? 0));

            if (! $plan) {
                throw ValidationException::withMessages(['preferred_plan_id' => '進めたいPlanを選択してください。']);
            }

            $draft['preferred_plan_id'] = $plan->id;
            unset($draft['scope_plan_id']);
        }

        $draft['minutes'] = (int) $validated['minutes'];
        $draft['step'] = 'recommendation';
        $draft['selection_steps'] = (int) ($draft['selection_steps'] ?? 1) + 1;
        $request->session()->put(self::SESSION_KEY, $draft);

        $logger->record($identity->resolve($request), BehaviorEventType::NavigationCompleted, $request, metadata: [
            'intent' => $draft['intent'],
            'minutes' => $draft['minutes'],
            'selection_steps' => $draft['selection_steps'],
            'duration_seconds' => isset($draft['started_at'])
                ? max(0, (int) Carbon::parse($draft['started_at'])->diffInSeconds(now()))
                : null,
        ]);

        return redirect()->route('navigation.index');
    }

    public function alternative(
        Request $request,
        BehaviorIdentityService $identity,
        BehaviorEventLogger $logger,
        PlanOwnershipService $ownership,
    ) {
        $draft = $request->session()->get(self::SESSION_KEY);

        if (! is_array($draft) || ($draft['step'] ?? null) !== 'recommendation') {
            return redirect()->route('navigation.index');
        }

        $validated = $request->validate(['task_id' => ['required', 'integer', 'min:1']]);
        $task = Task::with('plan')->findOrFail($validated['task_id']);
        $ownership->authorizeTask($request, $task);
        $draft['excluded_task_ids'] = collect($draft['excluded_task_ids'] ?? [])
            ->push($task->id)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $draft['selection_steps'] = (int) ($draft['selection_steps'] ?? 2) + 1;
        $request->session()->put(self::SESSION_KEY, $draft);
        $actorToken = $identity->resolve($request);
        $logger->record($actorToken, BehaviorEventType::RecommendationRejected, $request, $task->plan, $task, ['source' => 'navigation']);
        $logger->record($actorToken, BehaviorEventType::AlternativeRequested, $request, $task->plan, $task, [
            'source' => 'navigation',
            'excluded_count' => count($draft['excluded_task_ids']),
        ]);

        return redirect()->route('navigation.index');
    }

    public function reset(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('navigation.index');
    }
}
