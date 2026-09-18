<?php

namespace Tests\Feature;

use App\Models\Feedback;
use App\Models\FutureMemo;
use App\Models\Plan;
use App\Models\PlanMember;
use App\Models\ReleaseNote;
use App\Models\Task;
use App\Models\User;
use App\Services\BehaviorEventLogger;
use App\Services\FutureMemoService;
use App\Services\PlanOwnershipService;
use App\Support\ReleaseNotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionReliabilityV35Test extends TestCase
{
    use RefreshDatabase;

    private function taskFor(User $user): Task
    {
        $plan = Plan::create([
            'user_id' => $user->id, 'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(), 'title' => '互換性テスト',
            'start_date' => today(), 'deadline' => today()->addMonth(),
            'is_public' => false, 'is_collaborative' => true,
        ]);

        return Task::create([
            'plan_id' => $plan->id, 'title' => '作業', 'estimated_minutes' => 30,
            'remaining_minutes' => 30, 'progress_percent' => 0,
            'status' => 'todo', 'priority' => 1, 'activation_cost' => 1, 'sort_order' => 1,
        ]);
    }

    private function payload(Task $task): array
    {
        return [
            'client_session_id' => (string) Str::uuid(), 'task_id' => $task->id,
            'started_at' => now()->subMinutes(5)->toIso8601String(),
            'ended_at' => now()->toIso8601String(), 'actual_seconds' => 240,
        ];
    }

    public function test_sync_retries_create_one_session_log_and_event(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload($this->taskFor($user));
        $this->actingAs($user)->withSession(['pace_keeper.actor_token' => str_repeat('a', 64)]);
        $first = $this->postJson('/offline/work-sessions/sync', $payload)
            ->assertOk()->assertJsonPath('already_synced', false)->json('work_session_id');
        $this->postJson('/offline/work-sessions/sync', $payload)
            ->assertOk()->assertJsonPath('already_synced', true)->assertJsonPath('work_session_id', $first);
        $this->assertDatabaseCount('work_sessions', 1);
        $this->assertDatabaseCount('work_logs', 1);
        $this->assertDatabaseCount('behavior_events', 1);
    }

    public function test_sync_authorizes_before_returning_an_existing_record(): void
    {
        $owner = User::factory()->create();
        $payload = $this->payload($this->taskFor($owner));
        $this->actingAs($owner)->postJson('/offline/work-sessions/sync', $payload)->assertOk();
        $this->actingAs(User::factory()->create())
            ->postJson('/offline/work-sessions/sync', $payload)->assertForbidden();
    }

    public function test_client_id_cannot_be_reused_for_another_task_or_actor(): void
    {
        $owner = User::factory()->create();
        $payload = $this->payload($this->taskFor($owner));
        $this->actingAs($owner)->withSession(['pace_keeper.actor_token' => str_repeat('a', 64)])
            ->postJson('/offline/work-sessions/sync', $payload)->assertOk();
        $this->postJson('/offline/work-sessions/sync', array_replace($payload, ['task_id' => $this->taskFor($owner)->id]))
            ->assertStatus(409);
        $this->withSession(['pace_keeper.actor_token' => str_repeat('b', 64)])
            ->postJson('/offline/work-sessions/sync', $payload)->assertStatus(409);
        $this->assertDatabaseCount('work_logs', 1);
    }

    public function test_viewer_cannot_sync_but_editor_can(): void
    {
        $task = $this->taskFor(User::factory()->create());
        $member = User::factory()->create();
        $membership = PlanMember::create(['plan_id' => $task->plan_id, 'user_id' => $member->id, 'role' => 'viewer']);
        $payload = $this->payload($task);
        $this->actingAs($member)->postJson('/offline/work-sessions/sync', $payload)->assertForbidden();
        $membership->update(['role' => 'editor']);
        $this->postJson('/offline/work-sessions/sync', $payload)->assertOk();
    }

    public function test_invalid_sync_is_json_and_does_not_redirect_to_html(): void
    {
        $this->postJson('/offline/work-sessions/sync', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['client_session_id', 'task_id']);
    }

    public function test_event_failure_rolls_back_session_and_log(): void
    {
        $user = User::factory()->create();
        $payload = $this->payload($this->taskFor($user));
        $this->mock(BehaviorEventLogger::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('test failure'));
        });
        $this->actingAs($user)->postJson('/offline/work-sessions/sync', $payload)->assertStatus(500);
        $this->assertDatabaseCount('work_sessions', 0);
        $this->assertDatabaseCount('work_logs', 0);
    }

    public function test_loaded_memberships_avoid_repeated_role_queries(): void
    {
        $task = $this->taskFor(User::factory()->create());
        $member = User::factory()->create();
        PlanMember::create(['plan_id' => $task->plan_id, 'user_id' => $member->id, 'role' => 'viewer']);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $member);
        $service = app(PlanOwnershipService::class);
        $plans = $service->ownedPlans($request);
        $this->assertCount(1, $plans);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertTrue($service->canView($request, $plans->first()));
        $this->assertFalse($service->canEdit($request, $plans->first()));
        $this->assertSame('viewer', $service->role($request, $plans->first()));
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_manifest_remains_dynamic_private_and_canovia_branded(): void
    {
        $this->actingAs(User::factory()->create());
        $first = $this->get('/app.webmanifest')->assertOk()->assertJsonPath('name', 'Canovia');
        $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));
        $this->assertStringStartsWith('/pwa/handoff/', $first->json('start_url'));
        $second = $this->get('/app.webmanifest')->assertOk();
        $this->assertNotSame($first->json('start_url'), $second->json('start_url'));
        $this->assertDatabaseCount('pwa_handoffs', 2);
    }

    public function test_guest_memo_claim_and_ai_opt_out_remain_intact(): void
    {
        $token = str_repeat('m', 64);
        $user = User::factory()->create();
        foreach ([true, false] as $enabled) {
            FutureMemo::create(['guest_token_hash' => hash('sha256', $token), 'kind' => 'want_to_do',
                'category' => 'study', 'content' => $enabled ? '共有する希望' : '非公開の希望', 'use_for_ai' => $enabled]);
        }
        $request = Request::create('/', 'GET', [], [FutureMemoService::GUEST_COOKIE => $token]);
        $service = app(FutureMemoService::class);
        $this->assertSame(2, $service->claimGuestMemos($request, $user));
        $request->setUserResolver(fn () => $user);
        $this->assertStringContainsString('共有する希望', $service->promptContext($request));
        $this->assertStringNotContainsString('非公開の希望', $service->promptContext($request));
    }

    public function test_feedback_and_published_release_notes_remain_available(): void
    {
        $this->post('/feedback', ['type' => 'request', 'rating' => 5, 'message' => '改善希望'])
            ->assertRedirect();
        $feedback = Feedback::firstOrFail();
        $note = ReleaseNote::create(['feedback_id' => $feedback->id, 'version' => 'test',
            'title' => '改善しました', 'summary' => '公開済み', 'highlights' => [], 'published_at' => now()->subMinute()]);
        ReleaseNote::create(['version' => 'draft', 'title' => '下書き', 'summary' => '未公開', 'highlights' => []]);
        $notes = ReleaseNotes::all();
        $this->assertTrue($notes->contains('title', '改善しました'));
        $this->assertFalse($notes->contains('title', '下書き'));
        $note->delete();
        $this->assertFalse(ReleaseNotes::all()->contains('title', '改善しました'));
    }

    public function test_legacy_readiness_headers_remain_available(): void
    {
        $this->get('/health')->assertNoContent()
            ->assertHeader('X-Canovia-Ready', '1')->assertHeader('X-PaceKeeper-Ready', '1');
    }

    public function test_legacy_guest_ownership_cookie_still_allows_offline_sync(): void
    {
        $task = $this->taskFor(User::factory()->create());
        $plan = $task->plan;
        $plan->update(['user_id' => null]);
        $this->withCookie('pace_keeper_owner_token_'.$plan->id, $plan->owner_token)
            ->withCredentials()
            ->postJson('/offline/work-sessions/sync', $this->payload($task))->assertOk();
    }

    public function test_legacy_host_navigation_still_uses_one_time_brand_handoff(): void
    {
        $this->app['env'] = 'production';
        config(['canovia.redirect_legacy_hosts' => true, 'canovia.canonical_url' => 'https://app.canovia.example',
            'canovia.legacy_hosts' => ['legacy.example']]);
        $response = $this->withHeaders(['Accept' => 'text/html', 'Sec-Fetch-Dest' => 'document'])
            ->get('http://legacy.example/?test=1')->assertStatus(302);
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://app.canovia.example/pwa/handoff/', $location);
        $this->assertStringContainsString('brand_migration=1', $location);
        $this->assertStringContainsString('next=%2F%3Ftest%3D1', $location);
        $this->assertDatabaseCount('pwa_handoffs', 1);
    }
}
