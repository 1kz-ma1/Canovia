<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskMilestone;

class TaskMilestoneProgressService
{
    /**
     * Return weighted milestone completion, or null when a Task has no milestones.
     * This is a progress signal only; callers decide whether it is authoritative.
     */
    public function calculate(Task $task): ?int
    {
        $milestones = $task->relationLoaded('milestones')
            ? $task->milestones
            : $task->milestones()->get();

        if ($milestones->isEmpty()) {
            return null;
        }

        $totalWeight = (int) $milestones->sum(fn (TaskMilestone $milestone) => max(1, (int) $milestone->weight));
        if ($totalWeight <= 0) {
            return null;
        }

        $completedWeight = (int) $milestones
            ->filter(fn (TaskMilestone $milestone) => $milestone->status === 'done')
            ->sum(fn (TaskMilestone $milestone) => max(1, (int) $milestone->weight));

        return max(0, min(100, (int) round(($completedWeight / $totalWeight) * 100)));
    }

    public function markDone(TaskMilestone $milestone): TaskMilestone
    {
        if ($milestone->status !== 'done') {
            $milestone->update([
                'status' => 'done',
                'completed_at' => now(),
            ]);
        }

        return $milestone->fresh();
    }
}
