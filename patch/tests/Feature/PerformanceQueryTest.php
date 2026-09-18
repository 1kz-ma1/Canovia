<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_measure_core_pages_with_one_and_five_plans(): void
    {
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 9, 18)->setTime(12, 0));
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (range(1, 5) as $number) {
            $plan = Plan::create([
                'user_id' => $user->id, 'owner_token' => str_repeat('a', 64),
                'public_slug' => 'perf-'.$number, 'title' => 'Performance Plan '.$number,
                'start_date' => today()->subDays(7), 'deadline' => today()->addDays(30),
                'is_public' => false,
            ]);
            foreach (range(1, 3) as $task) {
                Task::create([
                    'plan_id' => $plan->id, 'title' => 'Task '.$task,
                    'estimated_minutes' => 60, 'remaining_minutes' => 60,
                    'progress_percent' => 0, 'status' => 'todo', 'priority' => 2,
                    'sort_order' => $task,
                ]);
            }
            if (! in_array($number, [1, 5])) continue;
            foreach (['/', '/roadmap', '/navigate'] as $path) {
                // SQLite stores the date cast with a time component. Isolate
                // page samples from the existing snapshot updateOrCreate issue.
                DB::table('user_state_snapshots')->delete();
                DB::flushQueryLog();
                DB::enableQueryLog();
                $response = $this->get($path);
                $queries = DB::getQueryLog();
                DB::disableQueryLog();
                $response->assertOk();
                $groups = [];
                foreach ($queries as $query) {
                    preg_match('/from ["`]?([a-z_]+)/i', $query['query'], $matches);
                    $key = $matches[1] ?? strtok($query['query'], ' ');
                    $groups[$key] = ($groups[$key] ?? 0) + 1;
                }
                fwrite(STDOUT, "\nPERF ".json_encode(['plans' => $number, 'path' => $path, 'queries' => count($queries), 'groups' => $groups])."\n");
                $this->assertSame(1, $groups['plan_availability_rules']);
                $this->assertSame(1, $groups['plan_availability_overrides']);
                if ($path === '/') $this->assertSame(1, $groups['plan_resources']);
                if ($path === '/navigate') $this->assertSame(3, $groups['work_sessions']);
            }
        }
    }

    public function test_roadmap_only_loads_detail_relations_for_selected_plan(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $plans = collect(range(1, 2))->map(fn ($i) => Plan::create([
            'user_id' => $user->id, 'owner_token' => str_repeat('a', 64),
            'public_slug' => 'detail-'.$i, 'title' => 'Detail '.$i,
            'start_date' => today(), 'deadline' => today()->addMonth(),
        ]));
        $response = $this->actingAs($user)->get('/roadmap?plan_id='.$plans->first()->id)->assertOk();
        foreach ($response->viewData('plans') as $plan) {
            $this->assertTrue($plan->relationLoaded('tasks'));
            $this->assertSame($plan->id === $plans->first()->id, $plan->relationLoaded('workLogs'));
        }
        $response->assertDontSee('rel="prefetch"', false);
    }

    public function test_optional_measurement_includes_database_session_and_no_private_values(): void
    {
        $this->withoutVite();
        config(['performance.enabled' => true, 'session.driver' => 'database', 'session.lottery' => [0, 100]]);
        $this->app->getProvider(\App\Providers\AppServiceProvider::class)->boot();
        \Illuminate\Support\Facades\Log::spy();
        $this->get('/navigate?_canovia_network=1')->assertOk();
        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')->once()->withArgs(function ($message, $data) {
            $this->assertSame('canovia.performance', $message);
            $this->assertTrue($data['network_retry']);
            $this->assertGreaterThanOrEqual(2, $data['session_query_count']);
            $this->assertGreaterThanOrEqual($data['session_query_count'], $data['query_count']);
            $this->assertArrayNotHasKey('sql', $data);
            $this->assertArrayNotHasKey('bindings', $data);
            $this->assertArrayNotHasKey('actor_token', $data);
            return true;
        });
    }
}
