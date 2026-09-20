<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Arr;

class PlanReviewJsonCompatibilityService
{
    /**
     * Deterministically adapt common external-AI variations into the canonical
     * Canovia plan-review envelope. This service never invents IDs or semantic
     * values; it only uses the plan already selected by the current route.
     */
    public function adapt(Plan $plan, array $decoded, ?array $draft = null): array
    {
        $notes = [];

        if (array_is_list($decoded)) {
            $decoded = ['operations' => $decoded];
            $notes[] = '最上位配列をoperationsとして読み込みました。';
        }

        foreach (['result', 'data', 'payload', 'response'] as $wrapper) {
            if (! isset($decoded[$wrapper]) || ! is_array($decoded[$wrapper])) {
                continue;
            }

            $inner = $decoded[$wrapper];
            if (isset($inner['operations']) || isset($inner['tasks']) || array_is_list($inner)) {
                $decoded = array_merge(Arr::except($decoded, [$wrapper]), $inner);
                $notes[] = "{$wrapper}内のJSONを本体として読み込みました。";
                break;
            }
        }

        if (! isset($decoded['operations'])) {
            foreach (['actions', 'changes', 'updates'] as $alias) {
                if (isset($decoded[$alias]) && is_array($decoded[$alias])) {
                    $decoded['operations'] = $decoded[$alias];
                    unset($decoded[$alias]);
                    $notes[] = "{$alias}配列をoperationsとして読み込みました。";
                    break;
                }
            }
        }

        if (isset($decoded['operations'], $decoded['tasks'])
            && is_array($decoded['operations'])
            && is_array($decoded['tasks'])) {
            if ($decoded['operations'] !== []) {
                unset($decoded['tasks']);
                $notes[] = 'operationsとtasksが両方あったため、明示されたoperationsを優先しました。';
            } else {
                unset($decoded['operations']);
            }
        }

        if (! isset($decoded['operations']) && isset($decoded['tasks']) && is_array($decoded['tasks'])) {
            $decoded['operations'] = array_map(
                fn ($task) => $this->taskListItemToOperation($plan, $task, $notes),
                $decoded['tasks']
            );
            unset($decoded['tasks']);
            $notes[] = 'tasks配列を計画更新用operationsへ変換しました。';
        }

        $expectedFlow = in_array(($draft['flow'] ?? null), ['plan_update', 'result_recording'], true)
            ? $draft['flow']
            : 'result_recording';

        if (($decoded['schema_version'] ?? null) !== '2.0') {
            $decoded['schema_version'] = '2.0';
            $notes[] = 'schema_versionを現在の2.0として扱いました。';
        }

        if (($decoded['flow'] ?? null) !== $expectedFlow) {
            $decoded['flow'] = $expectedFlow;
            $notes[] = "flowをこの画面の{$expectedFlow}へ合わせました。";
        }

        if (array_key_exists('action', $decoded)) {
            unset($decoded['action']);
            $notes[] = 'actionはoperations内容からCanovia側で再判定するため省略しました。';
        }

        $incomingTarget = $decoded['target_plan'] ?? null;
        $canonicalTarget = [
            'id' => (int) $plan->id,
            'title' => $plan->title,
            'category' => $plan->category,
        ];
        if ($incomingTarget !== $canonicalTarget) {
            $decoded['target_plan'] = $canonicalTarget;
            $notes[] = '対象計画は現在開いている計画を正として補正しました。';
        }

        if (! isset($decoded['summary']) || trim((string) $decoded['summary']) === '') {
            $decoded['summary'] = '外部AIから受け取った計画更新案';
        }

        if (isset($decoded['operations']) && is_array($decoded['operations'])) {
            $decoded['operations'] = array_map(
                fn ($operation) => $this->adaptOperation($plan, $operation, $notes),
                $decoded['operations']
            );

            if ($decoded['operations'] !== [] && $notes !== [] && is_array($decoded['operations'][0])) {
                $existing = $decoded['operations'][0]['_normalization_notes'] ?? [];
                $decoded['operations'][0]['_normalization_notes'] = array_values(array_unique(array_merge(
                    is_array($existing) ? $existing : [],
                    $notes
                )));
            }
        }

        return $decoded;
    }

    private function taskListItemToOperation(Plan $plan, mixed $task, array &$notes): mixed
    {
        if (! is_array($task)) {
            return $task;
        }

        $taskId = $task['task_id'] ?? $task['id'] ?? null;
        $isExisting = is_numeric($taskId) && $plan->tasks()->whereKey((int) $taskId)->exists();

        $task['type'] = $isExisting ? 'revise_task' : 'add_task';
        if ($isExisting) {
            $task['task_id'] = (int) $taskId;
        }
        unset($task['id']);

        return $task;
    }

    private function adaptOperation(Plan $plan, mixed $operation, array &$notes): mixed
    {
        if (! is_array($operation)) {
            return $operation;
        }

        $operation = $this->renameAliases($operation);

        $rawType = trim((string) ($operation['type'] ?? $operation['operation'] ?? ''));
        $typeKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $rawType) ?? $rawType);
        $typeKey = str_replace(['-', ' '], '_', $typeKey);

        $typeAliases = [
            'record_result' => 'record_result',
            'record_work' => 'record_result',
            'work_log' => 'record_result',
            'create_work_log' => 'record_result',
            'add_task' => 'add_task',
            'create_task' => 'add_task',
            'new_task' => 'add_task',
            'revise_task' => 'revise_task',
            'update_task' => 'revise_task',
            'edit_task' => 'revise_task',
            'keep_task' => 'keep_task',
            'retain_task' => 'keep_task',
            'archive_task' => 'archive_task',
            'cancel_task' => 'archive_task',
            'remove_task' => 'archive_task',
            'update_plan' => 'update_plan',
            'revise_plan' => 'update_plan',
            'update_availability' => 'update_availability',
            'update_schedule' => 'update_availability',
            'reorder_tasks' => 'reorder_tasks',
            'reorder_task' => 'reorder_tasks',
        ];

        if (isset($typeAliases[$typeKey])) {
            $canonical = $typeAliases[$typeKey];
            if ($canonical !== $rawType) {
                $notes[] = "操作種類「{$rawType}」を「{$canonical}」として読み込みました。";
            }
            $operation['type'] = $canonical;
            unset($operation['operation']);
        }

        $type = $operation['type'] ?? null;

        if (in_array($type, ['revise_task', 'keep_task', 'archive_task'], true)) {
            $operation = $this->resolveExistingTaskReference($plan, $operation, $type, $notes);
        }

        if ($type === 'record_result' && isset($operation['task_id'])
            && is_numeric($operation['task_id'])
            && ! $plan->tasks()->whereKey((int) $operation['task_id'])->exists()) {
            $resolved = $this->findTaskByProvidedTitle($plan, $operation);
            if ($resolved) {
                $operation['task_id'] = (int) $resolved->id;
                $notes[] = "作業実績のtask_idをタスク名「{$resolved->title}」から補正しました。";
            }
        }

        if (isset($operation['status'])) {
            $status = mb_strtolower(trim((string) $operation['status']));
            $statusAliases = [
                'not_started' => 'todo', 'pending' => 'todo', '未着手' => 'todo',
                'in_progress' => 'doing', 'progress' => 'doing', '進行中' => 'doing', '作業中' => 'doing',
                'completed' => 'done', 'complete' => 'done', '完了' => 'done',
            ];
            if (isset($statusAliases[$status])) {
                $operation['status'] = $statusAliases[$status];
                $notes[] = "status「{$status}」を「{$statusAliases[$status]}」として読み込みました。";
            }
        }

        if (isset($operation['difficulty'])) {
            $difficulty = mb_strtolower(trim((string) $operation['difficulty']));
            $difficultyAliases = [
                '簡単' => 'easy', 'easy' => 'easy',
                '普通' => 'normal', 'normal' => 'normal',
                '難しい' => 'hard', 'hard' => 'hard',
                '苦戦' => 'hard', '詰まり' => 'stuck', '詰まっている' => 'stuck', 'stuck' => 'stuck',
            ];
            if (isset($difficultyAliases[$difficulty])) {
                $operation['difficulty'] = $difficultyAliases[$difficulty];
            }
        }

        if ($type === 'reorder_tasks' && isset($operation['items']) && is_array($operation['items'])) {
            $operation['items'] = array_map(function ($item) use ($plan, &$notes) {
                if (! is_array($item) || isset($item['task_id']) || isset($item['task_ref'])) {
                    return $item;
                }
                $title = trim((string) ($item['task_title'] ?? $item['title'] ?? $item['name'] ?? ''));
                if ($title === '') {
                    return $item;
                }
                $task = $this->findUniqueTaskByTitle($plan, $title);
                if (! $task) {
                    return $item;
                }
                $notes[] = "順序指定の「{$title}」をtask_id {$task->id}へ変換しました。";
                return ['task_id' => (int) $task->id];
            }, $operation['items']);
        }

        return $operation;
    }

    private function renameAliases(array $operation): array
    {
        $aliases = [
            'taskId' => 'task_id',
            'taskID' => 'task_id',
            'taskTitle' => 'task_title',
            'taskName' => 'task_name',
            'currentTitle' => 'current_title',
            'clientRef' => 'client_ref',
            'taskRef' => 'task_ref',
            'estimatedMinutes' => 'estimated_minutes',
            'remainingMinutes' => 'remaining_minutes',
            'progressPercent' => 'progress_percent',
            'progress' => 'progress_percent',
            'progressReason' => 'progress_reason',
            'progressOrigin' => 'progress_origin',
            'sourceTaskIds' => 'source_task_ids',
            'nextActionNote' => 'next_action_note',
            'actualMinutes' => 'actual_minutes',
            'workedOn' => 'worked_on',
            'progressAfterPercent' => 'progress_after_percent',
            'remainingMinutesAfter' => 'remaining_minutes_after',
            'activationCost' => 'activation_cost',
            'weeklySchedule' => 'weekly_schedule',
            'replaceWeekly' => 'replace_weekly',
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $operation) && ! array_key_exists($to, $operation)) {
                $operation[$to] = $operation[$from];
                unset($operation[$from]);
            }
        }

        return $operation;
    }

    private function resolveExistingTaskReference(Plan $plan, array $operation, string $type, array &$notes): array
    {
        $taskId = $operation['task_id'] ?? null;
        if (is_numeric($taskId) && $plan->tasks()->whereKey((int) $taskId)->exists()) {
            return $operation;
        }

        $task = $this->findTaskByProvidedTitle($plan, $operation, allowTitle: $type !== 'revise_task');
        if (! $task && $type === 'revise_task' && isset($operation['title'])) {
            $task = $this->findUniqueTaskByTitle($plan, trim((string) $operation['title']));
        }

        if ($task) {
            $previous = $taskId;
            $operation['task_id'] = (int) $task->id;
            $notes[] = $previous
                ? "存在しないtask_id {$previous}をタスク名「{$task->title}」から{$task->id}へ補正しました。"
                : "タスク名「{$task->title}」からtask_id {$task->id}を補完しました。";
        }

        return $operation;
    }

    private function findTaskByProvidedTitle(Plan $plan, array $operation, bool $allowTitle = true): ?Task
    {
        $keys = ['task_title', 'task_name', 'current_title'];
        if ($allowTitle) {
            $keys[] = 'title';
            $keys[] = 'name';
        }

        foreach ($keys as $key) {
            $title = trim((string) ($operation[$key] ?? ''));
            if ($title === '') {
                continue;
            }
            $task = $this->findUniqueTaskByTitle($plan, $title);
            if ($task) {
                return $task;
            }
        }

        return null;
    }

    private function findUniqueTaskByTitle(Plan $plan, string $title): ?Task
    {
        $needle = mb_strtolower(trim($title));
        if ($needle === '') {
            return null;
        }

        $matches = $plan->tasks()
            ->get()
            ->filter(fn (Task $task) => mb_strtolower(trim($task->title)) === $needle)
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
