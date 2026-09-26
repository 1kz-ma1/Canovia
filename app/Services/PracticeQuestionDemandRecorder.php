<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PracticeQuestionDemand;
use App\Models\StudyPracticeSession;
use App\Models\Task;

class PracticeQuestionDemandRecorder
{
    /**
     * Persist the supply/demand snapshot for one prepared practice session.
     *
     * The schema is qualification-agnostic. AP is only one possible
     * exam_profile/focus_topics combination.
     *
     * @param array<string,mixed> $strategy
     * @param array<string,mixed> $prepared
     */
    public function record(
        StudyPracticeSession $session,
        Plan $plan,
        Task $task,
        array $strategy,
        array $prepared,
    ): PracticeQuestionDemand {
        $requested = max(0, min(20, (int) ($strategy['target_question_count'] ?? 0)));
        $provider = (string) ($prepared['provider'] ?? $session->question_provider);
        $questionCount = collect($prepared['questions'] ?? [])->filter(fn ($item) => is_array($item))->count();

        $bankSelected = 0;
        $generatedRequested = 0;
        $generatedCount = 0;
        $generationProvider = null;
        $assemblyMode = 'unknown';

        if ($provider === 'hybrid_ai') {
            $mix = is_array(data_get($prepared, 'payload.source_mix'))
                ? data_get($prepared, 'payload.source_mix')
                : [];
            $bankSelected = max(0, (int) ($mix['bank_selected_count'] ?? 0));
            $generatedRequested = max(0, (int) ($mix['native_requested_count'] ?? 0));
            $generatedCount = max(0, (int) ($mix['native_generated_count'] ?? 0));
            $generationProvider = $generatedRequested > 0 ? 'native_ai' : null;
            $assemblyMode = match (true) {
                $bankSelected > 0 && $generatedCount > 0 => 'hybrid',
                $bankSelected > 0 => 'bank_only',
                default => 'generated_only',
            };
        } elseif ($provider === 'question_bank') {
            $bankSelected = $questionCount;
            $assemblyMode = 'bank_only';
        } elseif ($provider === 'native_ai') {
            $generatedRequested = $requested;
            $generatedCount = $questionCount;
            $generationProvider = 'native_ai';
            $assemblyMode = 'generated_only';
        } elseif ($provider === 'external_ai') {
            $generatedRequested = $requested;
            $generatedCount = $questionCount;
            $generationProvider = 'external_ai';
            $assemblyMode = 'external_handoff';
        }

        $packId = data_get($prepared, 'payload.bank.pack.id')
            ?? data_get($prepared, 'payload.pack.id');
        $coverage = data_get($prepared, 'payload.bank.coverage')
            ?? data_get($prepared, 'payload.coverage')
            ?? [];

        $examProfile = is_array($strategy['exam_profile'] ?? null)
            ? $strategy['exam_profile']
            : [];

        return PracticeQuestionDemand::query()->updateOrCreate(
            ['prepare_request_id' => (string) $session->prepare_request_id],
            [
                'user_id' => $session->user_id,
                'plan_id' => $plan->id,
                'task_id' => $task->id,
                'study_practice_session_id' => $session->id,
                'question_pack_id' => is_numeric($packId) ? (int) $packId : null,
                'strategy_key' => filled($strategy['key'] ?? null) ? (string) $strategy['key'] : null,
                'exam_profile_key' => filled($examProfile['key'] ?? null) ? (string) $examProfile['key'] : null,
                'assembly_mode' => $assemblyMode,
                'generation_provider' => $generationProvider,
                'requested_count' => $requested,
                'bank_selected_count' => min(255, $bankSelected),
                'generated_requested_count' => min(255, $generatedRequested),
                'generated_count' => min(255, $generatedCount),
                'focus_topics' => collect($strategy['focus_topics'] ?? [])
                    ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                    ->map(fn ($item) => trim($item))
                    ->unique()
                    ->values()
                    ->take(20)
                    ->all(),
                'coverage' => is_array($coverage) ? $coverage : [],
                'metadata' => [
                    'plan_title' => mb_substr((string) $plan->title, 0, 180),
                    'plan_category' => mb_substr((string) ($plan->category ?? ''), 0, 120),
                    'task_title' => mb_substr((string) $task->title, 0, 180),
                    'exam_profile_label' => mb_substr((string) ($examProfile['label'] ?? ''), 0, 160),
                    'question_mix' => is_array($strategy['question_mix'] ?? null)
                        ? $strategy['question_mix']
                        : [],
                    'question_provider' => $provider,
                ],
            ],
        );
    }
}
