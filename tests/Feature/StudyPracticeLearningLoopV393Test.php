<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\StudyPracticeAttempt;
use App\Models\Task;
use App\Models\User;
use App\Services\StudyPracticePromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudyPracticeLearningLoopV393Test extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_is_persisted_once_by_request_hash(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $session = $this->answeredSession($plan, $task);
        $json = json_encode($this->assessment($plan, $task, 84, 72), JSON_UNESCAPED_UNICODE);

        $this->actingAs($user)
            ->withSession($session)
            ->post(route('plans.tasks.study_practice.assessment', [$plan, $task]), ['assessment_json' => $json])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]));

        $this->actingAs($user)
            ->withSession($session)
            ->post(route('plans.tasks.study_practice.assessment', [$plan, $task]), ['assessment_json' => $json])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]));

        $this->assertDatabaseCount('study_practice_attempts', 1);
        $attempt = StudyPracticeAttempt::firstOrFail();
        $this->assertSame(84, $attempt->score_percent);
        $this->assertSame(['MTU計算'], $attempt->weaknesses);
        $this->assertNotNull($attempt->request_hash);
    }

    public function test_same_content_in_a_new_practice_session_is_saved_as_a_new_attempt(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $json = json_encode($this->assessment($plan, $task, 84, 72), JSON_UNESCAPED_UNICODE);
        $first = $this->answeredSession($plan, $task);
        $second = $this->answeredSession($plan, $task);
        $second["study_practice.{$plan->id}.{$task->id}"]['attempt_token'] = '00000000-0000-4000-8000-000000000002';

        $this->actingAs($user)
            ->withSession($first)
            ->post(route('plans.tasks.study_practice.assessment', [$plan, $task]), ['assessment_json' => $json]);

        $this->actingAs($user)
            ->withSession($second)
            ->post(route('plans.tasks.study_practice.assessment', [$plan, $task]), ['assessment_json' => $json]);

        $this->assertDatabaseCount('study_practice_attempts', 2);
    }

    public function test_confirmed_assessment_updates_task_once_and_never_regresses_progress(): void
    {
        [$user, $plan, $task] = $this->studyPlan(progress: 80);
        $attempt = StudyPracticeAttempt::create([
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'request_hash' => hash('sha256', 'study-attempt'),
            'exercise_title' => 'ネットワーク確認',
            'questions' => [['id' => 'q1']],
            'answers' => [['question_id' => 'q1', 'answer' => 'A']],
            'assessment' => ['score_percent' => 60],
            'score_percent' => 60,
            'strengths' => ['DNS'],
            'weaknesses' => ['MTU計算'],
            'recommended_task_progress_percent' => 70,
            'evidence_summary' => 'MTU計算に誤答が残る。',
            'next_action' => 'MTU/TCP分割の類題を5問解く',
        ]);
        $payload = ['attempt_id' => $attempt->id, 'request_hash' => $attempt->request_hash];

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.apply', [$plan, $task]), $payload)
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]));

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.apply', [$plan, $task]), $payload)
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]));

        $task->refresh();
        $attempt->refresh();

        $this->assertSame(80, $task->progress_percent);
        $this->assertSame('MTU/TCP分割の類題を5問解く', $task->next_action_note);
        $this->assertStringContainsString('AI演習 60%', (string) $task->progress_reason);
        $this->assertSame(80, $attempt->progress_before_percent);
        $this->assertSame(80, $attempt->progress_after_percent);
        $this->assertNotNull($attempt->applied_at);
        $this->assertDatabaseCount('study_practice_attempts', 1);
    }

    public function test_confirmed_assessment_can_advance_progress(): void
    {
        [$user, $plan, $task] = $this->studyPlan(progress: 30);
        $attempt = StudyPracticeAttempt::create([
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'request_hash' => hash('sha256', 'advance-attempt'),
            'exercise_title' => 'ネットワーク確認',
            'questions' => [['id' => 'q1']],
            'answers' => [['question_id' => 'q1', 'answer' => 'A']],
            'assessment' => ['score_percent' => 90],
            'score_percent' => 90,
            'strengths' => ['DNS'],
            'weaknesses' => [],
            'recommended_task_progress_percent' => 75,
            'evidence_summary' => '主要概念を理解している。',
            'next_action' => '計算問題を追加で3問解く',
        ]);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.apply', [$plan, $task]), [
                'attempt_id' => $attempt->id,
                'request_hash' => $attempt->request_hash,
            ]);

        $task->refresh();
        $attempt->refresh();

        $this->assertSame(75, $task->progress_percent);
        $this->assertSame('doing', $task->status);
        $this->assertSame(30, $attempt->progress_before_percent);
        $this->assertSame(75, $attempt->progress_after_percent);
        $this->assertNotNull($attempt->applied_at);
    }

    public function test_recent_weaknesses_are_included_in_next_generation_prompt(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        StudyPracticeAttempt::create([
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'request_hash' => hash('sha256', 'history-attempt'),
            'exercise_title' => '前回演習',
            'questions' => [['id' => 'q1']],
            'answers' => [['question_id' => 'q1', 'answer' => 'A']],
            'assessment' => ['score_percent' => 70],
            'score_percent' => 70,
            'strengths' => ['DNS'],
            'weaknesses' => ['MTU計算', 'TCP分割'],
            'recommended_task_progress_percent' => 60,
            'evidence_summary' => '計算問題で誤答。',
            'next_action' => 'MTUの類題を解く',
        ]);

        $attempts = StudyPracticeAttempt::where('task_id', $task->id)->latest()->get();
        $prompt = app(StudyPracticePromptService::class)->generationPrompt($plan, $task, $attempts);

        $this->assertStringContainsString('MTU計算', $prompt);
        $this->assertStringContainsString('TCP分割', $prompt);
        $this->assertStringContainsString('過去のAI演習でweaknessesがある場合', $prompt);
        $this->assertStringContainsString('返答直前にJSONとして構文解析できることを確認してください', $prompt);
        $this->assertStringContainsString('スマートクォート（“ ”）は使わないでください', $prompt);
    }

    private function answeredSession(Plan $plan, Task $task): array
    {
        return [
            "study_practice.{$plan->id}.{$task->id}" => [
                'title' => 'ネットワーク確認',
                'questions' => [[
                    'id' => 'q1',
                    'type' => 'text',
                    'prompt' => 'DNSの役割を説明してください。',
                    'choices' => [],
                ]],
                'answers' => [[
                    'question_id' => 'q1',
                    'answer' => '名前解決',
                ]],
                'evaluation_prompt' => 'evaluation prompt',
                'assessment' => null,
                'attempt_id' => null,
                'attempt_token' => '00000000-0000-4000-8000-000000000001',
            ],
        ];
    }

    private function assessment(Plan $plan, Task $task, int $score, int $progress): array
    {
        return [
            'flow' => 'study_assessment',
            'target_plan' => ['id' => $plan->id],
            'target_task' => ['id' => $task->id],
            'score_percent' => $score,
            'strengths' => ['DNS'],
            'weaknesses' => ['MTU計算'],
            'recommended_task_progress_percent' => $progress,
            'evidence_summary' => 'DNSは理解、MTU計算に誤答。',
            'next_action' => 'MTU/TCP分割の類題を5問解く',
        ];
    }

    private function studyPlan(int $progress = 20): array
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
            'title' => 'ネットワーク分野の演習',
            'description' => '理解度を確認する',
            'estimated_minutes' => 120,
            'remaining_minutes' => 90,
            'progress_percent' => $progress,
            'status' => $progress > 0 ? 'doing' : 'todo',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 1,
        ]);

        return [$user, $plan, $task];
    }
}
