<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\StudyPracticeSession;
use App\Models\Task;
use Illuminate\Support\Collection;
use RuntimeException;

class StudyPracticeOrchestrator
{
    public function __construct(
        private readonly StudyPracticeStrategyService $strategyService,
        private readonly ExternalAiStudyPracticeQuestionProvider $externalAiProvider,
    ) {}

    /**
     * @param Collection<int, mixed> $recentAttempts
     */
    public function preview(Plan $plan, Task $task, Collection $recentAttempts): array
    {
        return $this->strategyService->build($plan, $task, $recentAttempts);
    }

    /**
     * @param Collection<int, mixed> $recentAttempts
     */
    public function prepare(
        Plan $plan,
        Task $task,
        Collection $recentAttempts,
        ?int $userId,
        ?string $actorToken,
        string $prepareRequestId,
    ): StudyPracticeSession {
        $strategy = $this->strategyService->build($plan, $task, $recentAttempts);

        // V40.1 fallback provider. Future versions insert Question Bank / embedded
        // AI selection ahead of this provider without changing the user entry flow.
        $prepared = $this->externalAiProvider->prepare($plan, $task, $recentAttempts, $strategy);

        $session = StudyPracticeSession::query()->createOrFirst(
            ['prepare_request_id' => $prepareRequestId],
            [
                'plan_id' => $plan->id,
                'task_id' => $task->id,
                'user_id' => $userId,
                'actor_token' => $userId ? null : $actorToken,
                'session_token' => (string) Illuminate\Support\Str::uuid(),
                'status' => StudyPracticeSession::STATUS_AWAITING_PROVIDER,
                'strategy' => (string) $strategy['key'],
                'strategy_version' => (string) ($strategy['version'] ?? 'v1'),
                'selector_type' => (string) ($prepared['selector_type'] ?? 'external_ai'),
                'selector_version' => (string) ($prepared['selector_version'] ?? 'v1'),
                'question_provider' => (string) ($prepared['provider'] ?? 'external_ai'),
                'question_provider_mode' => (string) ($prepared['mode'] ?? 'handoff'),
                'assessment_provider' => 'external_ai',
                'assessment_provider_mode' => 'handoff',
                'selection_context' => [
                    'strategy' => $strategy,
                    'recent_attempt_ids' => $recentAttempts->pluck('id')->map(fn ($id) => (int) $id)->all(),
                ],
                'provider_payload' => is_array($prepared['payload'] ?? null) ? $prepared['payload'] : [],
                'selected_questions' => null,
                'started_at' => now(),
            ]
        );

        if (
            (int) $session->plan_id !== (int) $plan->id
            || (int) $session->task_id !== (int) $task->id
            || ($userId !== null && (int) $session->user_id !== $userId)
            || ($userId === null && (string) $session->actor_token !== (string) $actorToken)
        ) {
            throw new RuntimeException('この演習準備リクエストは別の対象で使用済みです。');
        }

        return $session;
    }
}
