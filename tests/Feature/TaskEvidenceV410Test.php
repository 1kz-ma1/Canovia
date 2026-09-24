<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\StudyPracticeAttempt;
use App\Models\Task;
use App\Models\TaskEvidence;
use App\Models\TaskMilestone;
use App\Models\TaskProgressDecision;
use App\Models\User;
use App\Services\PlanToolService;
use App\Services\TaskEvidenceService;
use App\Services\TaskExecutionRegistry;
use App\Services\TaskMilestoneProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskEvidenceV410Test extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_is_idempotent_and_timer_is_context_not_progress_evidence(): void
    {
        [$user, $plan, $task] = $this->studyPlan();

        $service = app(TaskEvidenceService::class);
        $first = $service->record(
            task: $task,
            source: 'native',
            type: 'study_practice_assessed',
            summary: '10問中9問正解',
            confidence: 100,
            provider: 'study_practice',
            providerReference: 'attempt-1',
            userId: $user->id,
            dedupeKey: 'attempt-1',
        );
        $second = $service->record(
            task: $task,
            source: 'native',
            type: 'study_practice_assessed',
            summary: '10問中9問正解',
            confidence: 100,
            provider: 'study_practice',
            providerReference: 'attempt-1',
            userId: $user->id,
            dedupeKey: 'attempt-1',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('task_evidences', 1);

        $registry = app(TaskExecutionRegistry::class);
        $this->assertSame('context_only', data_get($registry->descriptor('timer'), 'evidence_mode'));
        $this->assertFalse((bool) data_get($registry->descriptor('timer'), 'primary_eligible'));
        $this->assertSame('automatic', data_get($registry->descriptor('ai_practice'), 'evidence_mode'));
        $this->assertTrue((bool) data_get($registry->descriptor('ai_practice'), 'primary_eligible'));

        $tools = collect(app(PlanToolService::class)->forTask($plan, $task, true, $user));
        $timer = $tools->firstWhere('id', 'timer');

        $this->assertNotNull($timer);
        $this->assertFalse((bool) $timer['recommended']);
        $this->assertSame('任意', $timer['badge']);
    }

    public function test_weighted_milestones_expose_a_progress_signal_without_mutating_task_progress(): void
    {
        [, , $task] = $this->studyPlan();

        TaskMilestone::create([
            'task_id' => $task->id,
            'title' => '構成を決める',
            'status' => 'done',
            'weight' => 1,
            'sort_order' => 1,
            'completed_at' => now(),
        ]);
        TaskMilestone::create([
            'task_id' => $task->id,
            'title' => '初稿を作る',
            'status' => 'pending',
            'weight' => 3,
            'sort_order' => 2,
        ]);

        $progress = app(TaskMilestoneProgressService::class)->calculate($task->fresh());

        $this->assertSame(25, $progress);
        $this->assertSame(20, (int) $task->fresh()->progress_percent);
    }

    public function test_ai_practice_creates_native_evidence_and_progress_decision_on_apply(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $key = "study_practice.{$plan->id}.{$task->id}";

        $state = [
            $key => [
                'title' => '可用性確認',
                'questions' => [[
                    'id' => 'q1',
                    'type' => 'text',
                    'prompt' => '並列可用性を説明してください。',
                    'choices' => [],
                    'response_fields' => [[
                        'id' => 'answer',
                        'type' => 'textarea',
                        'label' => '回答',
                        'required' => true,
                        'placeholder' => '',
                        'choices' => [],
                    ]],
                ]],
                'answers' => [[
                    'question_id' => 'q1',
                    'fields' => [[
                        'field_id' => 'answer',
                        'type' => 'textarea',
                        'label' => '回答',
                        'value' => '全台故障の補数を取る。',
                    ]],
                ]],
                'draft_answers' => [
                    'q1' => ['answer' => '全台故障の補数を取る。'],
                ],
                'evaluation_prompt' => 'evaluation prompt',
                'assessment' => null,
                'attempt_id' => null,
                'attempt_token' => (string) Str::uuid(),
                'practice_session_id' => null,
            ],
        ];

        $assessment = [
            'schema_version' => '1.0',
            'flow' => 'study_assessment',
            'target_plan' => ['id' => $plan->id],
            'target_task' => ['id' => $task->id],
            'score_percent' => 90,
            'question_feedback' => [[
                'question_id' => 'q1',
                'correctness' => 'correct',
                'feedback' => '考え方は正しいです。',
                'reasoning_feedback' => '補数を正しく使えています。',
                'misconceptions' => [],
            ]],
            'strengths' => ['並列可用性'],
            'weaknesses' => ['百分率変換'],
            'recommended_task_progress_percent' => 80,
            'evidence_summary' => '並列可用性の考え方を説明できた。',
            'next_action' => '百分率変換を4問確認する',
            'next_step' => [
                'kind' => 'practice',
                'label' => '百分率変換を4問確認する',
                'reason' => '数値変換だけ確認するため。',
                'focus_topics' => ['百分率変換'],
                'question_count' => 4,
            ],
        ];

        $this->actingAs($user)
            ->withSession($state)
            ->post(route('plans.tasks.study_practice.assessment', [$plan, $task]), [
                'assessment_json' => json_encode($assessment, JSON_UNESCAPED_UNICODE),
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertSessionHasNoErrors();

        $attempt = StudyPracticeAttempt::firstOrFail();
        $evidence = TaskEvidence::where('task_id', $task->id)
            ->where('provider', 'study_practice')
            ->where('provider_reference', (string) $attempt->id)
            ->firstOrFail();

        $this->assertSame('native', $evidence->source);
        $this->assertSame('study_practice_assessed', $evidence->type);
        $this->assertSame(90, data_get($evidence->metadata, 'score_percent'));

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.apply', [$plan, $task]), [
                'attempt_id' => $attempt->id,
                'request_hash' => $attempt->request_hash,
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame(80, (int) $task->progress_percent);

        $decision = TaskProgressDecision::where('task_id', $task->id)->firstOrFail();
        $this->assertSame($evidence->id, $decision->task_evidence_id);
        $this->assertSame('rule', $decision->source);
        $this->assertSame('applied', $decision->status);
        $this->assertSame(20, $decision->progress_before_percent);
        $this->assertSame(80, $decision->progress_after_percent);
    }

    public function test_home_plan_tab_is_a_plan_hub_not_a_duplicate_roadmap(): void
    {
        $view = file_get_contents(resource_path('views/dashboard/index.blade.php'));
        $planView = file_get_contents(resource_path('views/plans/show.blade.php'));

        $this->assertStringContainsString('PLAN HUB', $view);
        $this->assertStringContainsString('NEXT ACTION', $view);
        $this->assertStringContainsString('集中タイマー（任意）', $view);
        $this->assertStringContainsString('最近の前進', $view);
        $this->assertStringNotContainsString("'roadmapMode' => 'dashboard'", $view);

        $this->assertStringContainsString('id="task-list"', $planView);
        $this->assertStringContainsString('id="task-{{ $task->id }}"', $planView);
        $this->assertStringContainsString('◷ 集中タイマー', $planView);
        $this->assertStringContainsString('作業量の目安', $planView);
    }

    private function studyPlan(): array
    {
        $user = User::factory()->create();

        $plan = Plan::create([
            'user_id' => $user->id,
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => 'AP対策',
            'description' => '応用情報技術者試験の学習を進める',
            'category' => '資格学習',
            'priority_mode' => 'auto',
            'start_date' => today(),
            'deadline' => today()->addMonth(),
            'is_public' => false,
        ]);

        $task = Task::create([
            'plan_id' => $plan->id,
            'title' => '可用性の弱点補強',
            'description' => '並列可用性の数値変換を確認する',
            'estimated_minutes' => 60,
            'remaining_minutes' => 48,
            'progress_percent' => 20,
            'status' => 'doing',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 1,
        ]);

        $plan->load(['tasks.resources', 'tasks.artifacts', 'resources']);

        return [$user, $plan, $task->fresh()];
    }
}
