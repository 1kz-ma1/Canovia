<?php

namespace App\Services;

use App\Contracts\StudyPracticeAssessmentProvider;
use App\Contracts\StudyPracticeQuestionProvider;
use App\Models\Plan;
use App\Models\Task;

class StudyPracticeProviderRouter
{
    public function __construct(
        private readonly ExternalAiStudyPracticeQuestionProvider $externalQuestionProvider,
        private readonly ExternalAiStudyPracticeAssessmentProvider $externalAssessmentProvider,
    ) {}

    /**
     * V40.1 intentionally falls back to external AI for every Task.
     * Future versions can inspect Question Pack coverage and embedded-AI
     * availability here without changing the AI Practice entry flow.
     */
    public function questionProvider(Plan $plan, Task $task, array $strategy): StudyPracticeQuestionProvider
    {
        return $this->externalQuestionProvider;
    }

    public function assessmentProvider(Plan $plan, Task $task): StudyPracticeAssessmentProvider
    {
        return $this->externalAssessmentProvider;
    }
}
