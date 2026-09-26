<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\StudyRecallItem;
use App\Models\StudyRecallReview;
use App\Models\Task;
use App\Models\User;
use App\Services\StudyRecallSchedulerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudyRecallV4111Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_bulk_cards_are_persisted_and_duplicates_are_not_created(): void
    {
        [$user, $plan, $task] = $this->scenario();

        $payload = [
            'cards_text' => "abandon | 放棄する\naccurate | 正確な\nmaintain\t維持する",
        ];

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.items.store', [$plan, $task]), $payload)
            ->assertRedirect(route('plans.tasks.study_recall.show', [$plan, $task]));

        $this->assertDatabaseCount('study_recall_items', 3);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.items.store', [$plan, $task]), $payload)
            ->assertRedirect(route('plans.tasks.study_recall.show', [$plan, $task]));

        $this->assertDatabaseCount('study_recall_items', 3);
    }

    public function test_good_reviews_expand_interval_from_one_day_to_three_days(): void
    {
        [, $plan, $task] = $this->scenario();
        $item = $this->item($plan, $task);

        $scheduler = app(StudyRecallSchedulerService::class);

        $first = $scheduler->review($item, 'good', (string) Str::uuid());
        $this->assertSame(1, $first['item']->repetitions);
        $this->assertSame(1, $first['item']->interval_days);

        $second = $scheduler->review($first['item'], 'good', (string) Str::uuid());
        $this->assertSame(2, $second['item']->repetitions);
        $this->assertSame(3, $second['item']->interval_days);
        $this->assertDatabaseCount('study_recall_reviews', 2);
    }

    public function test_again_resets_repetitions_and_schedules_a_short_retry(): void
    {
        [, $plan, $task] = $this->scenario();
        $item = $this->item($plan, $task, [
            'repetitions' => 4,
            'interval_days' => 10,
            'ease_factor' => 2.50,
            'due_at' => now()->subMinute(),
        ]);

        $before = now();
        $result = app(StudyRecallSchedulerService::class)
            ->review($item, 'again', (string) Str::uuid());

        $this->assertSame(0, $result['item']->repetitions);
        $this->assertSame(0, $result['item']->interval_days);
        $this->assertSame(1, $result['item']->lapse_count);
        $this->assertTrue($result['item']->due_at->between(
            $before->copy()->addMinutes(9),
            $before->copy()->addMinutes(11),
        ));
    }

    public function test_review_request_is_idempotent_and_records_one_evidence(): void
    {
        [$user, $plan, $task] = $this->scenario();
        $item = $this->item($plan, $task);
        $requestId = (string) Str::uuid();

        $payload = [
            'rating' => 'good',
            'review_request_id' => $requestId,
        ];

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.items.review', [$plan, $task, $item]), $payload)
            ->assertRedirect(route('plans.tasks.study_recall.show', [$plan, $task]));

        $afterFirst = $item->fresh();
        $this->assertSame(1, $afterFirst->repetitions);
        $this->assertSame(1, $afterFirst->interval_days);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.items.review', [$plan, $task, $item]), $payload)
            ->assertRedirect(route('plans.tasks.study_recall.show', [$plan, $task]));

        $afterRetry = $item->fresh();
        $this->assertSame(1, $afterRetry->repetitions);
        $this->assertSame(1, $afterRetry->interval_days);
        $this->assertDatabaseCount('study_recall_reviews', 1);
        $this->assertDatabaseCount('task_evidences', 1);
        $this->assertDatabaseHas('task_evidences', [
            'task_id' => $task->id,
            'type' => 'study_recall_reviewed',
        ]);
    }

    public function test_recall_page_shows_due_card_and_answer_is_inside_disclosure(): void
    {
        [$user, $plan, $task] = $this->scenario();
        $this->item($plan, $task, [
            'prompt' => 'maintain',
            'answer' => '維持する',
        ]);

        $response = $this->actingAs($user)
            ->get(route('plans.tasks.study_recall.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('思い出す練習')
            ->assertSee('maintain')
            ->assertSee('答えを表示')
            ->assertSee('思い出せた');

        $html = $response->getContent();
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('維持する', $html);
    }

    public function test_future_due_card_is_not_shown_as_current(): void
    {
        [$user, $plan, $task] = $this->scenario();
        $this->item($plan, $task, [
            'prompt' => 'accurate',
            'answer' => '正確な',
            'repetitions' => 2,
            'interval_days' => 3,
            'due_at' => now()->addDays(3),
        ]);

        $this->actingAs($user)
            ->get(route('plans.tasks.study_recall.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('今すぐ確認するカードはありません');
    }

    public function test_toeic_activity_page_links_recall_primary_action(): void
    {
        [$user, $plan, $task] = $this->scenario();

        $this->actingAs($user)
            ->get(route('plans.tasks.study_activity.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('Recallを始める')
            ->assertSee(route('plans.tasks.study_recall.show', [$plan, $task]), false);
    }

    public function test_card_from_another_task_cannot_be_reviewed(): void
    {
        [$user, $plan, $task] = $this->scenario();
        $other = Task::create([
            'plan_id' => $plan->id,
            'title' => '別Task',
            'description' => '別の語彙',
            'estimated_minutes' => 30,
            'remaining_minutes' => 30,
            'progress_percent' => 0,
            'status' => 'todo',
            'priority' => 2,
            'activation_cost' => 2,
            'sort_order' => 2,
        ]);
        $foreignItem = $this->item($plan, $other);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.items.review', [$plan, $task, $foreignItem]), [
                'rating' => 'good',
                'review_request_id' => (string) Str::uuid(),
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('study_recall_reviews', 0);
    }

    private function item(Plan $plan, Task $task, array $overrides = []): StudyRecallItem
    {
        $prompt = $overrides['prompt'] ?? 'abandon';
        $answer = $overrides['answer'] ?? '放棄する';

        return StudyRecallItem::create(array_merge([
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'prompt' => $prompt,
            'answer' => $answer,
            'tags' => [],
            'fingerprint' => hash('sha256', mb_strtolower($prompt).'|'.mb_strtolower($answer)),
            'repetitions' => 0,
            'lapse_count' => 0,
            'interval_days' => 0,
            'ease_factor' => 2.50,
            'due_at' => null,
            'is_active' => true,
        ], $overrides));
    }

    private function scenario(): array
    {
        $user = User::factory()->create();

        $plan = Plan::create([
            'user_id' => $user->id,
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => 'TOEIC 800点',
            'description' => 'TOEIC語彙と読解を学ぶ',
            'category' => '資格学習',
            'start_date' => today(),
            'deadline' => today()->addMonths(2),
            'is_public' => false,
        ]);

        $task = Task::create([
            'plan_id' => $plan->id,
            'title' => 'TOEIC英単語を暗記する',
            'description' => '頻出語彙を単語帳で覚える',
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
