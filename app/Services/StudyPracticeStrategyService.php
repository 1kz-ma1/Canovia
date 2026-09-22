<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Collection;

class StudyPracticeStrategyService
{
    /**
     * @param Collection<int, mixed> $recentAttempts
     * @return array<string, mixed>
     */
    public function build(Plan $plan, Task $task, Collection $recentAttempts): array
    {
        $recentAttempts = $recentAttempts->take(5)->values();

        $weaknesses = $recentAttempts
            ->flatMap(fn ($attempt) => collect($attempt->weaknesses ?? []))
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn ($item) => trim($item));

        $misconceptions = $recentAttempts
            ->flatMap(fn ($attempt) => collect(data_get($attempt->assessment, 'question_feedback', [])))
            ->flatMap(fn ($feedback) => collect(is_array($feedback) ? ($feedback['misconceptions'] ?? []) : []))
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->map(fn ($item) => trim($item));

        $focusTopics = $weaknesses
            ->merge($misconceptions)
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(5)
            ->values()
            ->all();

        $latest = $recentAttempts->first();
        $latestScore = $latest ? (int) $latest->score_percent : null;

        if ($recentAttempts->isEmpty()) {
            $key = 'baseline_assessment';
            $label = '初回理解度確認';
            $reason = 'このTaskではまだ演習履歴がないため、現在の理解度を広く確認します。';
        } elseif ($focusTopics !== []) {
            $key = 'weakness_reinforcement';
            $label = '弱点補強';
            $reason = '直近の演習で見つかった弱点・誤解を優先して再確認します。';
        } elseif ($latestScore !== null && $latestScore >= 85) {
            $key = 'retention_and_transfer';
            $label = '定着・応用確認';
            $reason = '直近の理解度が高いため、同じ暗記確認より定着と応用を重視します。';
        } else {
            $key = 'task_mastery';
            $label = 'Task定着確認';
            $reason = 'このTaskの達成に必要な知識を中心に、理解の穴がないか確認します。';
        }

        return [
            'key' => $key,
            'version' => 'v1',
            'label' => $label,
            'reason' => $reason,
            'focus_topics' => $focusTopics,
            'target_question_count' => 10,
            'history_sample_count' => $recentAttempts->count(),
            'latest_score_percent' => $latestScore,
            'task_snapshot' => [
                'title' => (string) $task->title,
                'progress_percent' => (int) $task->progress_percent,
                'remaining_minutes' => (int) $task->remaining_minutes,
                'next_action' => trim((string) ($task->next_action_note ?? '')),
            ],
            'plan_snapshot' => [
                'title' => (string) $plan->title,
                'category' => (string) ($plan->category ?? ''),
            ],
        ];
    }
}
