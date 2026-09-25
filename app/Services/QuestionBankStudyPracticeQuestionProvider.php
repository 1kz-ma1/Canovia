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
        $weakness = is_array($strategy['weakness_priority'] ?? null)
            ? $strategy['weakness_priority']
            : [];

        $primaryTopics = collect($weakness['primary_topics'] ?? [])
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->values();
        $secondaryTopics = collect($weakness['secondary_topics'] ?? [])
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->values();

        // Backward compatibility for old strategies that only expose focus_topics.
        if ($primaryTopics->isEmpty() && $secondaryTopics->isEmpty()) {
            $secondaryTopics = collect($strategy['focus_topics'] ?? [])
                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                ->values();
        }

        $mix = is_array($strategy['question_mix'] ?? null)
            ? $strategy['question_mix']
            : [];
        $primaryTarget = max(0, min($targetCount, (int) ($mix['primary'] ?? 0)));
        $secondaryTarget = max(0, min($targetCount, (int) ($mix['secondary'] ?? 0)));
        $diagnosticTarget = max(0, min($targetCount, (int) ($mix['diagnostic'] ?? $targetCount)));

        $recentQuestionIds = StudyPracticeSession::query()
            ->where('plan_id', $plan->id)
            ->where('task_id', $task->id)
            ->where('question_provider', $this->key())
            ->latest('created_at')
            ->take(8)
            ->get()
            ->flatMap(fn (StudyPracticeSession $session) => collect($session->selected_questions ?? []))
            ->pluck('question_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $preferHarder = ($strategy['key'] ?? '') === 'retention_and_transfer';

        $candidates = $pack->questions
            ->where('is_active', true)
            ->map(function (Question $question) use (
                $primaryTopics,
                $secondaryTopics,
                $recentQuestionIds,
            ) {
                return [
                    'question' => $question,
                    'primary_score' => $this->coverageService->questionFocusScore($question, $primaryTopics),
                    'secondary_score' => $this->coverageService->questionFocusScore($question, $secondaryTopics),
                    'primary_topic' => $this->bestMatchingTopic($question, $primaryTopics),
                    'secondary_topic' => $this->bestMatchingTopic($question, $secondaryTopics),
                    'domain_key' => $this->domainKey($question),
                    'recent' => in_array((int) $question->id, $recentQuestionIds, true),
                ];
            })
            ->values();

        $selected = collect();
        $selectedIds = collect();

        $primary = $this->selectBucket(
            $candidates->filter(fn (array $item) => $item['primary_score'] > 0),
            $primaryTarget,
            'primary_topic',
            'primary_score',
            $preferHarder,
            $selectedIds,
        );
        $this->appendSelection($selected, $selectedIds, $primary, 'primary');

        $secondary = $this->selectBucket(
            $candidates->filter(fn (array $item) => $item['secondary_score'] > 0),
            $secondaryTarget,
            'secondary_topic',
            'secondary_score',
            $preferHarder,
            $selectedIds,
        );
        $this->appendSelection($selected, $selectedIds, $secondary, 'secondary');

        // Diagnostic questions deliberately prefer topics outside the active
        // weakness set so another weakness can still be discovered.
        $diagnosticPool = $candidates->filter(
            fn (array $item) => $item['primary_score'] === 0 && $item['secondary_score'] === 0
        );
        $diagnostic = $this->selectBucket(
            $diagnosticPool,
            $diagnosticTarget,
            'domain_key',
            null,
            $preferHarder,
            $selectedIds,
        );
        $this->appendSelection($selected, $selectedIds, $diagnostic, 'diagnostic');

        // Coverage can be sparse for a particular weakness. Fill any remaining
        // seats from the whole exam pack rather than failing the session.
        $remaining = max(0, $targetCount - $selected->count());
        if ($remaining > 0) {
            $fallback = $this->selectBucket(
                $candidates,
                $remaining,
                'domain_key',
                null,
                $preferHarder,
                $selectedIds,
            );
            $this->appendSelection($selected, $selectedIds, $fallback, 'balanced_fill');
        }

        if ($selected->count() < $targetCount) {
            throw new RuntimeException('Question Bankから必要数の問題を選定できませんでした。');
        }

        $selected = $selected->take($targetCount)->values();
        $questions = $selected->pluck('question')->values();
        $rendered = $questions->map(fn (Question $question) => $this->renderQuestion($question))->all();

        $actualMix = $selected
            ->countBy('bucket')
            ->map(fn ($count) => (int) $count)
            ->all();

        return [
            'provider' => $this->key(),
            'mode' => $this->mode(),
            'selector_type' => 'question_bank',
            'selector_version' => 'bank-v2-balanced',
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
                'selection_mix' => [
                    'requested' => [
                        'primary' => $primaryTarget,
                        'secondary' => $secondaryTarget,
                        'diagnostic' => $diagnosticTarget,
                    ],
                    'actual' => $actualMix,
                ],
            ],
            'questions' => $rendered,
            'selected_questions' => $selected->map(fn (array $item) => [
                'question_ref' => 'bank_'.$item['question']->id,
                'question_id' => $item['question']->id,
                'source_type' => $item['question']->source_type,
                'source_reference' => $item['question']->source_reference,
                'selection_bucket' => $item['bucket'],
                'selection_domain' => $item['domain_key'],
            ])->all(),
        ];
    }

    /**
     * @param Collection<int,array<string,mixed>> $candidates
     * @param Collection<int,int> $selectedIds
     * @return Collection<int,array<string,mixed>>
     */
    private function selectBucket(
        Collection $candidates,
        int $count,
        string $groupKey,
        ?string $scoreKey,
        bool $preferHarder,
        Collection $selectedIds,
    ): Collection {
        if ($count <= 0) {
            return collect();
        }

        $available = $candidates
            ->reject(fn (array $item) => $selectedIds->contains((int) $item['question']->id))
            ->groupBy(fn (array $item) => (string) ($item[$groupKey] ?: 'other'))
            ->map(function (Collection $group) use ($scoreKey, $preferHarder) {
                return $group
                    ->sort(function (array $left, array $right) use ($scoreKey, $preferHarder) {
                        $leftScore = $scoreKey ? (int) ($left[$scoreKey] ?? 0) : 0;
                        $rightScore = $scoreKey ? (int) ($right[$scoreKey] ?? 0) : 0;

                        $leftRank = [
                            $left['recent'] ? 1 : 0,
                            -1 * $leftScore,
                            $preferHarder
                                ? -1 * (int) $left['question']->difficulty
                                : abs(3 - (int) $left['question']->difficulty),
                            (int) $left['question']->sort_order,
                            (int) $left['question']->id,
                        ];
                        $rightRank = [
                            $right['recent'] ? 1 : 0,
                            -1 * $rightScore,
                            $preferHarder
                                ? -1 * (int) $right['question']->difficulty
                                : abs(3 - (int) $right['question']->difficulty),
                            (int) $right['question']->sort_order,
                            (int) $right['question']->id,
                        ];

                        return $leftRank <=> $rightRank;
                    })
                    ->values();
            })
            ->sortByDesc(fn (Collection $group) => $group->count());

        $picked = collect();

        while ($picked->count() < $count && $available->isNotEmpty()) {
            $madeProgress = false;

            foreach ($available as $key => $group) {
                if ($picked->count() >= $count) {
                    break;
                }

                $next = $group->shift();
                if (! $next) {
                    $available->forget($key);
                    continue;
                }

                $picked->push($next);
                $available->put($key, $group);
                $madeProgress = true;
            }

            if (! $madeProgress) {
                break;
            }
        }

        return $picked->values();
    }

    /**
     * @param Collection<int,array<string,mixed>> $selected
     * @param Collection<int,int> $selectedIds
     * @param Collection<int,array<string,mixed>> $items
     */
    private function appendSelection(
        Collection $selected,
        Collection $selectedIds,
        Collection $items,
        string $bucket,
    ): void {
        foreach ($items as $item) {
            $id = (int) $item['question']->id;
            if ($selectedIds->contains($id)) {
                continue;
            }

            $item['bucket'] = $bucket;
            $selected->push($item);
            $selectedIds->push($id);
        }
    }

    /**
     * @param Collection<int,string> $topics
     */
    private function bestMatchingTopic(Question $question, Collection $topics): string
    {
        $bestTopic = '';
        $bestScore = 0;

        foreach ($topics as $topic) {
            $score = $this->coverageService->questionFocusScore($question, collect([$topic]));
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestTopic = $topic;
            }
        }

        return $bestTopic;
    }

    private function domainKey(Question $question): string
    {
        $metadata = collect($question->learning_metadata ?? []);

        $ignored = ['科目a', '計算', '科目a計算'];
        $candidate = collect($metadata->get('tags', []))
            ->merge($metadata->get('concepts', []))
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn ($item) => trim((string) $item))
            ->first(function (string $item) use ($ignored) {
                return ! in_array(mb_strtolower($item), $ignored, true);
            });

        return $candidate ?: 'other';
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
