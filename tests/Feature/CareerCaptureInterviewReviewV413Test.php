<?php

namespace Tests\Feature;

use App\Models\CareerApplication;
use App\Models\CareerCapture;
use App\Models\CareerSelectionEvent;
use App\Models\InterviewReview;
use App\Models\InterviewReviewAnswer;
use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Services\InterviewReviewQuestionService;
use App\Services\PlanCategoryProfileService;
use App\Services\PlanSituationResolver;
use App\Services\PlanSurfaceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class CareerCaptureInterviewReviewV413Test extends TestCase
{
    use RefreshDatabase;

    public function test_screenshot_capture_needs_no_company_form_and_is_kept_private(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);
        $response = $this->actingAs($user)->post(route('plans.career.captures.store', $plan), [
            'source_type' => 'screenshot',
            'screenshot' => $this->pngUpload('application.png'),
        ]);

        $response->assertRedirect(route('plans.career.index', $plan));

        $capture = CareerCapture::firstOrFail();

        $this->assertSame('screenshot', $capture->source_type);
        $this->assertSame('pending', $capture->status);
        $this->assertNull($capture->career_application_id);
        $this->assertDatabaseCount('career_applications', 0);
        $this->assertDatabaseHas('career_capture_payloads', [
            'career_capture_id' => $capture->id,
        ]);
        $this->assertNull($capture->screenshot_path);

        $this->actingAs($user)
            ->get(route('plans.career.captures.screenshot', [$plan, $capture]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_url_capture_can_be_saved_before_company_is_known(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);

        $this->actingAs($user)
            ->post(route('plans.career.captures.store', $plan), [
                'source_type' => 'url',
                'source_url' => 'https://example.com/jobs/backend-engineer',
            ])
            ->assertRedirect(route('plans.career.index', $plan));

        $this->assertDatabaseHas('career_captures', [
            'plan_id' => $plan->id,
            'source_type' => 'url',
            'status' => 'pending',
            'source_url' => 'https://example.com/jobs/backend-engineer',
        ]);
        $this->assertDatabaseCount('career_applications', 0);
    }

    public function test_capture_can_be_linked_when_an_application_becomes_known(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);
        $capture = CareerCapture::create([
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'source_type' => 'url',
            'status' => 'pending',
            'source_url' => 'https://example.com/job',
            'captured_at' => now(),
        ]);
        $application = CareerApplication::create([
            'plan_id' => $plan->id,
            'company_name' => 'Example株式会社',
            'role_title' => 'Webエンジニア',
            'stage' => 'candidate',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->post(route('plans.career.captures.link', [$plan, $capture]), [
                'career_application_id' => $application->id,
            ])
            ->assertRedirect(route('plans.career.index', $plan));

        $capture->refresh();
        $this->assertSame('linked', $capture->status);
        $this->assertSame($application->id, $capture->career_application_id);
    }

    public function test_real_applications_replace_task_inference_in_career_pipeline(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);
        CareerApplication::create([
            'plan_id' => $plan->id,
            'company_name' => 'A社',
            'stage' => 'screening',
            'status' => 'active',
        ]);
        CareerApplication::create([
            'plan_id' => $plan->id,
            'company_name' => 'B社',
            'stage' => 'interview',
            'status' => 'active',
        ]);
        $this->task($plan, '企業研究をする');

        [$situation, $modules] = $this->careerSurfaceState($plan);

        $this->assertSame('applications', $situation['career_pipeline_source']);
        $this->assertSame(2, $situation['career_application_count']);
        $this->assertSame(1, data_get(collect($situation['career_pipeline'])->firstWhere('key', 'application'), 'total'));
        $this->assertSame(1, data_get(collect($situation['career_pipeline'])->firstWhere('key', 'interview'), 'total'));

        $pipeline = $modules->firstWhere('id', 'career_pipeline');
        $this->assertNotNull($pipeline);
        $this->assertSame('applications', $pipeline->payload['source']);
    }

    public function test_future_interview_shows_prep_and_elapsed_interview_switches_to_review(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);
        $application = CareerApplication::create([
            'plan_id' => $plan->id,
            'company_name' => 'A社',
            'stage' => 'interview',
            'status' => 'active',
        ]);
        $event = CareerSelectionEvent::create([
            'career_application_id' => $application->id,
            'type' => 'interview',
            'stage' => 'interview',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);

        [$futureSituation, $futureModules] = $this->careerSurfaceState($plan);
        $this->assertSame($event->id, $futureSituation['career_next_interview_event']->id);
        $this->assertContains('career_interview_prep', $futureModules->pluck('id')->all());
        $this->assertNotContains('career_interview_review', $futureModules->pluck('id')->all());

        $event->update(['scheduled_at' => now()->subMinutes(30)]);

        [$pastSituation, $pastModules] = $this->careerSurfaceState($plan->fresh());
        $this->assertTrue($pastSituation['career_review_due']);
        $this->assertSame($event->id, $pastSituation['career_review_due_event']->id);
        $this->assertContains('career_interview_review', $pastModules->pluck('id')->all());
        $this->assertNotContains('career_interview_prep', $pastModules->pluck('id')->all());
    }

    public function test_review_questions_use_previous_interview_learning(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);

        $firstApplication = CareerApplication::create([
            'plan_id' => $plan->id,
            'company_name' => 'A社',
            'stage' => 'interview',
            'status' => 'waiting',
        ]);
        $firstEvent = CareerSelectionEvent::create([
            'career_application_id' => $firstApplication->id,
            'type' => 'interview',
            'stage' => 'interview',
            'status' => 'result_waiting',
            'scheduled_at' => now()->subDays(3),
            'completed_at' => now()->subDays(3),
        ]);
        $review = InterviewReview::create([
            'plan_id' => $plan->id,
            'career_application_id' => $firstApplication->id,
            'career_selection_event_id' => $firstEvent->id,
            'status' => InterviewReview::STATUS_COMPLETED,
            'completed_at' => now()->subDays(3),
        ]);
        InterviewReviewAnswer::create([
            'interview_review_id' => $review->id,
            'question_key' => 'next_focus',
            'prompt' => '次回は？',
            'answer' => '志望動機を企業固有の事業と結び付ける',
            'source' => 'rule',
            'sort_order' => 1,
        ]);

        $secondApplication = CareerApplication::create([
            'plan_id' => $plan->id,
            'company_name' => 'B社',
            'stage' => 'interview',
            'status' => 'active',
        ]);
        $secondEvent = CareerSelectionEvent::create([
            'career_application_id' => $secondApplication->id,
            'type' => 'interview',
            'stage' => 'interview',
            'status' => 'scheduled',
            'scheduled_at' => now()->subHour(),
        ]);

        $questions = collect(app(InterviewReviewQuestionService::class)->questions($secondEvent));

        $this->assertSame('previous_focus', $questions->first()['key']);
        $this->assertStringContainsString('志望動機を企業固有の事業と結び付ける', $questions->first()['prompt']);
    }

    public function test_completing_interview_review_creates_learning_evidence_without_changing_task_progress(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);
        $task = $this->task($plan, 'A社 一次面接対策', progress: 45);
        $application = CareerApplication::create([
            'plan_id' => $plan->id,
            'company_name' => 'A社',
            'role_title' => 'Webエンジニア',
            'stage' => 'interview',
            'status' => 'active',
        ]);
        $event = CareerSelectionEvent::create([
            'career_application_id' => $application->id,
            'task_id' => $task->id,
            'type' => 'interview',
            'stage' => 'interview',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($user)
            ->post(route('plans.career.interview_reviews.store', [$plan, $event]), [
                'action' => 'complete',
                'answers' => [
                    'best_moment' => '開発経験を具体例で話せた',
                    'difficult_moment' => '志望動機の深掘り',
                    'redo_answer' => '事業との接点から答える',
                    'company_impression' => '開発文化への興味が上がった',
                    'next_focus' => '企業固有の志望理由を一段具体化する',
                ],
            ])
            ->assertRedirect(route('plans.career.interview_reviews.show', [$plan, $event]));

        $review = InterviewReview::firstOrFail();

        $this->assertSame(InterviewReview::STATUS_COMPLETED, $review->status);
        $this->assertSame('result_waiting', $event->fresh()->status);
        $this->assertSame('waiting', $application->fresh()->status);
        $this->assertSame(45, $task->fresh()->progress_percent);

        $this->assertDatabaseHas('task_evidences', [
            'task_id' => $task->id,
            'type' => 'interview_review_completed',
            'external_key' => 'interview-review:'.$review->id.':completed',
        ]);

        $this->actingAs($user)
            ->post(route('plans.career.interview_reviews.store', [$plan, $event]), [
                'action' => 'save',
                'answers' => [
                    'next_focus' => 'さらに具体化する',
                ],
            ])
            ->assertRedirect(route('plans.career.interview_reviews.show', [$plan, $event]));

        $this->assertSame(InterviewReview::STATUS_COMPLETED, $review->fresh()->status);
    }

    public function test_result_recording_closes_waiting_surface_and_keeps_result_as_evidence(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user);
        $task = $this->task($plan, 'A社 面接', progress: 50);
        $application = CareerApplication::create([
            'plan_id' => $plan->id,
            'company_name' => 'A社',
            'stage' => 'interview',
            'status' => 'waiting',
        ]);
        $event = CareerSelectionEvent::create([
            'career_application_id' => $application->id,
            'task_id' => $task->id,
            'type' => 'interview',
            'stage' => 'interview',
            'status' => 'result_waiting',
            'scheduled_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->patch(route('plans.career.events.result', [$plan, $event]), [
                'result' => 'rejected',
            ])
            ->assertRedirect(route('plans.career.index', $plan));

        $this->assertSame('completed', $event->fresh()->status);
        $this->assertSame('rejected', $event->fresh()->result);
        $this->assertSame('closed', $application->fresh()->stage);
        $this->assertSame('completed', $application->fresh()->status);
        $this->assertSame(50, $task->fresh()->progress_percent);
        $this->assertDatabaseHas('task_evidences', [
            'task_id' => $task->id,
            'type' => 'interview_result_recorded',
            'external_key' => 'career-selection-event:'.$event->id.':result',
        ]);

        [$situation, $modules] = $this->careerSurfaceState($plan->fresh());
        $this->assertSame(0, $situation['career_result_waiting_count']);
        $this->assertNotContains('career_result_waiting', $modules->pluck('id')->all());
    }

    public function test_non_career_plan_cannot_open_career_workspace(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user, '資格学習');

        $this->actingAs($user)
            ->get(route('plans.career.index', $plan))
            ->assertNotFound();
    }

    private function pngUpload(string $name): UploadedFile
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=',
            true,
        );
        $path = tempnam(sys_get_temp_dir(), 'career-capture-');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function careerSurfaceState(Plan $plan): array
    {
        $plan->load([
            'tasks',
            'resources',
            'artifacts',
            'careerApplications.selectionEvents.interviewReview',
            'careerCaptures',
        ]);

        $profile = app(PlanCategoryProfileService::class)->forPlan($plan);
        $currentTask = $plan->tasks
            ->first(fn ($task) => ! in_array($task->status, ['done', 'cancelled'], true) && (int) $task->progress_percent < 100);

        $situation = app(PlanSituationResolver::class)->resolve(
            $plan,
            $profile,
            $currentTask,
            collect(),
            collect(),
        );

        $modules = app(PlanSurfaceEngine::class)->build(
            $plan,
            $profile,
            $situation,
            $currentTask,
        );

        return [$situation, $modules];
    }

    private function plan(User $user, string $category = '就活・キャリア'): Plan
    {
        return Plan::create([
            'user_id' => $user->id,
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => 'エンジニア就活',
            'description' => '入力負担を減らしながら選考を進める',
            'category' => $category,
            'priority' => 1,
            'priority_mode' => 'manual',
            'start_date' => today(),
            'deadline' => today()->addMonths(3),
            'is_public' => false,
        ]);
    }

    private function task(Plan $plan, string $title, int $progress = 0): Task
    {
        return Task::create([
            'plan_id' => $plan->id,
            'title' => $title,
            'description' => 'Career Task',
            'estimated_minutes' => 60,
            'remaining_minutes' => $progress >= 100 ? 0 : 45,
            'progress_percent' => $progress,
            'status' => $progress > 0 ? 'doing' : 'todo',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 1,
        ]);
    }
}
