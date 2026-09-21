<?php

namespace Tests\Feature;

use App\Data\UserStateData;
use App\Enums\UserBehaviorState;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Services\DashboardGuidanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardGuidanceV395Test extends TestCase
{
    use RefreshDatabase;

    public function test_plan_priority_controls_first_card_even_when_lower_priority_plan_has_closer_deadline(): void
    {
        $primary = $this->plan('APを取得する', 1, 30, '資格学習');
        $primaryTask = $this->task($primary, 'ネットワーク演習', 3, 'todo', 1);

        $urgent = $this->plan('締切直前の別計画', 4, 1);
        $this->task($urgent, '緊急タスク', 1, 'doing', 1);

        $deck = $this->deck([$urgent, $primary]);

        $this->assertSame($primary->id, $deck->first()['plan']->id);
        $this->assertSame($primaryTask->id, $deck->first()['task']->id);
    }

    public function test_task_selection_is_priority_then_doing_then_sort_order_and_skips_blocked_task(): void
    {
        $plan = $this->plan('選定テスト', 1, 20);
        $prerequisite = $this->task($plan, '前提', 5, 'todo', 1);
        $this->task($plan, 'ブロック中の最優先', 1, 'todo', 2, dependsOn: $prerequisite->id);
        $todo = $this->task($plan, '未着手', 2, 'todo', 1);
        $doing = $this->task($plan, '進行中', 2, 'doing', 9);

        $plan->load('tasks');
        $deck = $this->deck([$plan]);

        $this->assertSame($doing->id, $deck->first()['task']->id);
        $this->assertNotSame($todo->id, $deck->first()['task']->id);
    }

    public function test_adaptive_guidance_is_pinned_to_the_objectively_selected_task(): void
    {
        $plan = $this->plan('固定選定', 1, 20);
        $objective = $this->task($plan, '重いが最優先', 1, 'todo', 1, 120, 5);
        $this->task($plan, '短く始めやすい', 5, 'todo', 2, 10, 1);

        $deck = $this->deck([$plan], UserBehaviorState::LowReadiness);
        $first = $deck->first();

        $this->assertSame($objective->id, $first['task']->id);
        $this->assertSame($objective->id, $first['adaptive']?->task->id);
    }

    public function test_existing_plan_update_without_priority_preserves_current_priority(): void
    {
        $user = User::factory()->create();
        $plan = Plan::create([
            'user_id' => $user->id,
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => '互換更新',
            'category' => '資格学習',
            'priority' => 1,
            'start_date' => today(),
            'deadline' => today()->addMonth(),
            'is_public' => false,
        ]);

        $this->actingAs($user)
            ->put(route('plans.update', $plan), ['title' => '互換更新後'])
            ->assertRedirect(route('plans.show', $plan));

        $this->assertSame(1, (int) $plan->fresh()->priority);
    }

    public function test_plan_without_explicit_priority_uses_default_three(): void
    {
        $plan = Plan::create([
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => '既存経路',
            'category' => 'その他',
            'start_date' => today(),
            'deadline' => today()->addMonth(),
            'is_public' => false,
        ]);

        $this->assertSame(3, (int) $plan->fresh()->priority);
    }

    public function test_study_task_surfaces_ai_practice_as_the_specialized_tool(): void
    {
        $plan = $this->plan('応用情報技術者試験', 1, 30, '資格学習');
        $task = $this->task($plan, 'ネットワーク分野の演習', 1, 'doing', 1);

        $deck = $this->deck([$plan]);
        $first = $deck->first();

        $this->assertSame($task->id, $first['task']->id);
        $this->assertSame('ai_practice', $first['recommended_tool']['id'] ?? null);
    }

    private function deck(array $plans, UserBehaviorState $behavior = UserBehaviorState::Normal)
    {
        $collection = collect($plans);

        foreach ($collection as $plan) {
            $plan->loadMissing(['tasks', 'workLogs', 'resources']);
        }

        return app(DashboardGuidanceService::class)->build(
            $collection,
            new UserStateData(50, 50, 50, 50, $behavior, [], 0.8),
            'test-actor',
            $collection->pluck('id')->all(),
        );
    }

    private function plan(string $title, int $priority, int $deadlineDays, string $category = 'その他'): Plan
    {
        return Plan::create([
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => $title,
            'category' => $category,
            'priority' => $priority,
            'start_date' => today(),
            'deadline' => today()->addDays($deadlineDays),
            'is_public' => false,
        ]);
    }

    private function task(
        Plan $plan,
        string $title,
        int $priority,
        string $status,
        int $sortOrder,
        int $minutes = 60,
        int $activationCost = 2,
        ?int $dependsOn = null,
    ): Task {
        return Task::create([
            'plan_id' => $plan->id,
            'depends_on_task_id' => $dependsOn,
            'title' => $title,
            'description' => '理解と実行を進める',
            'estimated_minutes' => $minutes,
            'remaining_minutes' => $minutes,
            'progress_percent' => $status === 'doing' ? 20 : 0,
            'status' => $status,
            'priority' => $priority,
            'activation_cost' => $activationCost,
            'sort_order' => $sortOrder,
        ]);
    }
}
