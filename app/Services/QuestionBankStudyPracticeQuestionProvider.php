<?php

namespace App\Services;

use App\Contracts\StudyPracticeQuestionProvider;
use App\Models\Plan;
use App\Models\Question;
use App\Models\StudyPracticeSession;
use App\Models\Task;
use Illuminate\Support\Collection;
use RuntimeException;

class QuestionBankStudyPracticeQuestionProvider implements StudyPracticeQuestionProvider
{
    public function __construct(private readonly QuestionBankCoverageService $coverageService) {}

    public function key(): string
    {
        return 'question_bank';
    }

    public function mode(): string
    {
        return 'direct';
    }

    public function prepare(Plan $plan, Task $task, Collection $recentAttempts, array $strategy): array
    {
        $coverage = $this->coverageService->evaluate($plan, $task, $strategy);
        $pack = $coverage['pack'];

        if (! $coverage['available'] || ! $pack) {
            throw new RuntimeException('Question BankのCoverageが不足しています。');
        }

        $targetCount = (int) $coverage['required_count'];
        $focusTopics = collect($strategy['focus_topics'] ?? [])
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->values();

        $recentQuestionIds = StudyPracticeSession::query()
            ->where('plan_id', $plan->id)
            ->where('task_id', $task->id)
            ->where('question_provider', $this->key())
            ->latest('created_at')
            ->take(5)
            ->get()
            ->flatMap(fn (StudyPracticeSession $session) => collect($session->selected_questions ?? []))
            ->pluck('question_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $questions = $pack->questions
            ->where('is_active', true)
            ->map(fn (Question $question) => [
                'question' => $question,
                'focus_score' => $this->coverageService->questionFocusScore($question, $focusTopics),
                'recent' => in_array((int) $question->id, $recentQuestionIds, true),
            ])
            ->sort(function (array $left, array $right) use ($strategy) {
                $preferHarder = ($strategy['key'] ?? '') === 'retention_and_transfer';

                $leftRank = [
                    -1 * (int) $left['focus_score'],
                    $left['recent'] ? 1 : 0,
                    $preferHarder
                        ? -1 * (int) $left['question']->difficulty
                        : (int) $left['question']->difficulty,
                    (int) $left['question']->sort_order,
                    (int) $left['question']->id,
                ];
                $rightRank = [
                    -1 * (int) $right['focus_score'],
                    $right['recent'] ? 1 : 0,
                    $preferHarder
                        ? -1 * (int) $right['question']->difficulty
                        : (int) $right['question']->difficulty,
                    (int) $right['question']->sort_order,
                    (int) $right['question']->id,
                ];

                return $leftRank <=> $rightRank;
            })
            ->take($targetCount)
            ->pluck('question')
            ->values();

        if ($questions->count() < $targetCount) {
            throw new RuntimeException('Question Bankから必要数の問題を選定できませんでした。');
        }

        $rendered = $questions->map(fn (Question $question) => $this->renderQuestion($question))->all();

        return [
            'provider' => $this->key(),
            'mode' => $this->mode(),
            'selector_type' => 'question_bank',
            'selector_version' => 'bank-v1',
            'payload' => [
                'title' => $pack->title.' / '.($strategy['label'] ?? '演習'),
                'questions' => $rendered,
                'pack' => [
                    'id' => $pack->id,
                    'slug' => $pack->slug,
                    'title' => $pack->title,
                    'version' => $pack->version,
                    'exam_code' => $pack->exam_code,
                    'subject' => $pack->subject,
                ],
                'coverage' => [
                    'active_count' => $coverage['active_count'],
                    'focus_match_count' => $coverage['focus_match_count'],
                    'required_count' => $coverage['required_count'],
                ],
            ],
            'questions' => $rendered,
            'selected_questions' => $questions->map(fn (Question $question) => [
                'question_ref' => 'bank_'.$question->id,
                'question_id' => $question->id,
                'source_type' => $question->source_type,
                'source_reference' => $question->source_reference,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function renderQuestion(Question $question): array
    {
        $fields = collect($question->response_schema ?? [])
            ->filter(fn ($field) => is_array($field))
            ->values()
            ->all();

        $first = $fields[0] ?? ['type' => 'textarea', 'choices' => []];
        $legacyType = match ((string) ($first['type'] ?? 'textarea')) {
            'short_text', 'textarea' => 'text',
            default => (string) ($first['type'] ?? 'text'),
        };

        return [
            'id' => 'bank_'.$question->id,
            'source_question_id' => $question->id,
            'source_type' => $question->source_type,
            'source_reference' => $question->source_reference,
            'prompt' => $question->prompt,
            'response_fields' => $fields,
            'type' => $legacyType,
            'choices' => $first['choices'] ?? [],
        ];
    }
}
