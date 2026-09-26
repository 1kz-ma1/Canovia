<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Collection;

class StudyPracticeStrategyService
{
    public function __construct(
        private readonly StudyPracticeExamProfileService $examProfiles,
        private readonly StudyWeaknessPrioritizationService $weaknessPriorities,
        private readonly StudyTaskProgressionService $progression,
    ) {}

    /**
     * @param Collection<int, mixed> $recentAttempts
     * @return array<string, mixed>
     */
    public function build(Plan $plan, Task $task, Collection $recentAttempts): array
    {
        $recentAttempts = $recentAttempts->take(8)->values();
        $progression = $this->progression->resolve($plan, $task, $recentAttempts);
        $masteryVerification = ($progression['kind'] ?? null) === 'verify_mastery';

        $latest = $recentAttempts->first();
        $latestScore = $latest ? (int) $latest->score_percent : null;
        $guidedNextStep = is_array(data_get($latest?->assessment, 'next_step'))
            ? data_get($latest?->assessment, 'next_step')
            : [];
        $guidedPractice = ($guidedNextStep['kind'] ?? null) === 'practice';
        $guidedFocusTopics = $guidedPractice
            ? collect($guidedNextStep['focus_topics'] ?? [])
                ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                ->map(fn ($item) => trim($item))
                ->unique()
                ->values()
                ->all()
            : [];

        // External AI can suggest a count, but Canovia owns the allocation.
        // Weakness reinforcement needs enough questions to keep a diagnostic
        // slice instead of turning the whole session into one local drill.
        $targetQuestionCount = $masteryVerification
            ? 5
            : ($guidedPractice
                ? max(5, min(20, (int) ($guidedNextStep['question_count'] ?? 5)))
                : 10);

        $weakness = $this->weaknessPriorities->analyze(
            $plan,
            $task,
            $recentAttempts,
            $guidedFocusTopics,
            $targetQuestionCount,
        );
        $examProfile = $this->examProfiles->forPlanTask($plan, $task);

        $focusTopics = $masteryVerification
            ? []
            : collect($weakness['primary_topics'] ?? [])
                ->merge($weakness['secondary_topics'] ?? [])
                ->unique()
                ->take(5)
                ->values()
                ->all();

        if ($masteryVerification) {
            $key = 'mastery_verification';
            $label = '完了前の仕上げ確認';
            $reason = (string) ($progression['reason'] ?? '現在Taskを完了する前に、別の問題で理解が安定しているか確認します。');
        } elseif ($recentAttempts->isEmpty()) {
            $key = 'baseline_assessment';
            $label = '初回理解度確認';
            $reason = 'このTaskではまだ演習履歴がないため、本番に近い形式で現在の理解度を広く確認します。';
        } elseif ((bool) ($weakness['has_any_weakness_signal'] ?? false)) {
            $key = 'weakness_reinforcement';
            $label = '弱点補強（偏り防止）';
            $reason = (bool) ($weakness['has_confirmed_weakness'] ?? false)
                ? '繰り返し確認された弱点を優先しつつ、他の弱点と横断診断も混ぜて局所的な出題偏りを防ぎます。'
                : '単発の誤答は重点弱点へ固定せず、再確認と横断診断を混ぜて本当に補強すべき弱点か見極めます。';
        } elseif ($latestScore !== null && $latestScore >= 85) {
            $key = 'retention_and_transfer';
            $label = '定着・応用確認';
            $reason = '直近の理解度が高いため、同じ暗記確認より本番形式での定着と応用を重視します。';
        } else {
            $key = 'task_mastery';
            $label = 'Task定着確認';
            $reason = 'このTaskの達成に必要な知識を中心に、特定分野へ寄せすぎず理解の穴を確認します。';
        }

        return [
            'key' => $key,
            'version' => 'v2',
            'label' => $label,
            'reason' => $reason,
            'focus_topics' => $focusTopics,
            'target_question_count' => $targetQuestionCount,
            'history_sample_count' => $recentAttempts->count(),
            'latest_score_percent' => $latestScore,
            'exam_profile' => $examProfile,
            'weakness_priority' => $weakness,
            'question_mix' => $masteryVerification
                ? [
                    'primary' => 0,
                    'secondary' => 0,
                    'diagnostic' => $targetQuestionCount,
                ]
                : ($weakness['question_mix'] ?? [
                    'primary' => 0,
                    'secondary' => 0,
                    'diagnostic' => $targetQuestionCount,
                ]),
            'progression' => [
                'kind' => $progression['kind'] ?? 'continue_current',
                'reason' => $progression['reason'] ?? null,
                'verification' => $progression['verification'] ?? null,
            ],
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
