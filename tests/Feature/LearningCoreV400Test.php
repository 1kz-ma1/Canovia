<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanResource;
use App\Models\Question;
use App\Models\QuestionPack;
use App\Models\Task;
use App\Services\PlanPriorityService;
use App\Services\PlanToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LearningCoreV400Test extends TestCase
{
    use RefreshDatabase;

    public function test_auto_priority_uses_objective_plan_state_and_manual_mode_overrides_it(): void
    {
        $urgent = $this->plan('期限直前', 5, 'auto', 1);
        $this->task($urgent, '進める', 120);

        $relaxed = $this->plan('余裕あり', 1, 'auto', 90);
        $this->task($relaxed, '進める', 120);

        $service = app(PlanPriorityService::class);
        $urgentEvaluation = $service->evaluate($urgent);
        $relaxedEvaluation = $service->evaluate($relaxed);

        $this->assertLessThan(
            $relaxedEvaluation['priority'],
            $urgentEvaluation['priority'],
            '期限直前のPlanは余裕のあるPlanより高い自動優先度になるべきです。'
        );

        $urgent->update(['priority_mode' => 'manual', 'priority' => 5]);
        $manual = $service->evaluate($urgent->fresh());

        $this->assertSame('manual', $manual['mode']);
        $this->assertSame(5, $manual['priority']);
        $this->assertNotSame(5, $manual['auto_priority']);
    }

    public function test_resource_tool_does_not_double_count_task_resources_that_are_already_in_plan_resources(): void
    {
        $plan = $this->plan('資料確認', 3, 'manual', 30);
        $task = $this->task($plan, '資料を読む', 60);

        $resources = collect(range(1, 5))->map(fn ($index) => PlanResource::create([
            'plan_id' => $plan->id,
            'provider' => 'google_drive',
            'resource_type' => 'file',
            'title' => "資料{$index}",
            'url' => "https://example.com/{$index}",
        ]));

        $task->resources()->attach($resources->take(2)->pluck('id')->all());

        $plan->load(['resources', 'tasks.resources', 'tasks.artifacts']);
        $task = $plan->tasks->first();
        $tool = collect(app(PlanToolService::class)->forTask($plan, $task, true))
            ->firstWhere('id', 'resources');

        $this->assertSame('Task 2件', $tool['badge']);
        $this->assertStringContainsString('Taskに関連 2 件 / Plan全体 5 件', $tool['description']);
        $this->assertStringNotContainsString('7 件', $tool['description']);
    }

    public function test_question_bank_foundation_keeps_source_response_grading_and_learning_metadata_separate(): void
    {
        $pack = QuestionPack::create([
            'slug' => 'ap-a-foundation',
            'title' => '応用情報 科目A',
            'exam_code' => 'AP',
            'subject' => '科目A',
            'version' => '1',
            'status' => 'draft',
            'downloadable' => true,
            'metadata' => ['locale' => 'ja-JP'],
        ]);

        $question = Question::create([
            'question_pack_id' => $pack->id,
            'external_key' => 'sample-001',
            'source_type' => 'official',
            'source_reference' => '出典情報',
            'prompt' => 'サンプル問題',
            'response_schema' => [
                ['id' => 'answer', 'type' => 'single_choice'],
                ['id' => 'reasoning', 'type' => 'textarea'],
            ],
            'grading_rule' => ['type' => 'exact_choice', 'answer' => 'A'],
            'learning_metadata' => ['concepts' => ['network'], 'weakness_targets' => ['MTU']],
            'difficulty' => 3,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertSame($pack->id, $question->pack->id);
        $this->assertSame('official', $question->source_type);
        $this->assertSame('textarea', $question->response_schema[1]['type']);
        $this->assertSame('A', $question->grading_rule['answer']);
        $this->assertSame(['MTU'], $question->learning_metadata['weakness_targets']);
        $this->assertTrue($pack->downloadable);
    }

    private function plan(string $title, int $priority, string $mode, int $deadlineDays): Plan
    {
        return Plan::create([
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => $title,
            'category' => '資格学習',
            'priority' => $priority,
            'priority_mode' => $mode,
            'start_date' => today(),
            'deadline' => today()->addDays($deadlineDays),
            'is_public' => false,
        ]);
    }

    private function task(Plan $plan, string $title, int $minutes): Task
    {
        return Task::create([
            'plan_id' => $plan->id,
            'title' => $title,
            'description' => 'V40 test',
            'estimated_minutes' => $minutes,
            'remaining_minutes' => $minutes,
            'progress_percent' => 0,
            'status' => 'todo',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 1,
        ]);
    }
}
