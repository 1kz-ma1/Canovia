<?php

namespace App\Services;

use App\Data\PlanCategoryProfileData;
use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Collection;

class PlanSituationResolver
{
    /**
     * @param Collection<int,mixed> $recentEvidence
     * @param Collection<int,array<string,mixed>> $executionTools
     * @return array<string,mixed>
     */
    public function resolve(
        Plan $plan,
        PlanCategoryProfileData $profile,
        ?Task $currentTask,
        Collection $recentEvidence,
        Collection $executionTools,
    ): array {
        $tasks = $plan->tasks;
        $activeTasks = $tasks
            ->filter(fn (Task $task) => ! in_array($task->status, ['done', 'cancelled'], true) && (int) $task->progress_percent < 100)
            ->values();

        $deadlineDays = $plan->deadline
            ? (int) now()->startOfDay()->diffInDays($plan->deadline->copy()->startOfDay(), false)
            : null;

        $situation = [
            'profile_key' => $profile->key,
            'active_task_count' => $activeTasks->count(),
            'done_task_count' => $tasks->where('status', 'done')->count(),
            'has_current_task' => (bool) $currentTask,
            'current_task_id' => $currentTask?->id,
            'deadline_days' => $deadlineDays,
            'deadline_soon' => $deadlineDays !== null && $deadlineDays >= 0 && $deadlineDays <= 7,
            'deadline_overdue' => $deadlineDays !== null && $deadlineDays < 0,
            'recent_evidence_count' => $recentEvidence->count(),
            'has_recent_evidence' => $recentEvidence->isNotEmpty(),
            'has_artifacts' => $plan->artifacts->isNotEmpty(),
            'has_resources' => $plan->resources->isNotEmpty(),
            'has_ai_practice' => $executionTools->contains(fn (array $tool) => ($tool['id'] ?? null) === 'ai_practice'),
        ];

        if ($profile->key === 'career') {
            $situation = array_merge($situation, $this->careerSituation($plan, $tasks, $activeTasks, $currentTask));
        }

        if ($profile->key === 'study') {
            $situation = array_merge($situation, $this->studySituation($recentEvidence));
        }

        if (in_array($profile->key, ['development', 'creative'], true)) {
            $situation = array_merge($situation, $this->deliverySituation($plan, $recentEvidence));
        }

        return $situation;
    }

    /**
     * @return array<string,mixed>
     */
    private function careerSituation(Plan $plan, Collection $tasks, Collection $activeTasks, ?Task $currentTask): array
    {
        $applications = $plan->relationLoaded('careerApplications')
            ? $plan->careerApplications
            : $plan->careerApplications()->with('selectionEvents.interviewReview')->get();
        $captures = $plan->relationLoaded('careerCaptures')
            ? $plan->careerCaptures
            : $plan->careerCaptures()->get();

        $activeInterviewTasks = $activeTasks
            ->filter(fn (Task $task) => $this->careerStage($task) === 'interview')
            ->values();

        if ($applications->isNotEmpty()) {
            $pipeline = $this->careerApplicationPipeline($applications);

            $events = $applications
                ->flatMap(fn ($application) => $application->selectionEvents)
                ->filter(fn ($event) => $event->type === 'interview')
                ->values();

            $nextInterview = $events
                ->filter(fn ($event) => $event->status === 'scheduled' && $event->scheduled_at && $event->scheduled_at->isFuture())
                ->sortBy('scheduled_at')
                ->first();

            $reviewDue = $events
                ->filter(function ($event) {
                    $completedReview = $event->interviewReview?->status === \App\Models\InterviewReview::STATUS_COMPLETED;
                    if ($completedReview) {
                        return false;
                    }

                    if ($event->status === 'completed') {
                        return true;
                    }

                    return $event->status === 'scheduled'
                        && $event->scheduled_at
                        && $event->scheduled_at->lte(now());
                })
                ->sortBy(fn ($event) => $event->scheduled_at?->timestamp ?? PHP_INT_MAX)
                ->first();

            $resultWaiting = $events
                ->filter(fn ($event) => $event->status === 'result_waiting')
                ->values();

            return [
                'career_pipeline' => $pipeline,
                'career_pipeline_source' => 'applications',
                'career_stage' => $currentTask ? $this->careerStage($currentTask) : null,
                'career_has_interview' => (bool) $nextInterview || (bool) $reviewDue || $activeInterviewTasks->isNotEmpty(),
                'career_interview_tasks' => $activeInterviewTasks->take(3)->values(),
                'career_interview_is_current' => $currentTask ? $this->careerStage($currentTask) === 'interview' : false,
                'career_application_count' => $applications->count(),
                'career_interview_count' => $events
                    ->filter(fn ($event) => in_array($event->status, ['scheduled', 'completed', 'result_waiting'], true))
                    ->count(),
                'career_pending_capture_count' => $captures->where('status', 'pending')->count(),
                'career_next_interview_event' => $nextInterview,
                'career_review_due_event' => $reviewDue,
                'career_result_waiting_events' => $resultWaiting,
                'career_result_waiting_count' => $resultWaiting->count(),
            ];
        }

        $stages = collect([
            'discovery' => ['label' => '企業・職種探し', 'tasks' => collect()],
            'application' => ['label' => '応募・書類', 'tasks' => collect()],
            'interview' => ['label' => '面接・選考', 'tasks' => collect()],
            'offer' => ['label' => '内定・条件確認', 'tasks' => collect()],
            'other' => ['label' => 'その他', 'tasks' => collect()],
        ]);

        foreach ($tasks as $task) {
            $stage = $this->careerStage($task);
            $stageData = $stages->get($stage);
            $stageData['tasks']->push($task);
            $stages->put($stage, $stageData);
        }

        $pipeline = $stages
            ->map(function (array $stage, string $key) {
                $tasks = $stage['tasks'];

                return [
                    'key' => $key,
                    'label' => $stage['label'],
                    'total' => $tasks->count(),
                    'done' => $tasks->filter(fn (Task $task) => $task->status === 'done' || (int) $task->progress_percent >= 100)->count(),
                    'active' => $tasks->filter(fn (Task $task) => ! in_array($task->status, ['done', 'cancelled'], true) && (int) $task->progress_percent < 100)->count(),
                    'tasks' => $tasks->take(4)->values(),
                ];
            })
            ->values();

        $currentStage = $currentTask ? $this->careerStage($currentTask) : null;

        return [
            'career_pipeline' => $pipeline,
            'career_pipeline_source' => 'tasks',
            'career_stage' => $currentStage,
            'career_has_interview' => $activeInterviewTasks->isNotEmpty(),
            'career_interview_tasks' => $activeInterviewTasks->take(3)->values(),
            'career_interview_is_current' => $currentStage === 'interview',
            'career_application_count' => 0,
            'career_interview_count' => (int) data_get($pipeline->firstWhere('key', 'interview'), 'total', 0),
            'career_pending_capture_count' => $captures->where('status', 'pending')->count(),
            'career_next_interview_event' => null,
            'career_review_due_event' => null,
            'career_result_waiting_events' => collect(),
            'career_result_waiting_count' => 0,
        ];
    }

    private function careerApplicationPipeline(Collection $applications): Collection
    {
        $definitions = collect([
            'discovery' => ['label' => '候補・応募準備', 'stages' => ['candidate', 'preparing']],
            'application' => ['label' => '応募・書類', 'stages' => ['applied', 'screening']],
            'interview' => ['label' => '面接・選考', 'stages' => ['interview', 'final_interview']],
            'offer' => ['label' => '内定・条件確認', 'stages' => ['offer']],
            'closed' => ['label' => '終了', 'stages' => ['closed']],
        ]);

        return $definitions
            ->map(function (array $definition, string $key) use ($applications) {
                $items = $applications
                    ->filter(fn ($application) => in_array($application->stage, $definition['stages'], true))
                    ->values();

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'total' => $items->count(),
                    'done' => $key === 'closed' ? $items->count() : 0,
                    'active' => $items->filter(fn ($application) => in_array($application->status, ['active', 'waiting'], true))->count(),
                    'applications' => $items->take(4)->values(),
                ];
            })
            ->values();
    }

    private function careerStage(Task $task): string
    {
        $text = mb_strtolower(trim($task->title.' '.($task->description ?? '').' '.($task->next_action_note ?? '')));

        return match (true) {
            preg_match('/内定|オファー|条件面|条件確認|承諾|入社|意思決定/u', $text) === 1 => 'offer',
            preg_match('/面接|面談|一次|二次|三次|最終|選考|模擬面接|逆質問/u', $text) === 1 => 'interview',
            preg_match('/応募|エントリー|履歴書|職務経歴書|es|エントリーシート|書類|ポートフォリオ|提出/u', $text) === 1 => 'application',
            preg_match('/企業研究|企業探|求人|職種|業界|候補|会社探|リサーチ|適職|自己分析/u', $text) === 1 => 'discovery',
            default => 'other',
        };
    }

    /**
     * @param Collection<int,mixed> $recentEvidence
     * @return array<string,mixed>
     */
    private function studySituation(Collection $recentEvidence): array
    {
        $latest = $recentEvidence->first(fn ($evidence) => $evidence->type === 'study_practice_assessed');

        return [
            'study_has_assessment' => (bool) $latest,
            'study_latest_score' => $latest ? (int) data_get($latest->metadata, 'score_percent', 0) : null,
            'study_weaknesses' => $latest ? collect(data_get($latest->metadata, 'weaknesses', []))->take(3)->values()->all() : [],
            'study_next_action' => $latest ? data_get($latest->metadata, 'next_action') : null,
        ];
    }

    /**
     * @param Collection<int,mixed> $recentEvidence
     * @return array<string,mixed>
     */
    private function deliverySituation(Plan $plan, Collection $recentEvidence): array
    {
        $artifactEvidence = $recentEvidence->filter(fn ($evidence) => $evidence->type === 'artifact_state_observed');

        return [
            'delivery_artifact_count' => $plan->artifacts->count(),
            'delivery_has_artifact_evidence' => $artifactEvidence->isNotEmpty(),
            'delivery_latest_artifact' => $artifactEvidence->first(),
        ];
    }
}
