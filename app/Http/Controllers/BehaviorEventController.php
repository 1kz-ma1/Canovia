<?php

namespace App\Http\Controllers;

use App\Enums\BehaviorEventType;
use App\Models\BehaviorEvent;
use App\Models\Plan;
use App\Models\Task;
use App\Services\BehaviorEventLogger;
use App\Services\BehaviorIdentityService;
use App\Services\PlanOwnershipService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BehaviorEventController extends Controller
{
    public function store(
        Request $request,
        BehaviorIdentityService $identity,
        BehaviorEventLogger $logger,
        PlanOwnershipService $ownership,
    ) {
        $validated = $request->validate([
            'event_type' => ['required', Rule::in(BehaviorEventType::clientRecordable())],
            'plan_id' => ['nullable', 'integer', 'min:1'],
            'task_id' => ['nullable', 'integer', 'min:1'],
            'metadata' => ['nullable', 'array'],
        ]);

        if (strlen(json_encode($validated['metadata'] ?? [])) > 8000) {
            throw ValidationException::withMessages(['metadata' => 'イベント情報が大きすぎます。']);
        }

        $plan = isset($validated['plan_id']) ? Plan::find($validated['plan_id']) : null;
        $task = isset($validated['task_id']) ? Task::with('plan')->find($validated['task_id']) : null;

        if ($plan) {
            $ownership->authorizeView($request, $plan);
        }

        if ($task) {
            $ownership->authorizeTaskView($request, $task);

            if ($plan && $task->plan_id !== $plan->id) {
                throw ValidationException::withMessages(['task_id' => 'TaskとPlanの組み合わせが正しくありません。']);
            }

            $plan ??= $task->plan;
        }

        if ((isset($validated['plan_id']) && ! $plan) || (isset($validated['task_id']) && ! $task)) {
            throw ValidationException::withMessages(['event_type' => '参照先が見つかりません。']);
        }

        $type = BehaviorEventType::from($validated['event_type']);
        $metadata = $validated['metadata'] ?? [];
        $actorToken = $identity->resolve($request);

        if ($type === BehaviorEventType::PlanTabViewed && ! $plan) {
            throw ValidationException::withMessages(['plan_id' => 'Planタブの記録にはPlanが必要です。']);
        }

        if ($type === BehaviorEventType::TaskViewed && ! $task) {
            throw ValidationException::withMessages(['task_id' => 'Task表示の記録にはTaskが必要です。']);
        }

        if ($type === BehaviorEventType::DashboardIdle) {
            $elapsed = (int) data_get($metadata, 'elapsed_seconds', 0);
            $switches = (int) data_get($metadata, 'plan_switches', 0);
            $taskViews = (int) data_get($metadata, 'task_views', 0);
            $visible = filter_var(data_get($metadata, 'page_visible', false), FILTER_VALIDATE_BOOLEAN);
            $workStarted = filter_var(data_get($metadata, 'work_started', false), FILTER_VALIDATE_BOOLEAN);
            $alreadyStartedToday = BehaviorEvent::query()
                ->where('actor_token', $actorToken)
                ->where('event_type', BehaviorEventType::WorkStarted->value)
                ->where('occurred_at', '>=', today())
                ->exists();

            if (! $visible || $workStarted || $alreadyStartedToday || $elapsed < 60 || ($switches + $taskViews) < 2) {
                return response()->noContent();
            }
        }

        $logger->recordOnceSafely(
            $actorToken,
            $type,
            $request,
            $plan,
            $task,
            $metadata,
            withinMinutes: $type === BehaviorEventType::DashboardIdle ? 15 : 5,
        );

        return response()->noContent();
    }
}
