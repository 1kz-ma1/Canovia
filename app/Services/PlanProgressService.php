<?php

namespace App\Services;

use App\Models\Plan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlanProgressService
{
    public function calculate(Plan $plan): array
    {
        $plan->loadMissing(['tasks', 'workLogs', 'availabilityRules', 'availabilityOverrides']);

        $activeTasks = $plan->tasks
            ->reject(fn ($task) => $task->status === 'cancelled')
            ->values();
        $workLogs = $plan->workLogs;

        $totalEstimatedMinutes = (int) $activeTasks->sum('estimated_minutes');
        $totalActualMinutes = (int) $workLogs->sum('actual_minutes');

        $today = Carbon::today();
        $deadline = $plan->deadline?->copy()->startOfDay();
        $remainingDays = $deadline ? (int) $today->diffInDays($deadline, false) : null;
        $weightedProgressPercent = $this->calculateWeightedProgressPercent($activeTasks);

        $remainingMinutes = (int) $activeTasks->sum(function ($task) {
            if ($task->status === 'done') {
                return 0;
            }
            if ($task->remaining_minutes !== null) {
                return max((int) $task->remaining_minutes, 0);
            }
            return max((int) round($task->estimated_minutes * (100 - $task->progress_percent) / 100), 0);
        });

        $remainingMinutesByTime = max($totalEstimatedMinutes - $totalActualMinutes, 0);
        $availability = new PlanAvailabilityService();
        $availabilityConfigured = $availability->isConfigured($plan);
        $remainingAvailableMinutes = $availabilityConfigured && $deadline && $remainingDays >= 0
            ? $availability->capacityBetween($plan, $today, $deadline)
            : null;
        $todayAvailableMinutes = $availabilityConfigured
            ? $availability->minutesForDate($plan, $today)
            : null;
        $todayWorkedMinutes = (int) $workLogs
            ->filter(fn ($log) => $log->worked_on?->isToday())
            ->sum('actual_minutes');

        $requiredCapacityRatio = $availabilityConfigured && $remainingAvailableMinutes !== null && $remainingAvailableMinutes > 0
            ? round($remainingMinutes / $remainingAvailableMinutes, 3)
            : null;

        if (! $deadline) {
            $dailyRequiredMinutes = 0;
            $expectedProgressPercent = null;
            $status = '期限未設定';
        } else {
            if ($availabilityConfigured) {
                if ($remainingMinutes <= 0) {
                    $dailyRequiredMinutes = 0;
                } elseif (($remainingAvailableMinutes ?? 0) <= 0) {
                    $dailyRequiredMinutes = $remainingMinutes;
                } else {
                    $dailyRequiredMinutes = (int) ceil(max(0, (int) $todayAvailableMinutes) * $requiredCapacityRatio);
                }
            } else {
                $dailyRequiredMinutes = $remainingDays > 0
                    ? (int) ceil($remainingMinutes / $remainingDays)
                    : $remainingMinutes;
            }

            $expectedProgressPercent = $this->calculateExpectedProgressPercent($plan, $availability, $availabilityConfigured);
            $status = $this->judgeStatus($remainingDays, $weightedProgressPercent, $expectedProgressPercent);

            if ($availabilityConfigured && $remainingAvailableMinutes !== null && $remainingMinutes > $remainingAvailableMinutes && $remainingDays >= 0) {
                $status = '作業時間不足';
            }
        }

        return [
            'total_estimated_minutes' => $totalEstimatedMinutes,
            'total_actual_minutes' => $totalActualMinutes,
            'remaining_days' => $remainingDays,
            'weighted_progress_percent' => $weightedProgressPercent,
            'remaining_minutes' => $remainingMinutes,
            'remaining_minutes_by_progress' => $remainingMinutes,
            'remaining_minutes_by_time' => $remainingMinutesByTime,
            'daily_required_minutes' => $dailyRequiredMinutes,
            'expected_progress_percent' => $expectedProgressPercent,
            'status' => $status,
            'cancelled_task_count' => $plan->tasks->where('status', 'cancelled')->count(),
            'availability_configured' => $availabilityConfigured,
            'today_available_minutes' => $todayAvailableMinutes,
            'today_available_remaining_minutes' => $todayAvailableMinutes === null ? null : max(0, $todayAvailableMinutes - $todayWorkedMinutes),
            'remaining_available_minutes' => $remainingAvailableMinutes,
            'required_capacity_ratio' => $requiredCapacityRatio,
        ];
    }

    private function calculateWeightedProgressPercent(Collection $tasks): float
    {
        $totalEstimatedMinutes = $tasks->sum('estimated_minutes');
        if ($totalEstimatedMinutes <= 0) {
            return 0;
        }
        $weightedProgress = $tasks->sum(fn ($task) => $task->estimated_minutes * $task->progress_percent);
        return round($weightedProgress / $totalEstimatedMinutes, 1);
    }

    private function calculateExpectedProgressPercent(
        Plan $plan,
        PlanAvailabilityService $availability,
        bool $availabilityConfigured
    ): ?float {
        if (! $plan->deadline) {
            return null;
        }

        $startDate = Carbon::parse($plan->start_date)->startOfDay();
        $deadline = Carbon::parse($plan->deadline)->startOfDay();
        $today = Carbon::today();

        if ($availabilityConfigured) {
            $totalCapacity = $availability->capacityBetween($plan, $startDate, $deadline);
            if ($totalCapacity > 0) {
                if ($today->lte($startDate)) return 0;
                if ($today->gt($deadline)) return 100;
                $elapsedCapacity = $availability->capacityBetween($plan, $startDate, $today->copy()->subDay());
                return round(min(1, $elapsedCapacity / $totalCapacity) * 100, 1);
            }
        }

        $totalDays = max($startDate->diffInDays($deadline), 1);
        $elapsedDays = $startDate->diffInDays($today, false);
        if ($elapsedDays <= 0) return 0;
        if ($elapsedDays >= $totalDays) return 100;
        return round(($elapsedDays / $totalDays) * 100, 1);
    }

    private function judgeStatus(int $remainingDays, float $actualProgressPercent, float $expectedProgressPercent): string
    {
        if ($remainingDays < 0) return '期限切れ';
        if ($actualProgressPercent + 5 < $expectedProgressPercent) return '遅れ気味';
        if ($actualProgressPercent >= $expectedProgressPercent + 5) return '順調';
        return '予定通り';
    }
}
