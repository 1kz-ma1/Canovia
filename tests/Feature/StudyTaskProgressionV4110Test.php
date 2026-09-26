<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\StudyPracticeAttempt;
use App\Models\Task;
use App\Models\User;
use App\Services\StudyPracticePromptService;
use App\Services\StudyPracticeStrategyService;
use App\Services\StudyTaskProgressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudyTaskProgressionV4110Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_one_strong_completion_signal_requires_mastery_verification(): void
    {
        [$user, $plan, $task, $nextTask] = $this->scenario();

        $this->attempt($user, $plan, $task, [
            'score' => 62,
            'weaknesses' => ['DNS'],
            'recommended' => 70,
            'next_kind' => 'practice',
            'created_at' => now()->subMinutes(10),
        ]);
        $current = $this->attempt($user, $plan, $task, [
            'score' => 94,
            'strengths' => ['DNS'],
            'weaknesses' => [],
            'recommended' => 100,
            'next_kind' => 'complete_task',
            'created_at' => now(),
        ]);

        $decision = app(StudyTaskProgressionService::class)->resolve(
            $plan,
            $task,
            StudyPracticeAttempt::where('task_id', $task->id)->latest('created_at')->latest('id')->get(),
        );

        $this->assertSame('verify_mastery', $decision['kind']);
        $this->assertFalse($decision['verification']['passed']);
        $this->assertNull($decision['next_task']);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.apply', [$plan, $task]), [
                'attempt_id' => $current->id,
                'request_hash' => $current->request_hash,
                'continue_after_apply' => '1',
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertSessionHas('status', 'Task完了前の仕上げ確認を行います。別の問題で理解が安定しているか確認します。');

        $task->refresh();
        $this->assertSame(99, $task->progress_percent);
        $this->assertSame('doing', $task->status);
        $this->assertSame('完了前の仕上げ確認を行う', $task->next_action_note);
        $this->assertSame('todo', $nextTask->fresh()->status);

        $strategy = app(StudyPracticeStrategyService::class)->build(
            $plan,
            $task,
            StudyPracticeAttempt::where('task_id', $task->id)->latest('created_at')->latest('id')->get(),
        );

        $this->assertSame('mastery_verification', $strategy['key']);
        $this->assertSame(5, $strategy['target_question_count']);
        $this->assertSame([], $strategy['focus_topics']);
        $this->assertSame([
            'primary' => 0,
            'secondary' => 0,
            'diagnostic' => 5,
        ], $strategy['question_mix']);
    }

    public function test_two_consecutive_strong_results_complete_task_and_advance_to_next_task(): void
    {
        [$user, $plan, $task, $nextTask] = $this->scenario();

        $this->attempt($user, $plan, $task, [
            'score' => 60,
            'weaknesses' => ['DNS'],
            'recommended' => 65,
            'next_kind' => 'practice',
            'created_at' => now()->subMinutes(20),
        ]);
        $this->attempt($user, $plan, $task, [
            'score' => 90,
            'strengths' => ['DNS'],
            'weaknesses' => [],
            'recommended' => 95,
            'next_kind' => 'continue_task',
            'created_at' => now()->subMinutes(10),
        ]);
        $current = $this->attempt($user, $plan, $task, [
            'score' => 96,
            'strengths' => ['DNS'],
            'weaknesses' => [],
            'recommended' => 100,
            'next_kind' => 'complete_task',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.apply', [$plan, $task]), [
                'attempt_id' => $current->id,
                'request_hash' => $current->request_hash,
                'continue_after_apply' => '1',
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $nextTask]))
            ->assertSessionHas('status', '前のTaskを完了しました。次のTask内容から演習方針を組み立てます。');

        $task->refresh();
        $this->assertSame(100, $task->progress_percent);
        $this->assertSame('done', $task->status);
        $this->assertSame(0, $task->remaining_minutes);

        $decision = app(StudyTaskProgressionService::class)->resolve(
            $plan,
            $task,
            StudyPracticeAttempt::where('task_id', $task->id)->latest('created_at')->latest('id')->get(),
        );
        $this->assertSame('advance_task', $decision['kind']);
        $this->assertSame($nextTask->id, $decision['next_task']->id);

        $nextStrategy = app(StudyPracticeStrategyService::class)->build($plan, $nextTask, collect());
        $prompt = app(StudyPracticePromptService::class)->generationPrompt($plan, $nextTask, collect(), $nextStrategy);

        $this->assertSame($nextTask->title, data_get($nextStrategy, 'task_snapshot.title'));
        $this->assertStringContainsString('タイトル: '.$nextTask->title, $prompt);
        $this->assertStringNotContainsString('タイトル: '.$task->title."\n説明:", $prompt);
    }

    public function test_blocking_error_prevents_a_high_score_from_counting_as_mastery_confirmation(): void
    {
        [$user, $plan, $task] = $this->scenario();

        $this->attempt($user, $plan, $task, [
            'score' => 92,
            'weaknesses' => [],
            'recommended' => 95,
            'next_kind' => 'continue_task',
            'created_at' => now()->subMinutes(10),
        ]);
        $this->attempt($user, $plan, $task, [
            'score' => 90,
            'weaknesses' => [],
            'recommended' => 100,
            'next_kind' => 'complete_task',
            'error_type' => 'concept_gap',
            'correctness' => 'partial',
            'created_at' => now(),
        ]);

        $decision = app(StudyTaskProgressionService::class)->resolve(
            $plan,
            $task,
            StudyPracticeAttempt::where('task_id', $task->id)->latest('created_at')->latest('id')->get(),
        );

        $this->assertSame('verify_mastery', $decision['kind']);
        $this->assertSame(1, $decision['verification']['strong_attempt_count']);
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function attempt(User $user, Plan $plan, Task $task, array $overrides): StudyPracticeAttempt
    {
        $score = (int) ($overrides['score'] ?? 80);
        $recommended = (int) ($overrides['recommended'] ?? $task->progress_percent);
        $weaknesses = $overrides['weaknesses'] ?? [];
        $strengths = $overrides['strengths'] ?? [];
        $nextKind = (string) ($overrides['next_kind'] ?? 'continue_task');
        $errorType = (string) ($overrides['error_type'] ?? 'none');
        $correctness = (string) ($overrides['correctness'] ?? 'correct');

        return StudyPracticeAttempt::create([
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'request_hash' => hash('sha256', Str::uuid()->toString()),
            'exercise_title' => '理解度確認',
            'questions' => [['id' => 'q1']],
            'answers' => [['question_id' => 'q1', 'answer' => 'A']],
            'assessment' => [
                'score_percent' => $score,
                'question_feedback' => [[
                    'question_id' => 'q1',
                    'correctness' => $correctness,
                    'feedback' => '',
                    'reasoning_feedback' => '',
                    'error_type' => $errorType,
                    'weakness_topics' => $errorType === 'none' ? [] : ['DNS'],
                    'misconceptions' => [],
                ]],
                'strengths' => $strengths,
                'weaknesses' => $weaknesses,
                'recommended_task_progress_percent' => $recommended,
                'evidence_summary' => 'テスト用評価',
                'next_action' => '次の学習へ進む',
                'next_step' => [
                    'kind' => $nextKind,
                    'label' => $nextKind === 'complete_task' ? 'このTaskを完了する' : '学習を続ける',
                    'reason' => '',
                    'focus_topics' => [],
                    'question_count' => 0,
                ],
            ],
            'score_percent' => $score,
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'recommended_task_progress_percent' => $recommended,
            'evidence_summary' => 'テスト用評価',
            'next_action' => '次の学習へ進む',
            'created_at' => $overrides['created_at'] ?? now(),
            'updated_at' => $overrides['created_at'] ?? now(),
        ]);
    }

    private function scenario(): array
    {
        $user = User::factory()->create();

        $plan = Plan::create([
            'user_id' => $user->id,
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => 'AP対策',
            'category' => '資格学習',
            'start_date' => today(),
            'deadline' => today()->addMonth(),
            'is_public' => false,
        ]);

        $task = Task::create([
            'plan_id' => $plan->id,
            'title' => 'ネットワーク弱点補強',
            'description' => 'DNSとMTUを安定して解けるようにする',
            'estimated_minutes' => 120,
            'remaining_minutes' => 30,
            'progress_percent' => 80,
            'status' => 'doing',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 1,
        ]);

        $nextTask = Task::create([
            'plan_id' => $plan->id,
            'title' => 'データベース弱点補強',
            'description' => '正規化とSQLの理解を確認する',
            'estimated_minutes' => 120,
            'remaining_minutes' => 120,
            'progress_percent' => 0,
            'status' => 'todo',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 2,
            'depends_on_task_id' => $task->id,
        ]);

        return [$user, $plan, $task, $nextTask];
    }
}
