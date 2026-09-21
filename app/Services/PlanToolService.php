<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Task;

class PlanToolService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function forTask(Plan $plan, Task $task, bool $canEdit = true): array
    {
        if (! $canEdit || in_array($task->status, ['done', 'cancelled'], true) || (int) $task->progress_percent >= 100) {
            return [];
        }

        $tools = [
            [
                'id' => 'timer',
                'name' => '集中タイマー',
                'description' => '作業時間を計り、終了後の実績をこのTaskへ残します。',
                'icon' => '◷',
                'recommended' => ! $this->isStudyPlan($plan),
                'badge' => '標準',
            ],
        ];

        if ($this->isStudyPlan($plan)) {
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
            $resourceCount = ($task->relationLoaded('resources') ? $task->resources->count() : 0)
                + ($plan->relationLoaded('resources') ? $plan->resources->count() : 0);
            $tools[] = [
                'id' => 'resources',
                'name' => '関連資料',
                'description' => $resourceCount > 0
                    ? "登録済みの資料 {$resourceCount} 件から必要な情報を開きます。"
                    : '参考資料やURLをこのPlanへまとめます。',
                'icon' => '⌘',
                'recommended' => false,
                'badge' => $resourceCount > 0 ? "{$resourceCount}件" : '資料',
            ];
        }

        if ($this->isProjectPlan($plan)) {
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
