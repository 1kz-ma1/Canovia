<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskEvidence;
use App\Models\TaskProgressDecision;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class TaskEvidenceService
{
    /**
     * Record an observed fact about a Task.
     *
     * Time is optional context only. Progress should be derived from the
     * observed outcome/evidence, not from elapsed time alone.
     *
     * @param array<string, mixed> $metadata
     */
    public function record(
        Task $task,
        string $source,
        string $type,
        ?string $summary = null,
        int $confidence = 100,
        array $metadata = [],
        ?string $provider = null,
        ?string $providerReference = null,
        ?int $userId = null,
        ?string $actorToken = null,
        ?int $observedDurationSeconds = null,
        ?int $milestoneId = null,
        CarbonInterface|string|null $occurredAt = null,
        ?string $dedupeKey = null,
    ): TaskEvidence {
        $occurredAt ??= now();
        $occurredAtString = $occurredAt instanceof CarbonInterface
            ? $occurredAt->toISOString()
            : (string) $occurredAt;

        $fingerprint = hash('sha256', implode('|', [
            'task-evidence-v1',
            (int) $task->plan_id,
            (int) $task->id,
            trim($source),
            trim($type),
            trim((string) $provider),
            trim((string) $providerReference),
            $dedupeKey ?: (string) Str::uuid(),
        ]));

        return TaskEvidence::query()->createOrFirst(
            ['fingerprint' => $fingerprint],
            [
                'plan_id' => $task->plan_id,
                'task_id' => $task->id,
                'user_id' => $userId,
                'actor_token' => $userId ? null : $actorToken,
                'milestone_id' => $milestoneId,
                'source' => mb_substr(trim($source), 0, 32),
                'type' => mb_substr(trim($type), 0, 64),
                'provider' => filled($provider) ? mb_substr(trim((string) $provider), 0, 64) : null,
                'provider_reference' => filled($providerReference)
                    ? mb_substr(trim((string) $providerReference), 0, 191)
                    : null,
                'summary' => filled($summary) ? mb_substr(trim((string) $summary), 0, 4000) : null,
                'confidence' => max(0, min(100, $confidence)),
                'observed_duration_seconds' => $observedDurationSeconds !== null
                    ? max(0, $observedDurationSeconds)
                    : null,
                'metadata' => $metadata !== [] ? $metadata : null,
                'occurred_at' => $occurredAtString,
            ],
        );
    }

    /**
     * Persist the reason a progress value changed (or was proposed).
     *
     * @param array<string, mixed> $metadata
     */
    public function recordProgressDecision(
        Task $task,
        int $progressBefore,
        int $progressAfter,
        string $reason,
        ?TaskEvidence $evidence = null,
        string $source = 'rule',
        string $status = 'applied',
        ?int $userId = null,
        ?string $actorToken = null,
        array $metadata = [],
        ?string $dedupeKey = null,
    ): TaskProgressDecision {
        $before = max(0, min(100, $progressBefore));
        $after = max(0, min(100, $progressAfter));
        $decisionKey = hash('sha256', implode('|', [
            'task-progress-decision-v1',
            (int) $task->id,
            (int) ($evidence?->id ?? 0),
            $source,
            $status,
            $before,
            $after,
            $dedupeKey ?: (string) Str::uuid(),
        ]));

        return TaskProgressDecision::query()->createOrFirst(
            ['decision_key' => $decisionKey],
            [
                'task_id' => $task->id,
                'task_evidence_id' => $evidence?->id,
                'user_id' => $userId,
                'actor_token' => $userId ? null : $actorToken,
                'source' => mb_substr(trim($source), 0, 32),
                'status' => mb_substr(trim($status), 0, 24),
                'progress_before_percent' => $before,
                'progress_after_percent' => $after,
                'reason' => mb_substr(trim($reason), 0, 4000),
                'metadata' => $metadata !== [] ? $metadata : null,
                'applied_at' => $status === 'applied' ? now() : null,
            ],
        );
    }
}
