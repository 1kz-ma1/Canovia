<?php

namespace App\Services;

use App\Models\PracticeQuestionCandidate;
use App\Models\PracticeQuestionDemand;
use App\Models\StudyPracticeSession;
use Illuminate\Support\Collection;

class PracticeQuestionCandidateRecorder
{
    /**
     * Native AIで実際に生成された問題だけを、公開前のCandidateとして記録する。
     * Question Bank問題やExternal AI handoffはここでは資産化しない。
     *
     * @param array<string,mixed> $strategy
     * @param array<string,mixed> $prepared
     * @return Collection<int,PracticeQuestionCandidate>
     */
    public function record(
        StudyPracticeSession $session,
        PracticeQuestionDemand $demand,
        array $strategy,
        array $prepared,
    ): Collection {
        $provider = (string) ($prepared['provider'] ?? $session->question_provider);
        if (! in_array($provider, ['native_ai', 'hybrid_ai'], true)) {
            return collect();
        }

        $nativeRefs = collect($prepared['selected_questions'] ?? [])
            ->filter(fn ($item) => is_array($item) && ($item['source_type'] ?? null) === 'native_ai')
            ->pluck('question_ref')
            ->filter(fn ($id) => is_string($id) && trim($id) !== '')
            ->map(fn ($id) => trim($id))
            ->unique()
            ->values();

        if ($nativeRefs->isEmpty()) {
            return collect();
        }

        $questions = collect($prepared['questions'] ?? [])
            ->filter(fn ($question) => is_array($question))
            ->keyBy(fn (array $question) => trim((string) ($question['id'] ?? '')));

        $examProfile = is_array($strategy['exam_profile'] ?? null)
            ? $strategy['exam_profile']
            : [];
        $examProfileKey = filled($examProfile['key'] ?? null)
            ? mb_substr((string) $examProfile['key'], 0, 80)
            : null;
        $runId = data_get($prepared, 'payload.native_ai.run_id');
        $model = trim((string) data_get($prepared, 'payload.native_ai.model', ''));

        return $nativeRefs
            ->map(function (string $questionRef) use (
                $questions,
                $demand,
                $strategy,
                $examProfile,
                $examProfileKey,
                $runId,
                $model,
            ) {
                $question = $questions->get($questionRef);
                if (! is_array($question)) {
                    return null;
                }

                $payload = $this->publicQuestionPayload($question);
                if (trim((string) ($payload['prompt'] ?? '')) === '') {
                    return null;
                }

                $fingerprint = $this->fingerprint($payload, $examProfileKey);

                $reviewHints = [
                    'strategy_key' => filled($strategy['key'] ?? null)
                        ? (string) $strategy['key']
                        : null,
                    'exam_profile_label' => filled($examProfile['label'] ?? null)
                        ? mb_substr((string) $examProfile['label'], 0, 160)
                        : null,
                    'focus_topics' => collect($strategy['focus_topics'] ?? [])
                        ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                        ->map(fn ($item) => mb_substr(trim($item), 0, 120))
                        ->unique()
                        ->take(20)
                        ->values()
                        ->all(),
                    'question_mix' => is_array($strategy['question_mix'] ?? null)
                        ? $strategy['question_mix']
                        : [],
                ];

                $candidate = PracticeQuestionCandidate::query()->firstOrCreate(
                    ['fingerprint' => $fingerprint],
                    [
                        'status' => PracticeQuestionCandidate::STATUS_PENDING,
                        'provider' => 'native_ai',
                        'model' => $model !== '' ? mb_substr($model, 0, 120) : null,
                        'exam_profile_key' => $examProfileKey,
                        'first_practice_question_demand_id' => $demand->id,
                        'latest_practice_question_demand_id' => $demand->id,
                        'native_ai_run_id' => is_numeric($runId) ? (int) $runId : null,
                        'question_payload' => $payload,
                        'review_hints' => $reviewHints,
                        'generation_count' => 1,
                        'last_seen_at' => now(),
                    ],
                );

                if ($candidate->wasRecentlyCreated) {
                    return $candidate;
                }

                $alreadyCounted = (int) $candidate->latest_practice_question_demand_id === (int) $demand->id;
                $updates = [
                    'latest_practice_question_demand_id' => $demand->id,
                    'native_ai_run_id' => is_numeric($runId) ? (int) $runId : $candidate->native_ai_run_id,
                    'model' => $model !== '' ? mb_substr($model, 0, 120) : $candidate->model,
                    'last_seen_at' => now(),
                ];

                if (! $alreadyCounted) {
                    $updates['generation_count'] = max(1, (int) $candidate->generation_count) + 1;
                }

                if ($candidate->status === PracticeQuestionCandidate::STATUS_PENDING) {
                    $updates['question_payload'] = $payload;
                    $updates['review_hints'] = $reviewHints;
                }

                $candidate->update($updates);

                return $candidate->fresh();
            })
            ->filter()
            ->values();
    }

    /**
     * @param array<string,mixed> $question
     * @return array<string,mixed>
     */
    private function publicQuestionPayload(array $question): array
    {
        return [
            'id' => mb_substr(trim((string) ($question['id'] ?? '')), 0, 120),
            'prompt' => mb_substr(trim((string) ($question['prompt'] ?? '')), 0, 12000),
            'work_input' => in_array(($question['work_input'] ?? null), ['none', 'reasoning', 'calculation'], true)
                ? (string) $question['work_input']
                : 'none',
            'response_fields' => collect($question['response_fields'] ?? [])
                ->filter(fn ($field) => is_array($field))
                ->take(4)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function fingerprint(array $payload, ?string $examProfileKey): string
    {
        $prompt = mb_strtolower(trim((string) ($payload['prompt'] ?? '')));
        $prompt = preg_replace('/\s+/u', ' ', $prompt) ?? $prompt;

        return hash('sha256', json_encode([
            'exam_profile_key' => $examProfileKey,
            'prompt' => $prompt,
            'response_fields' => $payload['response_fields'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
