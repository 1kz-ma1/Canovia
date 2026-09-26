<?php

namespace App\Services;

use App\Contracts\StudyPracticeAssessmentProvider;
use App\Contracts\StudyPracticeQuestionProvider;
use App\Models\Plan;
use App\Models\Task;

class StudyPracticeProviderRouter
{
    public function __construct(
        private readonly QuestionBankCoverageService $coverageService,
        private readonly QuestionBankStudyPracticeQuestionProvider $questionBankProvider,
        private readonly QuestionBankGrader $questionBankGrader,
        private readonly QuestionBankStudyPracticeAssessmentProvider $questionBankAssessmentProvider,
        private readonly ExternalAiStudyPracticeQuestionProvider $externalQuestionProvider,
        private readonly ExternalAiStudyPracticeAssessmentProvider $externalAssessmentProvider,
        private readonly NativeAiStudyPracticeQuestionProvider $nativeQuestionProvider,
        private readonly HybridStudyPracticeQuestionProvider $hybridQuestionProvider,
        private readonly NativeAiStudyPracticeAssessmentProvider $nativeAssessmentProvider,
    ) {}

    public function questionProvider(Plan $plan, Task $task, array $strategy): StudyPracticeQuestionProvider
    {
        $coverage = $this->coverageService->evaluate($plan, $task, $strategy);

        return $coverage['available']
            ? $this->questionBankProvider
            : $this->externalQuestionProvider;
    }

    public function questionProviderByKey(string $key): StudyPracticeQuestionProvider
    {
        return match ($key) {
            'question_bank' => $this->questionBankProvider,
            'native_ai' => $this->nativeQuestionProvider,
            'hybrid_ai' => $this->hybridQuestionProvider,
            'external_ai' => $this->externalQuestionProvider,
            default => $this->externalQuestionProvider,
        };
    }

    /**
     * @param array<int, array<string, mixed>> $questions
     * @param array<int, array<string, mixed>> $answers
     */
    public function assessmentProvider(
        Plan $plan,
        Task $task,
        array $questions = [],
        array $answers = [],
    ): StudyPracticeAssessmentProvider {
        if ($this->questionBankGrader->canGrade($questions, $answers)) {
            return $this->questionBankAssessmentProvider;
        }

        return $this->externalAssessmentProvider;
    }

    public function assessmentProviderByKey(string $key): StudyPracticeAssessmentProvider
    {
        return match ($key) {
            'question_bank_grader' => $this->questionBankAssessmentProvider,
            'native_ai' => $this->nativeAssessmentProvider,
            'external_ai' => $this->externalAssessmentProvider,
            default => $this->externalAssessmentProvider,
        };
    }
}
