<?php

namespace App\Services;

use App\Contracts\StudyPracticeQuestionProvider;
use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Collection;

class HybridStudyPracticeQuestionProvider implements StudyPracticeQuestionProvider
{
    public function __construct(
        private readonly QuestionBankStudyPracticeQuestionProvider $questionBank,
        private readonly NativeAiStudyPracticeQuestionProvider $nativeAi,
    ) {}

    public function key(): string
    {
        return 'hybrid_ai';
    }

    public function mode(): string
    {
        return 'direct';
    }

    public function prepare(
        Plan $plan,
        Task $task,
        Collection $recentAttempts,
        array $strategy,
        ?int $actorUserId = null,
    ): array {
        $targetCount = max(1, min(20, (int) ($strategy['target_question_count'] ?? 10)));

        $bank = $this->questionBank->preparePartial(
            $plan,
            $task,
            $recentAttempts,
            $strategy,
            $actorUserId,
        );

        $bankQuestions = collect($bank['questions'] ?? [])->filter(fn ($question) => is_array($question))->values();
        $bankSelected = collect($bank['selected_questions'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $bankCount = min($targetCount, $bankQuestions->count());
        $missingCount = max(0, $targetCount - $bankCount);

        $requestedMix = is_array($strategy['question_mix'] ?? null)
            ? $strategy['question_mix']
            : ['primary' => 0, 'secondary' => 0, 'diagnostic' => $targetCount];
        $actualBankMix = is_array(data_get($bank, 'payload.selection_mix.actual'))
            ? data_get($bank, 'payload.selection_mix.actual')
            : [];

        $remainingMix = [
            'primary' => max(0, (int) ($requestedMix['primary'] ?? 0) - (int) ($actualBankMix['primary'] ?? 0)),
            'secondary' => max(0, (int) ($requestedMix['secondary'] ?? 0) - (int) ($actualBankMix['secondary'] ?? 0)),
            'diagnostic' => max(0, (int) ($requestedMix['diagnostic'] ?? 0) - (int) ($actualBankMix['diagnostic'] ?? 0)),
        ];

        $remainingAssigned = array_sum($remainingMix);
        if ($remainingAssigned < $missingCount) {
            $remainingMix['diagnostic'] += $missingCount - $remainingAssigned;
        } elseif ($remainingAssigned > $missingCount) {
            $remainingMix = $this->trimMixToCount($remainingMix, $missingCount);
        }

        $native = null;
        $nativeQuestions = collect();
        $nativeSelected = collect();

        if ($missingCount > 0) {
            $nativeStrategy = $strategy;
            $nativeStrategy['target_question_count'] = $missingCount;
            $nativeStrategy['question_mix'] = $remainingMix;
            $nativeStrategy['existing_question_summaries'] = $bankQuestions
                ->pluck('prompt')
                ->filter(fn ($prompt) => is_string($prompt) && trim($prompt) !== '')
                ->map(fn ($prompt) => mb_substr(trim($prompt), 0, 500))
                ->take(20)
                ->values()
                ->all();
            $nativeStrategy['hybrid_context'] = [
                'requested_count' => $targetCount,
                'bank_selected_count' => $bankCount,
                'native_requested_count' => $missingCount,
            ];

            $native = $this->nativeAi->prepare(
                $plan,
                $task,
                $recentAttempts,
                $nativeStrategy,
                $actorUserId,
            );

            $nativeQuestions = collect($native['questions'] ?? [])
                ->filter(fn ($question) => is_array($question))
                ->take($missingCount)
                ->values();

            $nativeSelected = collect($native['selected_questions'] ?? [])
                ->filter(fn ($item) => is_array($item))
                ->take($nativeQuestions->count())
                ->map(function (array $item) {
                    $item['selection_bucket'] = $item['selection_bucket'] ?? 'native_gap';

                    return $item;
                })
                ->values();
        }

        $questions = $bankQuestions
            ->take($bankCount)
            ->concat($nativeQuestions)
            ->take($targetCount)
            ->values();

        $selectedQuestions = $bankSelected
            ->take($bankCount)
            ->concat($nativeSelected)
            ->take($questions->count())
            ->values();

        $pack = data_get($bank, 'payload.pack');
        $coverage = data_get($bank, 'payload.coverage', []);

        $payload = [
            'title' => 'Canovia Hybrid / '.($strategy['label'] ?? '演習'),
            'questions' => $questions->all(),
            'source_mix' => [
                'requested_count' => $targetCount,
                'bank_selected_count' => $bankCount,
                'native_requested_count' => $missingCount,
                'native_generated_count' => $nativeQuestions->count(),
            ],
            'bank' => [
                'pack' => is_array($pack) ? $pack : null,
                'coverage' => is_array($coverage) ? $coverage : [],
                'selection_mix' => data_get($bank, 'payload.selection_mix', []),
            ],
            'generation_prompt' => (string) data_get($native, 'payload.generation_prompt', ''),
            'response_envelope' => data_get($native, 'payload.response_envelope'),
            'native_ai' => data_get($native, 'payload.native_ai'),
        ];

        return [
            'provider' => $this->key(),
            'mode' => $this->mode(),
            'selector_type' => 'hybrid_question_assembly',
            'selector_version' => 'hybrid-v1',
            'payload' => $payload,
            'questions' => $questions->all(),
            'selected_questions' => $selectedQuestions->all(),
        ];
    }

    /**
     * @param array{primary:int,secondary:int,diagnostic:int} $mix
     * @return array{primary:int,secondary:int,diagnostic:int}
     */
    private function trimMixToCount(array $mix, int $target): array
    {
        $result = ['primary' => 0, 'secondary' => 0, 'diagnostic' => 0];
        $remaining = max(0, $target);

        foreach (['primary', 'secondary', 'diagnostic'] as $key) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, max(0, (int) ($mix[$key] ?? 0)));
            $result[$key] = $take;
            $remaining -= $take;
        }

        return $result;
    }
}
