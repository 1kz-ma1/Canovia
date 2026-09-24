<?php

namespace App\Services;

use App\Enums\EvidenceSource;
use App\Models\StudyPracticeAttempt;
use App\Models\Task;
use App\Models\TaskEvidence;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class TaskEvidenceService
{
    /**
     * Store a factual observation about Task execution.
     *
     * externalKey makes connector/native events idempotent. When no stable key
     * exists, the observation is appended as a new Evidence record.
     *
     * @param array<string, mixed> $metadata
     */
    public function record(
        Task $task,
        EvidenceSource $source,
        string $type,
        array $metadata = [],
        float $confidence = 1.0,
        ?string $externalKey = null,
        ?int $userId = null,
        ?string $actorToken = null,
        ?CarbonInterface $occurredAt = null,
    ): TaskEvidence {
        $type = Str::limit(trim($type), 64, '');
        $externalKey = filled($externalKey) ? Str::limit(trim((string) $externalKey), 191, '') : null;

        $values = [
            'plan_id' => (int) $task->plan_id,
            'user_id' => $userId,
            'actor_token' => $userId ? null : $actorToken,
            'type' => $type,
            'confidence' => max(0, min(1, $confidence)),
            'occurred_at' => $occurredAt ?? now(),
            'metadata' => $metadata,
        ];

        if ($externalKey !== null) {
            return TaskEvidence::query()->updateOrCreate(
                [
                    'task_id' => (int) $task->id,
                    'source' => $source->value,
                    'external_key' => $externalKey,
                ],
                $values,
            );
        }

        return TaskEvidence::query()->create([
            'task_id' => (int) $task->id,
            'source' => $source->value,
            'external_key' => null,
            ...$values,
        ]);
    }

    public function recordStudyPracticeAssessment(StudyPracticeAttempt $attempt): TaskEvidence
    {
        $attempt->loadMissing('task');
        $task = $attempt->task;

        return $this->record(
            $task,
            EvidenceSource::Native,
            'study_practice_assessed',
            [
                'study_practice_attempt_id' => (int) $attempt->id,
                'study_practice_session_id' => $attempt->study_practice_session_id
                    ? (int) $attempt->study_practice_session_id
                    : null,
                'score_percent' => (int) $attempt->score_percent,
                'recommended_task_progress_percent' => (int) $attempt->recommended_task_progress_percent,
                'evidence_summary' => $attempt->evidence_summary,
                'strengths' => $attempt->strengths ?? [],
                'weaknesses' => $attempt->weaknesses ?? [],
                'next_action' => $attempt->next_action,
                'next_step' => data_get($attempt->assessment, 'next_step'),
            ],
            confidence: 1.0,
            externalKey: 'study-practice-attempt:'.$attempt->id,
            userId: $attempt->user_id ? (int) $attempt->user_id : null,
            actorToken: $attempt->actor_token,
            occurredAt: $attempt->created_at,
        );
    }
}
