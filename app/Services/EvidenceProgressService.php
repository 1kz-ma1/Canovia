<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskEvidence;

class EvidenceProgressService
{
    /**
     * Return a conservative progress suggestion derived from strong Evidence.
     *
     * This service does not mutate Tasks. Automatic application is deliberately
     * left for a later policy layer so Evidence collection and user control stay
     * separate.
     */
    public function recommendPercent(Task $task, TaskEvidence $evidence): ?int
    {
        if ($evidence->type !== 'study_practice_assessed' || $evidence->confidence < 0.95) {
            return null;
        }

        $recommended = filter_var(
            data_get($evidence->metadata, 'recommended_task_progress_percent'),
            FILTER_VALIDATE_INT,
        );

        if ($recommended === false) {
            return null;
        }

        return max(
            (int) $task->progress_percent,
            max(0, min(100, (int) $recommended)),
        );
    }
}
