<?php

namespace App\Services;

use App\Models\Plan;
use Carbon\Carbon;

class PlanPriorityService
{
    public function __construct(private readonly PlanProgressService $progressService) {}

    /**
     * @return array{priority:int,auto_priority:int,score:float,mode:string,reasons:array<int,string>,auto_reasons:array<int,string>}
     */
    public function evaluate(Plan $plan): array
    {
        $auto = $this->autoEvaluation($plan);
        $mode = in_array((string) $plan->priority_mode, ['auto', 'manual'], true)
            ? (string) $plan->priority_mode
            : 'auto';

        if ($mode === 'manual') {
            return [
                'priority' => max(1, min(5, (int) ($plan->priority ?? 3))),
                'auto_priority' => $auto['priority'],
                'score' => $auto['score'],
                'mode' => 'manual',
                'reasons' => ['ユーザーが優先度を手動固定しています'],
                'auto_reasons' => $auto['reasons'],
            ];
        }

        return [
            ...$auto,
            'auto_priority' => $auto['priority'],
            'mode' => 'auto',
            'auto_reasons' => $auto['reasons'],
        ];
    }

    /**
     * Auto priority intentionally uses objective Plan state and the user's
     * configured capacity/history. Short-term readiness never changes goal importance.
     *
     * @return array{priority:int,score:float,reasons:array<int,string>}
     */
    private function autoEvaluation(Plan $plan): array
    {
        $plan->loadMissing(['tasks', 'workLogs', 'availabilityRules', 'availabilityOverrides']);
        $progress = $this->progressService->calculate($plan);

        $score = 0.0;
        $reasons = [];
        $remainingDays = $progress['remaining_days'];
        $expected = $progress['expected_progress_percent'];
        $actual = (float) $progress['weighted_progress_percent'];
        $gap = $expected === null ? 0 : max(0, (float) $expected - $actual);

        if ($remainingDays !== null) {
            $deadlineScore = match (true) {
                $remainingDays < 0 => 48,
                $remainingDays <= 2 => 38,
                $remainingDays <= 7 => 28,
                $remainingDays <= 14 => 20,
                $remainingDays <= 30 => 12,
                default => 4,
            };
            $score += $deadlineScore;

            if ($remainingDays <= 7) {
                $reasons[] = $remainingDays < 0
                    ? '期限を過ぎています'
                    : "期限まで{$remainingDays}日です";
            }
        }

        if ($gap >= 5) {
            $score += min(26, $gap * 0.7);
            $reasons[] = '期待進捗より実績進捗が遅れています';
        }

        if (($progress['status'] ?? null) === '作業時間不足') {
            $score += 28;
            $reasons[] = '現在の可処分時間では必要作業量が不足しています';
        } elseif (($progress['status'] ?? null) === '期限切れ') {
            $score += 22;
        } elseif (($progress['status'] ?? null) === '遅れ気味') {
            $score += 14;
        }

        $capacityRatio = $progress['required_capacity_ratio'];
        if (is_numeric($capacityRatio)) {
            if ((float) $capacityRatio >= 1) {
                $score += 20;
                $reasons[] = '残り作業量が利用可能時間に対して大きいです';
            } elseif ((float) $capacityRatio >= 0.75) {
                $score += 12;
            } elseif ((float) $capacityRatio >= 0.5) {
                $score += 6;
            }
        }

        $lastWorked = $plan->workLogs->max(fn ($log) => $log->worked_on?->timestamp ?? 0);
        if ($lastWorked > 0 && (int) now()->diffInDays(Carbon::createFromTimestamp($lastWorked)) >= 7) {
            $score += 5;
            $reasons[] = '最近このPlanの作業実績がありません';
        }

        if ((int) ($progress['remaining_minutes'] ?? 0) <= 0) {
            $score = 0;
        }

        $priority = match (true) {
            $score >= 62 => 1,
            $score >= 36 => 2,
            $score >= 20 => 3,
            $score >= 8 => 4,
            default => 5,
        };

        if ($reasons === []) {
            $reasons[] = $remainingDays === null
                ? '期限や遅れの強いシグナルがないため自動優先度は低めです'
                : '期限・進捗・残作業量から自動判定しています';
        }

        return [
            'priority' => $priority,
            'score' => round($score, 1),
            'reasons' => array_slice(array_values(array_unique($reasons)), 0, 3),
        ];
    }
}
