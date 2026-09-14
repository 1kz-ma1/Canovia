<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanAdjustment;
use App\Models\Task;
use App\Services\PlanProgressService;
use App\Services\PlanOwnershipService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiTaskAssistantController extends Controller
{
    public function show(Plan $plan)
    {
        $this->authorizePlanOwner($plan);

        $plan->load(['tasks' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);
        $title = json_encode($plan->title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $category = json_encode($plan->category, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $deadline = $plan->deadline?->format('Y-m-d') ?? '未設定';

        $prompt = <<<PROMPT
あなたはPace Keeperの計画生成アシスタントです。目標を実行可能なタスクへ分解してください。
不足情報があればJSONを出す前にユーザーへ質問し、期限、使える時間、現在地、完成条件を確認してください。

対象計画:
- ID: {$plan->id}
- タイトル: {$plan->title}
- 概要: {$plan->description}
- 期間: {$plan->start_date->format('Y-m-d')} ～ {$deadline}

最終回答は説明やMarkdownを付けず、次のJSON 2.0だけにしてください。
{
  "schema_version": "2.0",
  "flow": "plan_generation",
  "target_plan": {"id": {$plan->id}, "title": {$title}, "category": {$category}},
  "summary": "生成した計画の要約",
  "operations": [
    {
      "type": "update_plan",
      "deadline": "YYYY-MM-DD"
    },
    {
      "type": "add_task",
      "client_ref": "task_1",
      "title": "具体的なタスク名",
      "description": "完了を判定できる条件",
      "estimated_minutes": 120,
      "remaining_minutes": 120,
      "progress_percent": 0,
      "progress_reason": "未着手",
      "status": "todo",
      "priority": 1,
      "activation_cost": 2
    },
    {
      "type": "reorder_tasks",
      "items": [{"task_ref": "task_1"}],
      "reason": "依存関係と優先順位に基づく実行順"
    }
  ]
}

期限が未設定なら、タスク生成前にユーザーへ希望時期・使える時間・現在地を質問してください。会話で期限が決まった場合だけupdate_planを含め、まだ決めない場合はupdate_planを省略してください。
進捗率は最新の完成条件に対する絶対値、remaining_minutesは今後実際に必要な時間として別々に判断してください。
activation_costは1～5で、難易度ではなく「そのTaskを始めるまでの心理的・準備的な重さ」を推定してください。1はすぐ始められ、5はかなり準備や集中が必要です。
PROMPT;

        return view('plans.ai_task_assistant', compact('plan', 'prompt'));
    }

    public function import(Request $request, Plan $plan, PlanProgressService $progressService)
    {
        $this->authorizePlanOwner($plan);

        $validated = $request->validate(['tasks_json' => ['required', 'string', 'max:100000']]);
        $json = $this->extractJson($validated['tasks_json']);
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages(['tasks_json' => 'AIの回答から計画データを読み取れませんでした。最後の回答をそのまま貼り付けるか、AIに「最後はJSONだけで出力して」と伝えてください。']);
        }

        if ((string) ($decoded['schema_version'] ?? '') !== '2.0' || ($decoded['flow'] ?? null) !== 'plan_generation') {
            throw ValidationException::withMessages(['tasks_json' => 'PaceKeeper用の計画データではないようです。この画面の相談用文章から作った回答を貼り付けてください。']);
        }

        $target = $decoded['target_plan'] ?? [];

        if ((int) ($target['id'] ?? 0) !== $plan->id || trim((string) ($target['title'] ?? '')) !== $plan->title) {
            throw ValidationException::withMessages(['tasks_json' => '別の計画向けの回答のようです。この画面から作った相談内容を使って、もう一度AIへ相談してください。']);
        }

        $operations = $decoded['operations'] ?? null;

        if (! is_array($operations) || $operations === [] || count($operations) > 60) {
            throw ValidationException::withMessages(['tasks_json' => 'operationsは1～60件の配列にしてください。']);
        }

        $taskOperations = collect($operations)->filter(fn ($operation) => ($operation['type'] ?? null) === 'add_task')->values();
        $unsupported = collect($operations)->reject(fn ($operation) => in_array($operation['type'] ?? null, ['update_plan', 'add_task', 'reorder_tasks'], true));

        if ($taskOperations->isEmpty() || $unsupported->isNotEmpty()) {
            throw ValidationException::withMessages(['tasks_json' => '計画生成ではupdate_plan、add_task、reorder_tasksだけを使用できます。']);
        }

        $normalizedTasks = $taskOperations->map(function ($operation, $index) {
            $title = trim((string) ($operation['title'] ?? ''));
            $estimated = filter_var($operation['estimated_minutes'] ?? null, FILTER_VALIDATE_INT);
            $remaining = filter_var($operation['remaining_minutes'] ?? null, FILTER_VALIDATE_INT);
            $progress = filter_var($operation['progress_percent'] ?? 0, FILTER_VALIDATE_INT);
            $priority = filter_var($operation['priority'] ?? 3, FILTER_VALIDATE_INT);
            $activationCost = filter_var($operation['activation_cost'] ?? 3, FILTER_VALIDATE_INT);
            $status = $operation['status'] ?? 'todo';

            if ($title === '' || mb_strlen($title) > 255 || $estimated === false || $estimated < 0
                || $remaining === false || $remaining < 0 || $progress === false || $progress < 0 || $progress > 100
                || $priority === false || $priority < 1 || $priority > 5
                || $activationCost === false || $activationCost < 1 || $activationCost > 5
                || ! in_array($status, ['todo', 'doing', 'done'], true)) {
                throw ValidationException::withMessages(['tasks_json' => ($index + 1) . '件目のタスク内容が正しくありません。']);
            }

            return [
                'client_ref' => trim((string) ($operation['client_ref'] ?? '')),
                'title' => $title,
                'description' => trim((string) ($operation['description'] ?? '')),
                'estimated_minutes' => $estimated,
                'remaining_minutes' => $status === 'done' ? 0 : $remaining,
                'progress_percent' => $status === 'done' ? 100 : $progress,
                'progress_reason' => trim((string) ($operation['progress_reason'] ?? '')),
                'status' => $status,
                'priority' => $priority,
                'activation_cost' => $activationCost,
            ];
        });

        $refs = $normalizedTasks->pluck('client_ref')->filter();

        if ($refs->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['tasks_json' => 'add_taskのclient_refが重複しています。']);
        }

        $planUpdate = collect($operations)->firstWhere('type', 'update_plan');
        $normalizedDeadline = null;

        if ($planUpdate !== null) {
            $deadlineValue = trim((string) ($planUpdate['deadline'] ?? ''));
            if ($deadlineValue === '') {
                throw ValidationException::withMessages(['tasks_json' => 'update_planを使う場合はdeadlineを指定してください。']);
            }

            try {
                $normalizedDeadline = Carbon::parse($deadlineValue)->format('Y-m-d');
            } catch (\Throwable $e) {
                throw ValidationException::withMessages(['tasks_json' => 'update_planのdeadlineはYYYY-MM-DD形式にしてください。']);
            }

            if (Carbon::parse($normalizedDeadline)->lt($plan->start_date->copy()->startOfDay())) {
                throw ValidationException::withMessages(['tasks_json' => '期限は開始日以降にしてください。']);
            }
        }

        $reorder = collect($operations)->firstWhere('type', 'reorder_tasks');

        if ($reorder !== null) {
            $orderRefs = collect($reorder['items'] ?? [])->map(fn ($item) => is_array($item) ? ($item['task_ref'] ?? null) : null);

            if ($orderRefs->filter()->count() !== $normalizedTasks->count()
                || $orderRefs->unique()->count() !== $normalizedTasks->count()
                || $orderRefs->diff($refs)->isNotEmpty()
                || $refs->diff($orderRefs)->isNotEmpty()) {
                throw ValidationException::withMessages(['tasks_json' => 'reorder_tasksには、生成する全タスクのclient_refを重複なく指定してください。']);
            }

            $byRef = $normalizedTasks->keyBy('client_ref');
            $normalizedTasks = $orderRefs->map(fn ($ref) => $byRef->get($ref))->values();
        }

        $metricsBefore = $progressService->calculate($plan);

        DB::transaction(function () use ($plan, $decoded, $json, $normalizedTasks, $normalizedDeadline, $metricsBefore, $progressService) {
            $applied = [];

            if ($normalizedDeadline !== null) {
                $plan->update(['deadline' => $normalizedDeadline]);
                $applied[] = ['type' => 'update_plan', 'deadline' => $normalizedDeadline];
            }

            $sortOrder = (int) $plan->tasks()->max('sort_order');
            foreach ($normalizedTasks as $taskData) {
                $task = Task::create(array_merge($taskData, [
                    'plan_id' => $plan->id,
                    'sort_order' => ++$sortOrder,
                ]));
                $applied[] = array_merge(['type' => 'create_task', 'created_task_id' => $task->id], $taskData);
            }

            $plan->unsetRelation('tasks');
            $plan->unsetRelation('workLogs');

            PlanAdjustment::create([
                'plan_id' => $plan->id,
                'flow' => 'plan_generation',
                'summary' => trim((string) ($decoded['summary'] ?? 'AIが初期計画を生成')),
                'user_input' => [],
                'prompt' => 'AI計画生成画面から読み込み',
                'response_json' => $json,
                'applied_operations' => $applied,
                'metrics_before' => $metricsBefore,
                'metrics_after' => $progressService->calculate($plan),
                'applied_at' => now(),
            ]);
        });

        return redirect()->route('plans.show', $plan)->with('success', 'AIが生成した初期タスクを登録しました。');
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
            $candidate = trim(substr($trimmed, $start, $end - $start + 1));
            json_decode($candidate, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $candidate;
            }
        }

        return $trimmed;
    }

    private function authorizePlanOwner(Plan $plan): void
    {
        app(PlanOwnershipService::class)->authorizePlan(request(), $plan);
    }
}
