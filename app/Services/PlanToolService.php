<?php

namespace App\Services;

use App\Enums\FeatureKey;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;

class PlanToolService
{
    public function __construct(
        private readonly FeatureAccessService $featureAccess,
        private readonly TaskExecutionRegistry $executionRegistry,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forTask(Plan $plan, Task $task, bool $canEdit = true, ?User $actor = null): array
    {
        if (! $canEdit || in_array($task->status, ['done', 'cancelled'], true) || (int) $task->progress_percent >= 100) {
            return [];
        }

        $tools = [
            [
                'id' => 'timer',
                'name' => '集中タイマー',
                'description' => '必要なときだけ使う任意の集中モードです。時間は進捗の証拠ではなく、作業量を振り返る補助情報として扱います。',
                'icon' => '◷',
                'recommended' => false,
                'badge' => '任意',
            ],
        ];

        if (
            $this->isStudyPlan($plan)
            && $this->featureAccess->canUse($actor, FeatureKey::AiPractice, [
                'plan_id' => (int) $plan->id,
                'task_id' => (int) $task->id,
            ])
        ) {
            $tools[] = [
                'id' => 'ai_practice',
                'name' => 'AI演習',
                'description' => 'Taskの内容から問題を作り、Canovia上で解いて理解度を確認します。',
                'icon' => '✦',
                'recommended' => $this->practiceFriendly($task),
                'badge' => '学習',
            ];
        }

        if ($plan->relationLoaded('resources') || $task->relationLoaded('resources')) {
            $taskResourceCount = $task->relationLoaded('resources') ? $task->resources->count() : 0;
            $planResourceCount = $plan->relationLoaded('resources') ? $plan->resources->count() : 0;
            $resourceCount = max($taskResourceCount, $planResourceCount);
            $resourceDescription = match (true) {
                $taskResourceCount > 0 && $planResourceCount > 0
                    => "このTaskに関連 {$taskResourceCount} 件 / Plan全体 {$planResourceCount} 件の資料があります。",
                $taskResourceCount > 0
                    => "このTaskに関連する資料 {$taskResourceCount} 件を開きます。",
                $planResourceCount > 0
                    => "Plan全体の資料 {$planResourceCount} 件から必要な情報を開きます。",
                default => '参考資料やURLをこのPlanへまとめます。',
            };

            $tools[] = [
                'id' => 'resources',
                'name' => '関連資料',
                'description' => $resourceDescription,
                'icon' => '⌘',
                'recommended' => false,
                'badge' => $taskResourceCount > 0 ? "Task {$taskResourceCount}件" : ($resourceCount > 0 ? "{$resourceCount}件" : '資料'),
            ];
        }

        if (
            $this->isProjectPlan($plan)
            && $this->featureAccess->canUse($actor, FeatureKey::ProjectArtifact, [
                'plan_id' => (int) $plan->id,
                'task_id' => (int) $task->id,
            ])
        ) {
            $artifactCount = $task->relationLoaded('artifacts') ? $task->artifacts->count() : 0;
            $tools[] = [
                'id' => 'artifacts',
                'name' => '制作ファイル',
                'description' => $artifactCount > 0
                    ? "このTaskに紐づく制作ファイル {$artifactCount} 件を確認します。"
                    : 'GitHub・Driveなどの制作物をこのTaskと結びつけます。',
                'icon' => '◇',
                'recommended' => $artifactCount > 0 || $this->projectWorkFriendly($task),
                'badge' => $artifactCount > 0 ? "{$artifactCount}件" : '制作',
            ];
        }

        return collect($tools)
            ->map(fn (array $tool) => array_merge(
                $tool,
                $this->executionRegistry->descriptor((string) ($tool['id'] ?? '')),
            ))
            ->sortByDesc(fn (array $tool) => $tool['recommended'] ? 1 : 0)
            ->values()
            ->all();
    }

    public function looksLikeStudyPlan(Plan $plan): bool
    {
        if ($this->isStudyPlan($plan)) {
            return true;
        }

        $text = mb_strtolower(trim($plan->title.' '.($plan->description ?? '')));

        return preg_match(
            '/応用情報|基本情報|情報処理|itパスポート|資格|試験|検定|toeic|簿記|学習|勉強|ap対策|ap試験|ap学習/u',
            $text
        ) === 1;
    }

    private function isStudyPlan(Plan $plan): bool
    {
        return trim((string) $plan->category) === '資格学習';
    }

    private function isProjectPlan(Plan $plan): bool
    {
        return in_array(trim((string) $plan->category), ['個人開発', 'ゲーム開発', '制作活動'], true);
    }

    private function practiceFriendly(Task $task): bool
    {
        $text = mb_strtolower(trim($task->title.' '.($task->description ?? '')));

        return preg_match('/演習|問題|過去問|復習|理解|確認|計算|暗記|対策|学習|sql|ネットワーク|データベース/u', $text) === 1;
    }

    private function projectWorkFriendly(Task $task): bool
    {
        $text = mb_strtolower(trim($task->title.' '.($task->description ?? '')));

        return preg_match('/実装|修正|設計|開発|制作|コード|デプロイ|テスト|ui|api/u', $text) === 1;
    }
}
