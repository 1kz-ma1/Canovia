<?php

namespace App\Contracts;

use App\Models\Plan;
use App\Models\Task;

interface StudyPracticeAssessmentProvider
{
    public function key(): string;

    public function mode(): string;

    /**
     * @param array<int, array<string, mixed>> $questions
     * @param array<int, array<string, mixed>> $answers
     * @return array<string, mixed>
     */
    public function prepare(Plan $plan, Task $task, array $questions, array $answers, ?int $actorUserId = null, ?int $studyPracticeSessionId = null): array;
}
