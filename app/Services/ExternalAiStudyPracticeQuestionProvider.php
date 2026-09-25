<?php

namespace App\Services;

use App\Contracts\StudyPracticeQuestionProvider;
use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Collection;

class ExternalAiStudyPracticeQuestionProvider implements StudyPracticeQuestionProvider
{
    public function __construct(private readonly StudyPracticePromptService $promptService) {}

    public function key(): string
    {
        return 'external_ai';
    }

    public function mode(): string
    {
        return 'handoff';
    }

    public function prepare(Plan $plan, Task $task, Collection $recentAttempts, array $strategy, ?int $actorUserId = null): array
    {
        return [
            'provider' => $this->key(),
            'mode' => $this->mode(),
            'selector_type' => 'external_ai',
            'selector_version' => 'prompt-v41.4-calibrated',
            'payload' => [
                'generation_prompt' => $this->promptService->generationPrompt(
                    $plan,
                    $task,
                    $recentAttempts,
                    $strategy,
                ),
            ],
            'questions' => null,
        ];
    }
}
