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
            'tasks' => fn ($query) => $query->with('prerequisite')->orderBy('sort_order')->orderBy('id'),
            'workLogs' => fn ($query) => $query->with('task')->latest('worked_on'),
        ]);

        $selectedPlanId = (int) $request->integer('plan_id');
        $plan = $selectedPlanId > 0 ? $plans->firstWhere('id', $selectedPlanId) : $plans->first();
        $roadmap = null;
        $recommendation = null;
        $continuity = null;
        $previousPlan = null;
        $nextPlan = null;
        $canEdit = false;

        if ($plan) {
            $canEdit = $ownership->canEdit($request, $plan);
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
        ));
    }
}
