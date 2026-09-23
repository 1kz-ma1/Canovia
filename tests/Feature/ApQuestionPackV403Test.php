<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\QuestionPack;
use App\Models\StudyPracticeAttempt;
use App\Models\Task;
use App\Models\User;
use App\Services\AdminAccessService;
use App\Services\QuestionBankCoverageService;
use App\Services\QuestionPackCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApQuestionPackV403Test extends TestCase
{
    use RefreshDatabase;

    public function test_bundled_ap_pack_is_discoverable_and_importable_as_draft(): void
    {
        $catalog = app(QuestionPackCatalogService::class);
        $item = $catalog->all()->firstWhere('key', 'ap/ap-a-canovia-core-v1');

        $this->assertNotNull($item);
        $this->assertSame('AP', $item['exam_code']);
        $this->assertSame(74, $item['question_count']);

        $this->withSession([AdminAccessService::SESSION_KEY => true])
            ->post(route('admin.question_packs.import_bundled'), [
                'catalog_key' => 'ap/ap-a-canovia-core-v1',
            ])
            ->assertRedirect(route('admin.question_packs.index'))
            ->assertSessionHasNoErrors();

        $pack = QuestionPack::where('slug', 'ap-a-canovia-core-v1')->firstOrFail();

        $this->assertSame('draft', $pack->status);
        $this->assertSame(74, $pack->questions()->count());

        $mtu = $pack->questions()->where('external_key', 'net-mtu-001')->firstOrFail();
        $this->assertSame(
            ['ア', 'イ', 'ウ', 'エ'],
            collect($mtu->response_schema[0]['choices'])->pluck('id')->all()
        );
        $this->assertSame('イ', data_get($mtu->grading_rule, 'answer'));
        $this->assertSame('1.1.0', $pack->version);
        $this->assertSame('1.1', data_get($pack->metadata, 'content_rules_version'));

        $bayes = $pack->questions()->where('external_key', 'calc-bayes-disease-025')->firstOrFail();
        $this->assertStringContainsString('病気', $bayes->prompt);
        $this->assertStringContainsString('陽性', $bayes->prompt);
        $this->assertStringNotContainsString('異常な対象', $bayes->prompt);
    }

    public function test_published_ap_pack_covers_single_mtu_dns_and_database_weaknesses(): void
    {
        $this->importAndPublish();

        [$user, $plan, $task] = $this->studyPlan();
        $coverage = app(QuestionBankCoverageService::class);

        foreach ([
            'MTU計算',
            'DNSレコード',
            'DB',
            'ベイズ',
            'MIPS',
            'ボトルネック',
            'ラウンドロビン',
            'スタベーション',
            '可用性',
            '品質特性',
        ] as $focus) {
            $result = $coverage->evaluate($plan, $task, [
                'key' => 'weakness_reinforcement',
                'target_question_count' => 10,
                'focus_topics' => [$focus],
            ]);

            $this->assertTrue($result['available'], $focus.' should be covered.');
            $this->assertSame('ap-a-canovia-core-v1', $result['pack']?->slug);
            $this->assertGreaterThanOrEqual(3, $result['focus_match_count']);
        }

        $genericCalculation = QuestionPack::where('slug', 'ap-a-canovia-core-v1')
            ->firstOrFail()
            ->questions()
            ->where('external_key', 'calc-mips-026')
            ->firstOrFail();

        $this->assertSame(
            0,
            $coverage->questionFocusScore($genericCalculation, collect(['MTU計算'])),
            'A generic 計算 tag must not inflate MTU-specific coverage.'
        );
    }

    public function test_ai_practice_uses_bundled_pack_directly_for_mtu_weakness(): void
    {
        $this->importAndPublish();
        [$user, $plan, $task] = $this->studyPlan();

        StudyPracticeAttempt::create([
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'request_hash' => hash('sha256', 'v403-mtu-history'),
            'exercise_title' => 'MTU確認',
            'questions' => [['id' => 'q1']],
            'answers' => [['question_id' => 'q1', 'fields' => []]],
            'assessment' => ['question_feedback' => []],
            'score_percent' => 50,
            'strengths' => [],
            'weaknesses' => ['MTU計算'],
            'recommended_task_progress_percent' => 20,
            'evidence_summary' => 'MTU計算で誤答',
            'next_action' => 'MTU計算を復習',
        ]);

        $this->actingAs($user)
            ->get(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('Canovia Question Bank')
            ->assertSee('Canovia問題集から演習を始める')
            ->assertDontSee('演習準備プロンプトをコピー');
    }

    private function importAndPublish(): QuestionPack
    {
        $this->withSession([AdminAccessService::SESSION_KEY => true])
            ->post(route('admin.question_packs.import_bundled'), [
                'catalog_key' => 'ap/ap-a-canovia-core-v1',
            ])
            ->assertSessionHasNoErrors();

        $pack = QuestionPack::where('slug', 'ap-a-canovia-core-v1')->firstOrFail();

        $this->withSession([AdminAccessService::SESSION_KEY => true])
            ->patch(route('admin.question_packs.status', $pack), [
                'status' => 'published',
            ])
            ->assertSessionHasNoErrors();

        return $pack->fresh();
    }

    private function studyPlan(): array
    {
        $user = User::factory()->create();

        $plan = Plan::create([
            'user_id' => $user->id,
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => 'AP 応用情報技術者試験',
            'category' => '資格学習',
            'priority_mode' => 'auto',
            'start_date' => today(),
            'deadline' => today()->addMonth(),
            'is_public' => false,
        ]);

        $task = Task::create([
            'plan_id' => $plan->id,
            'title' => '科目A ネットワーク弱点補強',
            'description' => 'MTU・DNS・DB・計算を重点的に補強する',
            'estimated_minutes' => 90,
            'remaining_minutes' => 90,
            'progress_percent' => 20,
            'status' => 'doing',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 1,
        ]);

        return [$user, $plan, $task];
    }
}
