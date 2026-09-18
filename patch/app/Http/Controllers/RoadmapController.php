<?php

namespace App\Http\Controllers;

use App\Services\BehaviorIdentityService;
use App\Services\ContinuityService;
use App\Services\PlanOwnershipService;
use App\Services\RecommendationService;
use App\Services\RoadmapService;
use App\Services\UserBehaviorService;
use App\Services\UserStateService;
use Illuminate\Http\Request;

class RoadmapController extends Controller
{
    public function index(
        Request $request,
        PlanOwnershipService $ownership,
        BehaviorIdentityService $identity,
        UserBehaviorService $behaviorService,
        UserStateService $stateService,
        RecommendationService $recommendationService,
        RoadmapService $roadmapService,
        ContinuityService $continuityService,
    ) {
        $plans = $ownership->ownedPlans($request, [
            'tasks' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        $selectedPlanId = (int) $request->integer('plan_id');
        $plan = $selectedPlanId > 0 ? $plans->firstWhere('id', $selectedPlanId) : $plans->first();
        $roadmap = null;
        $recommendation = null;
        $continuity = null;
        $previousPlan = null;
        $nextPlan = null;
        $canEdit = false;
        $canManage = false;
        $collaborationRole = null;

        if ($plan) {
            // The carousel only needs task counts; detail data is for this plan.
            $plan->loadMissing([
                'tasks.prerequisite', 'tasks.resources',
                'workLogs' => fn ($query) => $query->latest('worked_on'),
                'availabilityRules', 'availabilityOverrides',
            ]);
            $canEdit = $ownership->canEdit($request, $plan);
            $canManage = $ownership->owns($request, $plan);
            $collaborationRole = $ownership->role($request, $plan);
            $planIndex = $plans->values()->search(fn ($candidate) => $candidate->id === $plan->id);
            if ($planIndex !== false) {
                $previousPlan = $planIndex > 0 ? $plans->values()->get($planIndex - 1) : null;
                $nextPlan = $planIndex < ($plans->count() - 1) ? $plans->values()->get($planIndex + 1) : null;
            }

            $actorToken = $identity->resolve($request);
            $baseline = $behaviorService->baseline($actorToken);
            $state = $stateService->calculate($actorToken, $baseline, collect([$plan]));
            $recommendation = $recommendationService->recommend(
                collect([$plan]),
                $state,
                actorToken: $actorToken,
                preferredPlanId: $plan->id,
            );
            $continuity = $continuityService->forPlan($plan, $actorToken);
            $roadmap = $roadmapService->build(
                $plan,
                $recommendation?->task?->id,
                $continuity['task_id'] ?? null,
            );
        }

        return view('roadmap.index', compact(
            'plans',
            'plan',
            'roadmap',
            'recommendation',
            'continuity',
            'previousPlan',
            'nextPlan',
            'canEdit',
            'canManage',
            'collaborationRole',
        ));
    }
}
