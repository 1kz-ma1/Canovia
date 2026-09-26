<?php

namespace App\Services;

use App\Models\PracticeQuestionCandidate;
use App\Models\Question;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PracticeQuestionCandidatePromotionService
{
    public function __construct(private readonly QuestionPackImportService $questionImporter) {}

    /**
     * @param array<string,mixed> $review
     */
    public function promote(
        PracticeQuestionCandidate $candidate,
        QuestionPack $pack,
        array $review,
        ?User $reviewer,
    ): Question {
        if ($candidate->status !== PracticeQuestionCandidate::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'candidate' => '確認待ちのCandidateだけをQuestion Bankへ昇格できます。',
            ]);
        }

        if ($pack->status !== 'draft') {
            throw ValidationException::withMessages([
                'question_pack_id' => 'Candidateの昇格先はDraftのQuestion Packにしてください。',
            ]);
        }

        $payload = is_array($candidate->question_payload) ? $candidate->question_payload : [];
        $prompt = trim((string) ($payload['prompt'] ?? ''));
        $responseFields = $payload['response_fields'] ?? null;

        if ($prompt === '' || ! is_array($responseFields) || $responseFields === []) {
            throw ValidationException::withMessages([
                'candidate' => 'Candidateの問題データが不足しているため昇格できません。',
            ]);
        }

        $externalKey = trim((string) ($review['external_key'] ?? ''));
        if ($externalKey === '') {
            $externalKey = 'ai-candidate-'.$candidate->id;
        }

        if (
            Question::query()
                ->where('question_pack_id', $pack->id)
                ->where('external_key', $externalKey)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'external_key' => '昇格先Pack内でexternal_keyが重複しています。',
            ]);
        }

        $nextSortOrder = ((int) $pack->questions()->max('sort_order')) + 1;
        $normalized = $this->questionImporter->normalizeQuestionDefinition([
            'external_key' => $externalKey,
            'source_type' => 'ai_generated',
            'source_reference' => filled($review['source_reference'] ?? null)
                ? (string) $review['source_reference']
                : 'Canovia Native AI candidate #'.$candidate->id,
            'prompt' => $prompt,
            'response_schema' => $responseFields,
            'grading_rule' => $review['grading_rule'] ?? null,
            'learning_metadata' => is_array($review['learning_metadata'] ?? null)
                ? $review['learning_metadata']
                : [],
            'explanation' => $review['explanation'] ?? null,
            'difficulty' => $review['difficulty'] ?? 3,
            'sort_order' => $nextSortOrder,
            'is_active' => true,
        ], $nextSortOrder);

        return DB::transaction(function () use ($candidate, $pack, $normalized, $review, $reviewer) {
            $question = $pack->questions()->create($normalized);

            $candidate->update([
                'status' => PracticeQuestionCandidate::STATUS_PROMOTED,
                'review_data' => [
                    'review_note' => mb_substr(trim((string) ($review['review_note'] ?? '')), 0, 2000),
                    'external_key' => $question->external_key,
                    'grading_rule' => $question->grading_rule,
                    'learning_metadata' => $question->learning_metadata,
                    'explanation' => $question->explanation,
                    'difficulty' => $question->difficulty,
                ],
                'promoted_question_pack_id' => $pack->id,
                'promoted_question_id' => $question->id,
                'reviewed_by_user_id' => $reviewer?->id,
                'reviewed_at' => now(),
            ]);

            return $question;
        });
    }
}
