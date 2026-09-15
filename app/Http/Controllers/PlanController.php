<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\BehaviorIdentityService;
use App\Services\ContinuityService;
use App\Services\PlanOwnershipService;
use App\Services\PlanCollaborationService;
use App\Services\PlanActivityService;
use App\Services\PlanProgressService;
use App\Services\PlanTimelineService;
use App\Services\RecommendationService;
use App\Services\RoadmapService;
use App\Services\UserBehaviorService;
use App\Services\UserStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PlanController extends Controller
{
    public function create()
    {
        return view('plans.create');
    }

    public function store(Request $request, PlanCollaborationService $collaboration)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'visual_icon' => ['nullable', 'string', 'max:16'],
            'accent_key' => ['nullable', Rule::in(Plan::ACCENT_KEYS)],
            'roadmap_world' => ['nullable', Rule::in(Plan::ROADMAP_WORLDS)],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'is_public' => ['nullable'],
            'is_collaborative' => ['nullable'],
        ]);

        $startDate = $validated['start_date'] ?? now()->toDateString();
        $deadline = $validated['deadline'] ?? null;

        if ($deadline && Carbon::parse($deadline)->lt(Carbon::parse($startDate))) {
            return back()->withErrors(['deadline' => '期限は開始日以降にしてください。'])->withInput();
        }

        if ($request->boolean('is_collaborative') && ! $request->user()) {
            return back()->withErrors(['is_collaborative' => '共同計画を作るにはログインが必要です。'])->withInput();
        }

        $ownerToken = Str::random(64);
        $plan = Plan::create([
            'user_id' => $request->user()?->id,
            'owner_token' => $ownerToken,
            'public_slug' => Str::uuid()->toString(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'visual_icon' => $validated['visual_icon'] ?? null,
            'accent_key' => $validated['accent_key'] ?? 'sky',
            'roadmap_world' => $validated['roadmap_world'] ?? 'default',
            'start_date' => $startDate,
            'deadline' => $deadline,
            'is_public' => $request->boolean('is_public'),
            'is_collaborative' => false,
        ]);

        if ($request->boolean('is_collaborative') && $request->user()) {
            if (! $collaboration->canOwnCollaborativePlan($request->user())) {
                $plan->delete();
                abort(403, '共同計画の作成権限がありません。');
            }
            $collaboration->enable($plan);
        }

        if (! $request->user()) {
            cookie()->queue('pace_keeper_owner_token_' . $plan->id, $ownerToken, 60 * 24 * 365, '/', null, app()->environment('production') || $request->isSecure(), true, false, 'lax');
        }

        return redirect()->route('plans.ai_task_assistant.show', $plan)
            ->with('status', '計画の基本情報を作成しました。続けてAIで初期タスクを生成できます。');
    }

    public function show(
        Request $request,
        Plan $plan,
        PlanProgressService $progressService,
        PlanTimelineService $timelineService,
        PlanOwnershipService $ownership,
        BehaviorIdentityService $identity,
        UserBehaviorService $behaviorService,
        UserStateService $stateService,
        RecommendationService $recommendationService,
        RoadmapService $roadmapService,
        ContinuityService $continuityService,
    ) {
        $canView = $ownership->canView($request, $plan);
        $canEdit = $ownership->canEdit($request, $plan);
        $canManage = $ownership->owns($request, $plan);
        $collaborationRole = $ownership->role($request, $plan);

        // The numeric Plan detail route contains private operational context
        // (work logs, adjustments, recommendation state). Public sharing uses
        // the dedicated random-slug route instead, so non-owners never receive
        // the full Plan detail even when is_public is enabled.
        if (! $canView) {
            abort(404);
        }

        $plan->load([
            'tasks' => fn ($query) => $query->with('prerequisite')->orderBy('sort_order')->orderBy('id'),
            'workLogs' => fn ($query) => $query->with('task')->latest('worked_on')->latest('id'),
            'adjustments' => fn ($query) => $query->latest('applied_at')->limit(10),
            'availabilityRules',
            'availabilityOverrides',
        ]);

        $progress = $progressService->calculate($plan);
        $timeline = $timelineService->build($plan);
        $recommendation = null;
        $continuity = null;

        if ($canEdit) {
            $actorToken = $identity->resolve($request);
            $plans = collect([$plan]);
            $baseline = $behaviorService->baseline($actorToken);
            $state = $stateService->calculate($actorToken, $baseline, $plans);
            $recommendation = $recommendationService->recommend(
                $plans,
                $state,
                actorToken: $actorToken,
                preferredPlanId: $plan->id,
            );
            $continuity = $continuityService->forPlan($plan, $actorToken);
        }

        $roadmap = $roadmapService->build(
            $plan,
            $recommendation?->task?->id,
            $continuity['task_id'] ?? null,
        );

        $recentActivities = $plan->is_collaborative
            ? $plan->activityLogs()->with('user')->limit(8)->get()
            : collect();

        return view('plans.show', compact('plan', 'progress', 'timeline', 'canEdit', 'canManage', 'collaborationRole', 'recommendation', 'continuity', 'roadmap', 'recentActivities'));
    }

    public function edit(Request $request, Plan $plan, PlanOwnershipService $ownership)
    {
        $ownership->authorizePlan($request, $plan);

        return view('plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan, PlanOwnershipService $ownership, PlanActivityService $activity)
    {
        $ownership->authorizePlan($request, $plan);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'visual_icon' => ['nullable', 'string', 'max:16'],
            'accent_key' => ['nullable', Rule::in(Plan::ACCENT_KEYS)],
            'roadmap_world' => ['nullable', Rule::in(Plan::ROADMAP_WORLDS)],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date'],
            'is_public' => ['nullable'],
            'is_collaborative' => ['nullable'],
        ]);

        $startDate = $validated['start_date'] ?? $plan->start_date?->format('Y-m-d');
        $deadline = array_key_exists('deadline', $validated)
            ? $validated['deadline']
            : $plan->deadline?->format('Y-m-d');

        if ($startDate && $deadline && Carbon::parse($deadline)->lt(Carbon::parse($startDate))) {
            return back()->withErrors(['deadline' => '期限は開始日以降にしてください。'])->withInput();
        }

        $before = $plan->only(['title', 'description', 'category', 'start_date', 'deadline', 'is_public']);

        $plan->update([
            'title' => $validated['title'],
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $plan->description,
            'category' => array_key_exists('category', $validated) ? $validated['category'] : $plan->category,
            'visual_icon' => array_key_exists('visual_icon', $validated) ? $validated['visual_icon'] : $plan->visual_icon,
            'accent_key' => $validated['accent_key'] ?? $plan->accentKey(),
            'roadmap_world' => $validated['roadmap_world'] ?? $plan->roadmapWorld(),
            'start_date' => $startDate,
            'deadline' => $deadline,
            'is_public' => $request->has('is_public') ? $request->boolean('is_public') : $plan->is_public,
        ]);

        $changedFields = collect($plan->only(array_keys($before)))
            ->filter(fn ($value, $key) => (string) ($before[$key] ?? '') !== (string) $value)
            ->keys()
            ->values()
            ->all();
        $activity->record($plan, $request->user(), 'plan_updated', 'plan', (int) $plan->id, [
            'changed_fields' => $changedFields,
        ]);

        return redirect()->route('plans.show', $plan)->with('success', '計画を更新しました。');
    }

    public function destroy(Request $request, Plan $plan, PlanOwnershipService $ownership)
    {
        $ownership->authorizePlan($request, $plan);
        $plan->delete();

        return redirect()->route('home')->with('success', '計画を削除しました。');
    }
}
