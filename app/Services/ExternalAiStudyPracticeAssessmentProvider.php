<?php

namespace App\Services;

use App\Contracts\StudyPracticeAssessmentProvider;
use App\Models\Plan;
use App\Models\Task;

class ExternalAiStudyPracticeAssessmentProvider implements StudyPracticeAssessmentProvider
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

    public function prepare(Plan $plan, Task $task, array $questions, array $answers): array
    {
        return [
            'provider' => $this->key(),
            'mode' => $this->mode(),
            'payload' => [
                'evaluation_prompt' => $this->promptService->evaluationPrompt(
                    $plan,
                    $task,
                    $questions,
                    $answers,
                ),
            ],
        ];
    }
}
