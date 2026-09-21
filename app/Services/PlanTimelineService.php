<?php

namespace App\Services;

use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlanTimelineService
{
    public function build(Plan $plan): Collection
    {
        $plan->loadMissing(['workLogs.task', 'adjustments']);

        $results = $plan->workLogs->toBase()->map(function ($log) {
            return [
                'type' => 'result',
                'occurred_at' => $log->created_at ?? Carbon::parse($log->worked_on)->endOfDay(),
                'date_label' => Carbon::parse($log->worked_on)->format('Y-m-d'),
                'title' => $log->task?->title ?? $log->task_title_snapshot ?? '計画全体の作業',
                'summary' => $log->outcome ?: $log->memo,
                'actual_minutes' => $log->actual_minutes,
                'progress_before' => $log->progress_before_percent,
                'progress_after' => $log->progress_after_percent,
                'remaining_before' => $log->remaining_minutes_before,
                'remaining_after' => $log->remaining_minutes_after,
                'model' => $log,
            ];
        });

        $changes = $plan->adjustments->toBase()->map(function ($adjustment) {
            return [
                'type' => 'change',
                'occurred_at' => $adjustment->applied_at ?? $adjustment->created_at,
                'date_label' => ($adjustment->applied_at ?? $adjustment->created_at)?->format('Y-m-d H:i'),
                'title' => $adjustment->summary ?: '計画を更新',
                'summary' => $this->operationSummary($adjustment->applied_operations ?? []),
                'flow' => $adjustment->flow,
                'metrics_before' => $adjustment->metrics_before,
                'metrics_after' => $adjustment->metrics_after,
                'operation_count' => count($adjustment->applied_operations ?? []),
                'model' => $adjustment,
            ];
        });

        return $results
            ->concat($changes)
            ->sortByDesc(fn (array $event) => $event['occurred_at']?->timestamp ?? 0)
            ->values();
    }

    private function operationSummary(array $operations): string
    {
        $labels = [
            'update_plan' => '計画情報',
            'create_work_log' => '実績',
            'create_task' => 'タスク追加',
            'update_task' => 'タスク更新',
            'keep_task' => 'タスク維持',
            'cancel_task' => 'タスク中止',
            'reorder_tasks' => '後続タスク再編',
        ];

        return collect($operations)
            ->groupBy('type')
            ->map(fn ($items, $type) => ($labels[$type] ?? $type) . ' ' . $items->count() . '件')
            ->values()
            ->implode('・');
    }
}
