<?php

namespace App\Http\Controllers;

use App\Services\PlanOwnershipService;
use App\Services\PlanProgressService;
use Illuminate\Http\Request;

class MyPlanController extends Controller
{
    public function index(Request $request, PlanProgressService $progressService, PlanOwnershipService $ownership)
    {
        $myPlans = $ownership->ownedPlans($request, ['tasks', 'workLogs'])
            ->map(function ($plan) use ($progressService, $ownership, $request) {
                return [
                    'plan' => $plan,
                    'progress' => $progressService->calculate($plan),
                    'role' => $ownership->role($request, $plan),
                ];
            });

        return view('my_plans.index', compact('myPlans'));
    }
}
