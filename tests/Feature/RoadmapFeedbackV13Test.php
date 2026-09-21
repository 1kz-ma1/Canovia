<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkSession;
use App\Services\RoadmapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoadmapFeedbackV13Test extends TestCase
{
    use RefreshDatabase;

    public function test_roadmap_uses_execution_order_instead_of_registration_order(): void
    {
        $plan = $this->createPlan();

        $lowPriorityRoot = Task::create([
            'plan_id' => $plan->id,
            'title' => '後でやる低優先Task',
            'estimated_minutes' => 60,
            'remaining_minutes' => 60,
            'progress_percent' => 0,
            'status' => 'todo',
            'priority' => 5,
            'activation_cost' => 3,
            'sort_order' => 1,
        ]);

        $highPriorityRoot = Task::create([
            'plan_id' => $plan->id,
            'title' => '先にやる高優先Task',
            'estimated_minutes' => 60,
            'remaining_minutes' => 60,
            'progress_percent' => 0,
            'status' => 'todo',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 4,
        ]);

        $dependent = Task::create([
            'plan_id' => $plan->id,
            'depends_on_task_id' => $highPriorityRoot->id,
            'title' => '高優先Taskの次',
            'estimated_minutes' => 30,
            'remaining_minutes' => 30,
            'progress_percent' => 0,
            'status' => 'todo',
            'priority' => 1,
            'activation_cost' => 1,
            'sort_order' => 2,
        ]);

        $completed = Task::create([
            'plan_id' => $plan->id,
            'title' => '完了済みTask',
            'estimated_minutes' => 30,
            'remaining_minutes' => 0,
            'progress_percent' => 100,
            'status' => 'done',
            'priority' => 5,
            'activation_cost' => 5,
            'sort_order' => 99,
        ]);

        $roadmap = app(RoadmapService::class)->build($plan->fresh('tasks'));
        $ids = collect($roadmap['nodes'])->pluck('task_id')->all();

        $this->assertSame([
            $completed->id,
            $highPriorityRoot->id,
            $dependent->id,
            $lowPriorityRoot->id,
        ], $ids);
        $this->assertSame($highPriorityRoot->id, $roadmap['current']['task_id']);
    }

    public function test_starting_from_roadmap_redirects_directly_to_active_timer(): void
    {
        $user = User::factory()->create();
        $plan = $this->createPlan($user);
        $task = Task::create([
            'plan_id' => $plan->id,
            'title' => 'Roadmapから開始',
            'estimated_minutes' => 45,
            'remaining_minutes' => 45,
            'progress_percent' => 0,
            'status' => 'todo',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('work_sessions.start'), [
            'task_id' => $task->id,
            'source' => 'roadmap',
            'intended_minutes' => 25,
        ]);

        $session = WorkSession::where('task_id', $task->id)->firstOrFail();
        $response->assertRedirect(route('work_sessions.active', $session));
        $this->assertSame('active', $session->status);
        $this->assertSame('roadmap', $session->source);
    }

    public function test_feedback_can_store_five_star_rating_and_app_version(): void
    {
        $user = User::factory()->create();
        config(['pacekeeper.version' => 'v13-test']);

        $this->actingAs($user)->post(route('feedback.store'), [
            'type' => 'positive',
            'rating' => 5,
            'message' => 'ロードマップが使いやすいです。',
            'page' => '/roadmap',
        ])->assertRedirect();

        $feedback = Feedback::firstOrFail();
        $this->assertSame(5, $feedback->rating);
        $this->assertSame('v13-test', $feedback->app_version);
        $this->assertSame($user->id, $feedback->user_id);
    }

    public function test_canovia_admin_login_opens_a_hub_for_feedback_and_telemetry(): void
    {
        config(['canovia.admin_password' => 'test-secret']);

        $this->get(route('admin.feedback.index'))
            ->assertRedirect(route('admin.login'));

        $this->get(route('admin.feedback.login'))
            ->assertRedirect(route('admin.login'));

        $this->post(route('admin.authenticate'), [
            'password' => 'test-secret',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('フィードバック管理')
            ->assertSee('計画作成・更新の診断');

        $this->get(route('admin.feedback.index'))
            ->assertOk()
            ->assertSee('概要');

        $this->get(route('admin.telemetry.index'))
            ->assertOk()
            ->assertSee('概要');
    }

    private function createPlan(?User $user = null): Plan
    {
        return Plan::create([
            'user_id' => $user?->id,
            'owner_token' => Str::random(64),
            'public_slug' => Str::uuid()->toString(),
            'title' => 'v13 test plan',
            'start_date' => today()->toDateString(),
            'deadline' => today()->addMonth()->toDateString(),
            'is_public' => false,
        ]);
    }
}
