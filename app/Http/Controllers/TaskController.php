<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Task;
use App\Services\PlanOwnershipService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function store(Request $request, Plan $plan)
    {
        $this->authorizePlanOwner($plan);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'estimated_minutes' => ['required', 'integer', 'min:0'],
            'remaining_minutes' => ['required', 'integer', 'min:0'],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', 'in:todo,doing,done,cancelled'],
            'priority' => ['required', 'integer', 'min:1', 'max:5'],
            'activation_cost' => ['required', 'integer', 'min:1', 'max:5'],
            'next_action_note' => ['nullable', 'string', 'max:1000'],
            'depends_on_task_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')->where(fn ($query) => $query->where('plan_id', $plan->id)),
            ],
        ]);

        Task::create([
            'plan_id' => $plan->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'estimated_minutes' => $validated['estimated_minutes'],
            'remaining_minutes' => $validated['status'] === 'done' ? 0 : $validated['remaining_minutes'],
            'progress_percent' => $validated['progress_percent'],
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'activation_cost' => $validated['activation_cost'],
            'next_action_note' => $validated['next_action_note'] ?? null,
            'depends_on_task_id' => $validated['depends_on_task_id'] ?? null,
            'sort_order' => 0,
        ]);

        return redirect()->route('plans.show', $plan);
    }

    public function edit(Task $task)
    {
        $task->load('plan.tasks');

        $this->authorizeOwner($task);

        return view('tasks.edit', compact('task'));
    }

    public function update(Request $request, Task $task)
    {
        $task->load('plan');

        $this->authorizeOwner($task);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'estimated_minutes' => ['required', 'integer', 'min:0'],
            'remaining_minutes' => ['required', 'integer', 'min:0'],
            'progress_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', 'in:todo,doing,done,cancelled'],
            'priority' => ['required', 'integer', 'min:1', 'max:5'],
            'activation_cost' => ['required', 'integer', 'min:1', 'max:5'],
            'next_action_note' => ['nullable', 'string', 'max:1000'],
            'depends_on_task_id' => [
                'nullable',
                'integer',
                Rule::exists('tasks', 'id')
                    ->where(fn ($query) => $query->where('plan_id', $task->plan_id)),
                Rule::notIn([$task->id]),
            ],
        ]);

        $task->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'estimated_minutes' => $validated['estimated_minutes'],
            'remaining_minutes' => $validated['status'] === 'done' ? 0 : $validated['remaining_minutes'],
            'progress_percent' => $validated['progress_percent'],
            'status' => $validated['status'],
            'priority' => $validated['priority'],
            'activation_cost' => $validated['activation_cost'],
            'next_action_note' => $validated['next_action_note'] ?? null,
            'depends_on_task_id' => $validated['depends_on_task_id'] ?? null,
        ]);

        return redirect()->route('plans.show', $task->plan);
    }

    public function destroy(Task $task)
    {
        $task->load('plan');

        $this->authorizeOwner($task);

        $plan = $task->plan;

        $task->delete();

        return redirect()->route('plans.show', $plan);
    }

    private function authorizeOwner(Task $task): void
    {
        app(PlanOwnershipService::class)->authorizeTask(request(), $task);
    }

    private function authorizePlanOwner(Plan $plan): void
    {
        app(PlanOwnershipService::class)->authorizeEdit(request(), $plan);
    }
}
