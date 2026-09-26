<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Services\PlanToolService;
use App\Services\StudyActivityPolicyService;
use App\Services\StudyPracticeReliabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudyActivityPolicyV4110Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_toeic_vocabulary_prefers_recall_instead_of_ai_practice(): void
    {
        [$user, $plan, $task] = $this->scenario(
            'TOEIC 800点',
            'TOEIC英単語を暗記する',
            '頻出語彙を単語帳で覚える',
        );

        $activity = app(StudyActivityPolicyService::class)->forPlanTask($plan, $task);

        $this->assertSame('recall', data_get($activity, 'primary.key'));
        $this->assertGreaterThan(
            (int) collect($activity['all'])->firstWhere('key', 'question_practice')['fit_score'],
            (int) data_get($activity, 'primary.fit_score'),
        );

        $tools = collect(app(PlanToolService::class)->forTask($plan, $task, true, $user));
        $studyActivity = $tools->firstWhere('id', 'study_activity');
        $aiPractice = $tools->firstWhere('id', 'ai_practice');

        $this->assertTrue((bool) data_get($studyActivity, 'recommended'));
        $this->assertSame('recall', data_get($studyActivity, 'activity.key'));
        $this->assertFalse((bool) data_get($aiPractice, 'recommended'));
    }

    public function test_ap_problem_task_keeps_question_practice_as_primary_activity(): void
    {
        [$user, $plan, $task] = $this->scenario(
            '応用情報 科目A対策',
            'ネットワーク過去問演習',
            'DNSとMTUの問題を解いて理解を確認する',
        );

        $activity = app(StudyActivityPolicyService::class)->forPlanTask($plan, $task);
        $this->assertSame('question_practice', data_get($activity, 'primary.key'));

        $tools = collect(app(PlanToolService::class)->forTask($plan, $task, true, $user));
        $this->assertTrue((bool) data_get($tools->firstWhere('id', 'ai_practice'), 'recommended'));
        $this->assertNull($tools->firstWhere('id', 'study_activity'));
    }

    public function test_reference_book_task_prefers_resource_study(): void
    {
        [, $plan, $task] = $this->scenario(
            '簿記2級',
            '参考書の第3章を読む',
            '解説を読んで新しい論点を理解する',
        );

        $activity = app(StudyActivityPolicyService::class)->forPlanTask($plan, $task);

        $this->assertSame('resource_study', data_get($activity, 'primary.key'));
        $this->assertGreaterThanOrEqual(70, (int) data_get($activity, 'primary.fit_score'));
    }

    public function test_activity_page_explains_recall_and_keeps_ai_practice_as_secondary_option(): void
    {
        [$user, $plan, $task] = $this->scenario(
            'TOEIC 800点',
            'TOEIC英単語を暗記する',
            '頻出語彙を毎日思い出せるようにする',
        );

        $this->actingAs($user)
            ->get(route('plans.tasks.study_activity.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('Recall')
            ->assertSee('学習方法の相性')
            ->assertSee('見ずに思い出す')
            ->assertSee('AI演習も使う');
    }

    public function test_reliability_is_guidance_not_claimed_model_accuracy(): void
    {
        [, $plan, $task] = $this->scenario(
            'TOEIC 800点',
            'TOEIC英単語を暗記する',
            '頻出語彙を単語帳で覚える',
        );
        $activity = app(StudyActivityPolicyService::class)->forPlanTask($plan, $task);
        $service = app(StudyPracticeReliabilityService::class);

        $bank = $service->evaluate($activity, ['provider' => 'question_bank'], null, ['target_question_count' => 10]);
        $external = $service->evaluate($activity, ['provider' => 'external_ai'], null, ['target_question_count' => 10]);

        $this->assertGreaterThan(
            data_get($external, 'metrics.0.score'),
            data_get($bank, 'metrics.0.score'),
        );
        $this->assertStringContainsString('実測したAI正答率ではありません', $bank['disclaimer']);

        $methodFit = collect($bank['metrics'])->firstWhere('key', 'method_fit');
        $this->assertLessThan(70, (int) $methodFit['score']);
    }

    public function test_ai_practice_view_contains_visual_reliability_surface(): void
    {
        $view = file_get_contents(resource_path('views/study_practice/show.blade.php'));

        $this->assertStringContainsString('PRACTICE RELIABILITY', $view);
        $this->assertStringContainsString('この演習の信頼度の目安', $view);
        $this->assertStringContainsString('style="width:', $view);
        $this->assertStringContainsString('実測したAI正答率', app(StudyPracticeReliabilityService::class)->evaluate(
            ['all' => [], 'primary' => []],
            ['provider' => 'external_ai'],
            null,
        )['disclaimer']);
    }

    private function scenario(string $planTitle, string $taskTitle, string $taskDescription): array
    {
        $user = User::factory()->create();

        $plan = Plan::create([
            'user_id' => $user->id,
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => $planTitle,
            'description' => $planTitle.'の学習',
            'category' => '資格学習',
            'start_date' => today(),
            'deadline' => today()->addMonth(),
            'is_public' => false,
        ]);

        $task = Task::create([
            'plan_id' => $plan->id,
            'title' => $taskTitle,
            'description' => $taskDescription,
            'estimated_minutes' => 60,
            'remaining_minutes' => 60,
            'progress_percent' => 0,
            'status' => 'todo',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 1,
        ]);

        return [$user, $plan, $task];
    }
}
