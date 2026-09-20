<?php

namespace App\Services;

use App\Models\Plan;

class PlanGenerationJsonCompatibilityService
{
    /**
     * Normalize common external-AI variations for the initial plan-generation
     * flow. The route-selected Plan is authoritative, so this service never
     * allows pasted JSON to redirect writes to another plan.
     *
     * @return array{decoded: array, notes: list<string>}
     */
    public function adapt(Plan $plan, array $decoded): array
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
                $decoded = array_merge(
                    array_diff_key($decoded, [$wrapper => true]),
                    $inner
                );
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

        if (! isset($decoded['operations']) && isset($decoded['tasks']) && is_array($decoded['tasks'])) {
            $decoded['operations'] = array_map(
                fn ($task, $index) => $this->taskToOperation($task, (int) $index),
                $decoded['tasks'],
                array_keys($decoded['tasks'])
            );
            unset($decoded['tasks']);
            $notes[] = 'tasks配列をadd_task操作へ変換しました。';
        }

        $decoded['schema_version'] = '2.0';
        $decoded['flow'] = 'plan_generation';
        $decoded['target_plan'] = [
            'id' => (int) $plan->id,
            'title' => $plan->title,
            'category' => $plan->category,
        ];

        if (! isset($decoded['summary']) || trim((string) $decoded['summary']) === '') {
            $decoded['summary'] = '外部AIから受け取った初期計画';
        }

        if (! isset($decoded['operations']) || ! is_array($decoded['operations'])) {
            return ['decoded' => $decoded, 'notes' => $notes];
        }

        $usedRefs = [];
        $taskRefs = [];
        $normalizedOperations = [];

        foreach ($decoded['operations'] as $index => $operation) {
            if (! is_array($operation)) {
                $normalizedOperations[] = $operation;
                continue;
            }

            $operation = $this->renameAliases($operation);
            $rawType = trim((string) ($operation['type'] ?? $operation['operation'] ?? ''));
            $typeKey = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $rawType) ?? $rawType);
            $typeKey = str_replace(['-', ' '], '_', $typeKey);

            $typeAliases = [
                'add_task' => 'add_task',
                'create_task' => 'add_task',
                'new_task' => 'add_task',
                'task' => 'add_task',
                'update_plan' => 'update_plan',
                'plan_update' => 'update_plan',
                'reorder_tasks' => 'reorder_tasks',
                'reorder_task' => 'reorder_tasks',
                'sort_tasks' => 'reorder_tasks',
            ];

            if (isset($typeAliases[$typeKey])) {
                $operation['type'] = $typeAliases[$typeKey];
                unset($operation['operation']);
                if ($rawType !== '' && $rawType !== $operation['type']) {
                    $notes[] = "操作種類「{$rawType}」を「{$operation['type']}」として読み込みました。";
                }
            }

            if (($operation['type'] ?? null) === 'add_task') {
                $ref = trim((string) ($operation['client_ref'] ?? ''));
                if ($ref === '' || isset($usedRefs[$ref])) {
                    $ref = $this->nextClientRef($index + 1, $usedRefs);
                    $operation['client_ref'] = $ref;
                    $notes[] = 'add_taskのclient_refをCanovia側で補完しました。';
                }
                $usedRefs[$ref] = true;
                $taskRefs[] = $ref;

                $estimated = $this->toIntOrNull($operation['estimated_minutes'] ?? null);
                $remaining = $this->toIntOrNull($operation['remaining_minutes'] ?? null);

                if ($estimated !== null && $remaining === null) {
                    $operation['remaining_minutes'] = $estimated;
                    $notes[] = "「{$ref}」のremaining_minutesをestimated_minutesから補完しました。";
                }

                if (! array_key_exists('progress_percent', $operation)) {
                    $operation['progress_percent'] = 0;
                }
                if (! array_key_exists('progress_reason', $operation)) {
                    $operation['progress_reason'] = '未着手';
                }
                if (! array_key_exists('status', $operation)) {
                    $operation['status'] = 'todo';
                }

                if (isset($operation['status'])) {
                    $status = mb_strtolower(trim((string) $operation['status']));
                    $statusAliases = [
                        'not_started' => 'todo',
                        'pending' => 'todo',
                        '未着手' => 'todo',
                        'in_progress' => 'doing',
                        'progress' => 'doing',
                        '進行中' => 'doing',
                        '作業中' => 'doing',
                        'completed' => 'done',
                        'complete' => 'done',
                        '完了' => 'done',
                    ];
                    if (isset($statusAliases[$status])) {
                        $operation['status'] = $statusAliases[$status];
                    }
                }

                $priority = $this->toIntOrNull($operation['priority'] ?? null);
                if ($priority === null) {
                    $operation['priority'] = 3;
                } elseif ($priority < 1 || $priority > 5) {
                    $operation['priority'] = max(1, min(5, $priority));
                    $notes[] = "「{$ref}」のpriority {$priority}を許容範囲1〜5へ補正しました。";
                }

                $activationCost = $this->toIntOrNull($operation['activation_cost'] ?? null);
                if ($activationCost === null) {
                    $operation['activation_cost'] = 3;
                } elseif ($activationCost < 1 || $activationCost > 5) {
                    $operation['activation_cost'] = max(1, min(5, $activationCost));
                    $notes[] = "「{$ref}」のactivation_cost {$activationCost}を許容範囲1〜5へ補正しました。";
                }
            }

            $normalizedOperations[] = $operation;
        }

        $hasReorder = false;
        foreach ($normalizedOperations as &$operation) {
            if (! is_array($operation) || ($operation['type'] ?? null) !== 'reorder_tasks') {
                continue;
            }

            $hasReorder = true;
            $orderRefs = [];
            foreach (($operation['items'] ?? []) as $item) {
                if (is_string($item)) {
                    $orderRefs[] = $item;
                } elseif (is_array($item)) {
                    $orderRefs[] = $item['task_ref'] ?? $item['client_ref'] ?? null;
                }
            }
            $orderRefs = array_values(array_filter($orderRefs, fn ($ref) => is_string($ref) && $ref !== ''));

            if (count($orderRefs) !== count($taskRefs)
                || count(array_unique($orderRefs)) !== count($taskRefs)
                || array_diff($taskRefs, $orderRefs) !== []
                || array_diff($orderRefs, $taskRefs) !== []) {
                $operation['items'] = array_map(fn ($ref) => ['task_ref' => $ref], $taskRefs);
                $notes[] = 'reorder_tasksの参照不整合を、生成タスクの順番を保ったまま補正しました。';
            } else {
                $operation['items'] = array_map(fn ($ref) => ['task_ref' => $ref], $orderRefs);
            }
        }
        unset($operation);

        if (! $hasReorder && count($taskRefs) > 1) {
            $normalizedOperations[] = [
                'type' => 'reorder_tasks',
                'items' => array_map(fn ($ref) => ['task_ref' => $ref], $taskRefs),
                'reason' => 'AIが出力したadd_taskの順番をそのまま使用',
            ];
            $notes[] = 'reorder_tasksがなかったため、add_taskの出力順を実行順として補完しました。';
        }

        $decoded['operations'] = $normalizedOperations;

        return [
            'decoded' => $decoded,
            'notes' => array_values(array_unique($notes)),
        ];
    }

    private function taskToOperation(mixed $task, int $index): mixed
    {
        if (! is_array($task)) {
            return $task;
        }

        $task['type'] = 'add_task';
        $task['client_ref'] ??= 'task_' . ($index + 1);

        return $task;
    }

    private function renameAliases(array $operation): array
    {
        $aliases = [
            'clientRef' => 'client_ref',
            'estimatedMinutes' => 'estimated_minutes',
            'remainingMinutes' => 'remaining_minutes',
            'progressPercent' => 'progress_percent',
            'progress' => 'progress_percent',
            'progressReason' => 'progress_reason',
            'activationCost' => 'activation_cost',
            'taskRef' => 'task_ref',
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $operation) && ! array_key_exists($to, $operation)) {
                $operation[$to] = $operation[$from];
                unset($operation[$from]);
            }
        }

        return $operation;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        return $filtered === false ? null : (int) $filtered;
    }

    /** @param array<string,bool> $usedRefs */
    private function nextClientRef(int $seed, array $usedRefs): string
    {
        $number = max(1, $seed);
        do {
            $candidate = 'task_' . $number++;
        } while (isset($usedRefs[$candidate]));

        return $candidate;
    }
}
