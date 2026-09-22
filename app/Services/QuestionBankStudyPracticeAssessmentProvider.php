<?php

namespace App\Services;

use App\Contracts\StudyPracticeAssessmentProvider;
use App\Models\Plan;
use App\Models\Task;

class QuestionBankStudyPracticeAssessmentProvider implements StudyPracticeAssessmentProvider
{
    public function __construct(private readonly QuestionBankGrader $grader) {}

    public function key(): string
    {
        return 'question_bank_grader';
    }

    public function mode(): string
    {
        return 'direct';
    }

    public function prepare(Plan $plan, Task $task, array $questions, array $answers): array
    {
        return [
            'provider' => $this->key(),
            'mode' => $this->mode(),
            'payload' => [
                'assessment' => $this->grader->grade($task, $questions, $answers),
            ],
        ];
    }
}
