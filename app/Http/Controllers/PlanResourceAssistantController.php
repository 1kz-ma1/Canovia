<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PlanActivityService;
use App\Services\PlanOwnershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanResourceAssistantController extends Controller
{
    public function show(Request $request, Plan $plan, PlanOwnershipService $ownership)
    {
        $ownership->authorizeEdit($request, $plan);
        $this->loadPlan($plan);

        $prompt = $this->buildPrompt($plan);
        $preview = session('resource_assignment_preview.' . $plan->id);

        return view('resources.assistant', compact('plan', 'prompt', 'preview'));
    }

    public function preview(Request $request, Plan $plan, PlanOwnershipService $ownership)
    {
        $ownership->authorizeEdit($request, $plan);
        $this->loadPlan($plan);

        $validated = $request->validate([
            'assignment_json' => ['required', 'string', 'max:100000'],
        ]);

        $decoded = json_decode($this->extractJson($validated['assignment_json']), true);
        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                'assignment_json' => 'AIの回答からJSONを読み取れませんでした。最後の回答をそのまま貼り付けてください。',
            ]);
        }

        if (($decoded['flow'] ?? null) !== 'resource_assignment' || (string) ($decoded['schema_version'] ?? '') !== '1.0') {
            throw ValidationException::withMessages([
                'assignment_json' => '関連資料整理用のJSONではありません。この画面の相談文から作った回答を貼り付けてください。',
            ]);
        }

        $target = $decoded['target_plan'] ?? [];
        if (! is_array($target)
            || (int) ($target['id'] ?? 0) !== (int) $plan->id
            || trim((string) ($target['title'] ?? '')) !== $plan->title) {
            throw ValidationException::withMessages([
                'assignment_json' => '別の計画向けの回答です。現在の計画用に生成し直してください。',
            ]);
        }

        $assignments = $decoded['assignments'] ?? null;
        if (! is_array($assignments) || count($assignments) > 200) {
            throw ValidationException::withMessages([
                'assignment_json' => 'assignmentsは配列で指定してください。',
            ]);
        }

        $resourceById = $plan->resources->keyBy('id');
        $taskById = $plan->tasks->keyBy('id');
        $seenResources = [];
        $normalized = [];

        foreach ($assignments as $index => $assignment) {
            if (! is_array($assignment)) {
                throw ValidationException::withMessages(['assignment_json' => ($index + 1) . '件目の割り当て形式が正しくありません。']);
            }

            $resourceId = (int) ($assignment['resource_id'] ?? 0);
            if (! $resourceById->has($resourceId) || in_array($resourceId, $seenResources, true)) {
                throw ValidationException::withMessages(['assignment_json' => ($index + 1) . '件目のresource_idが無効、または重複しています。']);
            }

            $rawTaskIds = $assignment['task_ids'] ?? [];
            if (! is_array($rawTaskIds)) {
                throw ValidationException::withMessages(['assignment_json' => ($index + 1) . '件目のtask_idsは配列にしてください。']);
            }

            $taskIds = collect($rawTaskIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            if ($taskIds->contains(fn ($id) => ! $taskById->has($id))) {
                throw ValidationException::withMessages(['assignment_json' => ($index + 1) . '件目に別計画のタスクIDが含まれています。']);
            }

            $seenResources[] = $resourceId;
            $normalized[] = [
                'resource_id' => $resourceId,
                'resource_title' => $resourceById[$resourceId]->title,
                'task_ids' => $taskIds->all(),
                'task_titles' => $taskIds->map(fn ($id) => $taskById[$id]->title)->all(),
                'reason' => mb_substr(trim((string) ($assignment['reason'] ?? '')), 0, 1000),
            ];
        }

        $missingResourceIds = $resourceById->keys()->map(fn ($id) => (int) $id)->diff($seenResources);
        if ($missingResourceIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'assignment_json' => '一部の関連資料がassignmentsに含まれていません。すべてのresource_idを一度ずつ含めてください。',
            ]);
        }

        session()->put('resource_assignment_preview.' . $plan->id, [
            'assignments' => $normalized,
            'summary' => mb_substr(trim((string) ($decoded['summary'] ?? '')), 0, 2000),
            'created_at' => now()->toIso8601String(),
        ]);

        return redirect()->route('plans.resources.assistant', $plan)
            ->with('status', 'AIの割り当て案を確認してください。まだ反映されていません。');
    }

    public function apply(
        Request $request,
        Plan $plan,
        PlanOwnershipService $ownership,
        PlanActivityService $activity,
    ) {
        $ownership->authorizeEdit($request, $plan);
        $preview = session('resource_assignment_preview.' . $plan->id);

        if (! is_array($preview) || ! is_array($preview['assignments'] ?? null)) {
            return redirect()->route('plans.resources.assistant', $plan)
                ->withErrors(['assignment_json' => '適用できるプレビューがありません。もう一度AIの回答を読み込んでください。']);
        }

        $plan->load(['resources', 'tasks:id,plan_id']);
        $resourceById = $plan->resources->keyBy('id');
        $validTaskIds = $plan->tasks->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($preview, $resourceById, $validTaskIds) {
            foreach ($preview['assignments'] as $assignment) {
                $resource = $resourceById->get((int) ($assignment['resource_id'] ?? 0));
                if (! $resource) {
                    continue;
                }
                $taskIds = collect($assignment['task_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => in_array($id, $validTaskIds, true))
                    ->unique()
                    ->values()
                    ->all();
                $resource->tasks()->sync($taskIds);
            }
        });

        session()->forget('resource_assignment_preview.' . $plan->id);
        $activity->record($plan, $request->user(), 'resource_ai_assigned', 'plan', (int) $plan->id, [
            'assignment_count' => count($preview['assignments']),
        ]);

        return redirect()->route('plans.resources.index', $plan)
            ->with('success', 'AIの整理案を関連資料へ反映しました。');
    }

    public function reset(Request $request, Plan $plan, PlanOwnershipService $ownership)
    {
        $ownership->authorizeEdit($request, $plan);
        session()->forget('resource_assignment_preview.' . $plan->id);

        return redirect()->route('plans.resources.assistant', $plan);
    }

    private function loadPlan(Plan $plan): void
    {
        $plan->load([
            'tasks' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'resources' => fn ($query) => $query->with('tasks:id,title')->orderBy('id'),
        ]);
    }

    private function buildPrompt(Plan $plan): string
    {
        $resources = $plan->resources->map(function ($resource) {
            $taskIds = $resource->tasks->pluck('id')->map(fn ($id) => (int) $id)->all();

            return [
                'resource_id' => (int) $resource->id,
                'title' => $resource->title,
                'provider' => $resource->provider,
                'type' => $resource->resource_type,
                'url' => $resource->url,
                'current_task_ids' => $taskIds,
            ];
        })->values()->all();

        $tasks = $plan->tasks->map(fn ($task) => [
            'task_id' => (int) $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
        ])->values()->all();

        $planTitle = json_encode($plan->title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $resourcesJson = json_encode($resources, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $tasksJson = json_encode($tasks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return <<<PROMPT
あなたはCanoviaの資料整理アシスタントです。
登録済みの関連資料を、実行時に本当に役立つタスクだけへ割り当ててください。
全資料を全タスクへ機械的に付けないでください。関連が弱い資料はtask_idsを空配列にしてください。
既存の割り当ては参考にしてよいですが、必要なら付け替えて構いません。

対象計画:
- ID: {$plan->id}
- タイトル: {$plan->title}

タスク:
{$tasksJson}

関連資料:
{$resourcesJson}

最後の回答は説明やMarkdownを付けず、次のJSONだけにしてください。
{
  "schema_version": "1.0",
  "flow": "resource_assignment",
  "target_plan": {"id": {$plan->id}, "title": {$planTitle}},
  "summary": "整理方針の短い要約",
  "assignments": [
    {
      "resource_id": 1,
      "task_ids": [10, 11],
      "reason": "この資料がこのタスクに必要な理由"
    }
  ]
}

assignmentsには、上にあるすべての関連資料をresource_idごとに一度だけ含めてください。紐づけ不要な資料はtask_idsを空配列にしてください。
PROMPT;
    }

    private function extractJson(string $text): string
    {
        $trimmed = trim($text);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $trimmed, $matches)) {
            return trim($matches[1]);
        }

        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            return $trimmed;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return trim(substr($trimmed, $start, $end - $start + 1));
        }

        return $trimmed;
    }
}
