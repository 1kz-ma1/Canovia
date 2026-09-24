<?php

namespace App\Services;

use App\Data\PlanCategoryProfileData;
use App\Data\PlanSurfaceModuleData;
use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Collection;

class PlanSurfaceEngine
{
    /**
     * Build a deterministic list of registered UI modules.
     *
     * Today this is rule-based. A future AI policy can change priority /
     * visibility using the same module ids without generating arbitrary UI.
     *
     * @param array<string,mixed> $situation
     * @return Collection<int,PlanSurfaceModuleData>
     */
    public function build(
        Plan $plan,
        PlanCategoryProfileData $profile,
        array $situation,
        ?Task $currentTask,
    ): Collection {
        $modules = collect();

        if ($currentTask) {
            $modules->push($this->module(
                'current_task',
                'dashboard.surfaces.current-task',
                100,
                'primary',
                '今取り組むTaskは全カテゴリ共通で最優先です。',
            ));
        }

        if ($profile->key === 'career') {
            $modules->push($this->module(
                'career_pipeline',
                'dashboard.surfaces.career-pipeline',
                94,
                'primary',
                '就活では複数企業・選考段階の現在地を同時に把握する価値が高いためです.',
                ['pipeline' => $situation['career_pipeline'] ?? collect()],
            ));

            if ((bool) ($situation['career_has_interview'] ?? false)) {
                $modules->push($this->module(
                    'career_interview_focus',
                    'dashboard.surfaces.career-interview-focus',
                    (bool) ($situation['career_interview_is_current'] ?? false) ? 106 : 96,
                    'primary',
                    '面接・選考Taskが存在する間だけ面接対策を前面に出します。',
                    ['tasks' => $situation['career_interview_tasks'] ?? collect()],
                ));
            }
        }

        if ($profile->key === 'study' && ((bool) ($situation['has_ai_practice'] ?? false) || (bool) ($situation['study_has_assessment'] ?? false))) {
            $modules->push($this->module(
                'study_focus',
                'dashboard.surfaces.study-focus',
                92,
                'supporting',
                '資格学習では演習結果と弱点・次Actionが日々の判断材料になるためです。',
                [
                    'score' => $situation['study_latest_score'] ?? null,
                    'weaknesses' => $situation['study_weaknesses'] ?? [],
                    'next_action' => $situation['study_next_action'] ?? null,
                ],
            ));
        }

        if (in_array($profile->key, ['development', 'creative'], true)
            && ((int) ($situation['delivery_artifact_count'] ?? 0) > 0 || (bool) ($situation['delivery_has_artifact_evidence'] ?? false))) {
            $modules->push($this->module(
                'delivery_focus',
                'dashboard.surfaces.delivery-focus',
                86,
                'supporting',
                '開発・制作では成果物の状態変化が強い実行Signalになるためです。',
                [
                    'artifact_count' => (int) ($situation['delivery_artifact_count'] ?? 0),
                    'latest_artifact' => $situation['delivery_latest_artifact'] ?? null,
                ],
            ));
        }

        if ((bool) ($situation['has_recent_evidence'] ?? false)) {
            $modules->push($this->module(
                'recent_evidence',
                'dashboard.surfaces.recent-evidence',
                78,
                'supporting',
                'Canoviaが確認できた直近の事実を、進捗率とは分けて返します。',
            ));
        }

        if ((int) ($situation['active_task_count'] ?? 0) > 0) {
            $modules->push($this->module(
                'task_list',
                'dashboard.surfaces.task-list',
                60,
                'supporting',
                'Current Taskの次に控えるTaskを短く確認できるようにします。',
            ));
        }

        $modules->push($this->module(
            'plan_tools',
            'dashboard.surfaces.plan-tools',
            40,
            'available',
            '必要なToolへの入口は残しつつ、主要カードより下へ置きます。',
        ));

        $modules->push($this->module(
            'recent_activity',
            'dashboard.surfaces.recent-activity',
            20,
            'available',
            '履歴は重要ですが、次Actionより優先しません。',
        ));

        return $modules
            ->sortByDesc(fn (PlanSurfaceModuleData $module) => $module->priority)
            ->values();
    }

    private function module(
        string $id,
        string $view,
        int $priority,
        string $visibility,
        string $reason,
        array $payload = [],
    ): PlanSurfaceModuleData {
        return new PlanSurfaceModuleData(
            id: $id,
            view: $view,
            priority: $priority,
            visibility: $visibility,
            reason: $reason,
            payload: $payload,
        );
    }
}
