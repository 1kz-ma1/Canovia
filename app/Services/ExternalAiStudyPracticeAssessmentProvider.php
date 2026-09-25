<?php

namespace App\Services;

use App\Contracts\StudyPracticeAssessmentProvider;
use App\Models\Plan;
use App\Models\Question;
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

    public function prepare(Plan $plan, Task $task, array $questions, array $answers, ?int $actorUserId = null, ?int $studyPracticeSessionId = null): array
    {
        $sourceIds = collect($questions)
            ->pluck('source_question_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $bankQuestions = $sourceIds->isEmpty()
            ? collect()
            : Question::query()->whereIn('id', $sourceIds)->get()->keyBy('id');

        $assessmentQuestions = collect($questions)
            ->map(function (array $question) use ($bankQuestions) {
                $sourceId = (int) ($question['source_question_id'] ?? 0);
                $bankQuestion = $sourceId > 0 ? $bankQuestions->get($sourceId) : null;

                if (! $bankQuestion) {
                    return $question;
                }

                return [
                    ...$question,
                    'grading_context' => [
                        'grading_rule' => $bankQuestion->grading_rule,
                        'explanation' => $bankQuestion->explanation,
                        'learning_metadata' => $bankQuestion->learning_metadata,
                    ],
                ];
            })
            ->all();

        return [
            'provider' => $this->key(),
            'mode' => $this->mode(),
            'payload' => [
                'evaluation_prompt' => $this->promptService->evaluationPrompt(
                    $plan,
                    $task,
                    $assessmentQuestions,
                    $answers,
                ),
            ],
        ];
    }
}
