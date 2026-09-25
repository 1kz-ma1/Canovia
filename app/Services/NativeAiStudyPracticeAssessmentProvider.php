<?php

namespace App\Services;

use App\Contracts\StudyPracticeAssessmentProvider;
use App\Exceptions\NativeAiExecutionException;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;

class NativeAiStudyPracticeAssessmentProvider implements StudyPracticeAssessmentProvider
{
    public function __construct(
        private readonly StudyPracticePromptService $promptService,
        private readonly NativeAiGateway $gateway,
        private readonly AiCapacityService $capacity,
    ) {}

    public function key(): string
    {
        return 'native_ai';
    }

    public function mode(): string
    {
        return 'direct';
    }

    public function prepare(
        Plan $plan,
        Task $task,
        array $questions,
        array $answers,
        ?int $actorUserId = null,
        ?int $studyPracticeSessionId = null,
    ): array {
        $prompt = $this->promptService->evaluationPrompt($plan, $task, $questions, $answers);
        $user = $actorUserId ? User::query()->find($actorUserId) : null;
        $capacityTier = $this->capacity->tierFor($user);
        $maxOutputTokens = (int) config(
            "native_ai.study_practice.capacity.{$capacityTier}.assessment_max_output_tokens",
            6000,
        );

        $result = $this->gateway->generateStructured(
            purpose: 'study_practice_assessment',
            prompt: $prompt,
            schema: StudyPracticeNativeAiSchema::assessment(),
            schemaName: 'canovia_study_assessment',
            plan: $plan,
            task: $task,
            maxOutputTokens: $maxOutputTokens,
            userId: $actorUserId,
            studyPracticeSessionId: $studyPracticeSessionId,
            capacityTier: $capacityTier,
        );

        $data = $result['data'];
        if (
            ($data['flow'] ?? null) !== 'study_assessment'
            || (int) data_get($data, 'target_plan.id') !== (int) $plan->id
            || (int) data_get($data, 'target_task.id') !== (int) $task->id
        ) {
            $this->gateway->markRunFailed(
                (int) $result['run_id'],
                'native_ai_contract_mismatch',
                'Native AIの評価結果がCanoviaの対象と一致しません。',
            );

            throw new NativeAiExecutionException(
                'Native AIの評価結果をCanoviaへ安全に読み込めませんでした。',
                'native_ai_contract_mismatch',
                (int) $result['run_id'],
            );
        }

        return [
            'provider' => $this->key(),
            'mode' => $this->mode(),
            'payload' => [
                'evaluation_prompt' => $prompt,
                'assessment_envelope' => $data,
                'native_ai' => [
                    'run_id' => (int) $result['run_id'],
                    'provider' => $result['provider'],
                    'model' => $result['model'],
                    'capacity_tier' => $capacityTier,
                ],
            ],
        ];
    }
}
