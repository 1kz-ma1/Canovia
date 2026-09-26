<?php

namespace Tests\Feature;

use App\Enums\ProductKey;
use App\Models\NativeAiRun;
use App\Models\Plan;
use App\Models\PracticeQuestionDemand;
use App\Models\Question;
use App\Models\QuestionPack;
use App\Models\StudyPracticeSession;
use App\Models\Task;
use App\Models\User;
use App\Models\UserProductGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class HybridQuestionAssemblyV4182Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'canovia.super_admin_user_id' => null,
            'canovia.admin_email' => null,
            'native_ai.driver' => 'openai',
            'native_ai.providers.openai.base_url' => 'https://api.openai.com/v1',
            'native_ai.providers.openai.api_key' => 'test-key',
            'native_ai.providers.openai.model' => 'gpt-6-luna',
            'native_ai.study_practice.capacity.standard.generation_max_output_tokens' => 8000,
            'native_ai.study_practice.capacity.standard.assessment_max_output_tokens' => 6000,
        ]);
    }

    public function test_hybrid_uses_bank_first_generates_only_gap_and_records_demand(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $this->grantPremium($user);
        $pack = $this->seedBank(3);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(
                $this->responseBody($this->questionEnvelope($plan, $task, 7), 'resp_gap'),
                200,
            ),
        ]);

        $prepareRequestId = (string) Str::uuid();

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.native.prepare', [$plan, $task]), [
                'prepare_request_id' => $prepareRequestId,
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '10問を準備しました（Question Bank 3問 + Native AI 7問）。');

        $session = StudyPracticeSession::where('prepare_request_id', $prepareRequestId)->firstOrFail();

        $this->assertSame('hybrid_ai', $session->question_provider);
        $this->assertSame('hybrid_question_assembly', $session->selector_type);
        $this->assertCount(10, $session->questions_snapshot);
        $this->assertSame(3, (int) data_get($session->provider_payload, 'source_mix.bank_selected_count'));
        $this->assertSame(7, (int) data_get($session->provider_payload, 'source_mix.native_requested_count'));
        $this->assertSame(7, (int) data_get($session->provider_payload, 'source_mix.native_generated_count'));

        $bankQuestions = collect($session->questions_snapshot)->filter(
            fn (array $question) => isset($question['source_question_id'])
        );
        $nativeQuestions = collect($session->questions_snapshot)->reject(
            fn (array $question) => isset($question['source_question_id'])
        );

        $this->assertCount(3, $bankQuestions);
        $this->assertCount(7, $nativeQuestions);

        $demand = PracticeQuestionDemand::where('prepare_request_id', $prepareRequestId)->firstOrFail();
        $this->assertSame('hybrid', $demand->assembly_mode);
        $this->assertSame('native_ai', $demand->generation_provider);
        $this->assertSame(10, $demand->requested_count);
        $this->assertSame(3, $demand->bank_selected_count);
        $this->assertSame(7, $demand->generated_requested_count);
        $this->assertSame(7, $demand->generated_count);
        $this->assertSame($pack->id, $demand->question_pack_id);
        $this->assertSame($plan->id, $demand->plan_id);
        $this->assertSame($task->id, $demand->task_id);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $input = (string) data_get($request->data(), 'input', '');

            return str_contains($input, '目安問題数: 7問')
                && str_contains($input, 'Question Bankですでに選定済みの問題')
                && str_contains($input, 'Bank問題 1');
        });

        $this->assertDatabaseCount('native_ai_runs', 1);
    }

    public function test_hybrid_bank_only_skips_native_generation_and_uses_deterministic_grader(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $this->grantPremium($user);
        $this->seedBank(10);

        Http::fake();

        $prepareRequestId = (string) Str::uuid();

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.native.prepare', [$plan, $task]), [
                'prepare_request_id' => $prepareRequestId,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '10問をQuestion Bankから準備しました。Native AI生成は不要でした。');

        $session = StudyPracticeSession::where('prepare_request_id', $prepareRequestId)->firstOrFail();
        $this->assertCount(10, $session->questions_snapshot);
        $this->assertSame(10, (int) data_get($session->provider_payload, 'source_mix.bank_selected_count'));
        $this->assertSame(0, (int) data_get($session->provider_payload, 'source_mix.native_requested_count'));
        $this->assertDatabaseCount('native_ai_runs', 0);

        $answers = [];
        foreach ($session->questions_snapshot as $question) {
            $answers[(string) $question['id']] = ['answer' => 'A'];
        }

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.answers', [$plan, $task]), [
                'answers' => $answers,
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame('question_bank_grader', $session->assessment_provider);
        $this->assertSame('direct', $session->assessment_provider_mode);
        $this->assertDatabaseCount('native_ai_runs', 0);
        Http::assertNothingSent();

        $demand = PracticeQuestionDemand::where('prepare_request_id', $prepareRequestId)->firstOrFail();
        $this->assertSame('bank_only', $demand->assembly_mode);
        $this->assertNull($demand->generation_provider);
        $this->assertSame(10, $demand->bank_selected_count);
        $this->assertSame(0, $demand->generated_count);
    }

    public function test_native_assessment_gets_bank_grading_context_without_exposing_answer_in_snapshot(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $this->grantPremium($user);
        $this->seedBank(9);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->responseBody($this->questionEnvelope($plan, $task, 1), 'resp_one_gap'), 200)
                ->push($this->responseBody($this->assessmentEnvelope($plan, $task, 10), 'resp_assessment'), 200),
        ]);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.native.prepare', [$plan, $task]), [
                'prepare_request_id' => (string) Str::uuid(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $session = StudyPracticeSession::latest('id')->firstOrFail();
        $bankQuestion = collect($session->questions_snapshot)->first(
            fn (array $question) => isset($question['source_question_id'])
        );

        $this->assertIsArray($bankQuestion);
        $this->assertArrayNotHasKey('grading_context', $bankQuestion);

        $answers = [];
        foreach ($session->questions_snapshot as $question) {
            $answers[(string) $question['id']] = ['answer' => 'A'];
        }

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.answers', [$plan, $task]), [
                'answers' => $answers,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertSentCount(2);
        $requests = Http::recorded();
        $assessmentRequest = $requests[1][0];
        $input = (string) data_get($assessmentRequest->data(), 'input', '');

        $this->assertStringContainsString('grading_context', $input);
        $this->assertStringContainsString('exact_choice', $input);
        $this->assertStringContainsString('\"answer\": \"A\"', $input);

        $session->refresh();
        $this->assertSame('native_ai', $session->assessment_provider);
        $this->assertCount(2, NativeAiRun::all());
    }

    private function seedBank(int $count): QuestionPack
    {
        $pack = QuestionPack::create([
            'slug' => 'ap-hybrid-test-'.$count,
            'title' => 'AP Hybrid Test',
            'exam_code' => 'AP',
            'subject' => '科目A',
            'version' => '1',
            'status' => 'published',
            'downloadable' => true,
            'metadata' => [
                'match_terms' => ['AP', '応用情報', '応用情報技術者試験'],
                'locale' => 'ja-JP',
            ],
        ]);

        foreach (range(1, $count) as $index) {
            Question::create([
                'question_pack_id' => $pack->id,
                'external_key' => 'bank-'.$index,
                'source_type' => 'canovia_original',
                'prompt' => "Bank問題 {$index}",
                'response_schema' => [[
                    'id' => 'answer',
                    'type' => 'single_choice',
                    'label' => '回答',
                    'required' => true,
                    'choices' => [
                        ['id' => 'A', 'label' => '正答'],
                        ['id' => 'B', 'label' => '誤答'],
                    ],
                ]],
                'grading_rule' => [
                    'type' => 'exact_choice',
                    'field_id' => 'answer',
                    'answer' => 'A',
                ],
                'learning_metadata' => [
                    'concepts' => ['横断診断'.$index],
                    'tags' => ['科目A'],
                ],
                'explanation' => "Bank問題 {$index} の解説",
                'difficulty' => 3,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        return $pack;
    }

    private function questionEnvelope(Plan $plan, Task $task, int $count): array
    {
        $questions = [];

        foreach (range(1, $count) as $index) {
            $questions[] = [
                'id' => 'native_'.$index,
                'prompt' => "Native補完問題 {$index}",
                'work_input' => 'none',
                'response_fields' => [[
                    'id' => 'answer',
                    'type' => 'single_choice',
                    'label' => '回答',
                    'required' => true,
                    'placeholder' => '',
                    'choices' => [
                        ['id' => 'A', 'label' => '正答候補'],
                        ['id' => 'B', 'label' => '誤答候補'],
                    ],
                ]],
            ];
        }

        return [
            'schema_version' => '1.0',
            'flow' => 'study_practice',
            'target_plan' => ['id' => $plan->id],
            'target_task' => ['id' => $task->id],
            'title' => 'Hybrid Native補完',
            'questions' => $questions,
        ];
    }

    private function assessmentEnvelope(Plan $plan, Task $task, int $count): array
    {
        $feedback = [];
        for ($index = 1; $index <= $count; $index++) {
            $feedback[] = [
                'question_id' => $index <= 9 ? 'bank_'.$index : 'native_1',
                'correctness' => 'correct',
                'feedback' => '正解です。',
                'reasoning_feedback' => '',
                'error_type' => 'none',
                'weakness_topics' => [],
                'misconceptions' => [],
            ];
        }

        return [
            'schema_version' => '1.0',
            'flow' => 'study_assessment',
            'target_plan' => ['id' => $plan->id],
            'target_task' => ['id' => $task->id],
            'score_percent' => 100,
            'question_feedback' => $feedback,
            'strengths' => ['理解できている'],
            'weaknesses' => [],
            'recommended_task_progress_percent' => 80,
            'evidence_summary' => 'Hybrid演習を完了した。',
            'next_action' => '次の範囲へ進む',
            'next_step' => [
                'kind' => 'continue_task',
                'label' => '次の範囲へ進む',
                'reason' => '理解できているため。',
                'focus_topics' => [],
                'question_count' => 0,
            ],
        ];
    }

    private function responseBody(array $payload, string $id): array
    {
        return [
            'id' => $id,
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
            ]],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 200,
                'total_tokens' => 300,
            ],
        ];
    }

    private function grantPremium(User $user): UserProductGrant
    {
        return UserProductGrant::create([
            'user_id' => $user->id,
            'product_key' => ProductKey::PremiumCore,
            'source' => 'manual',
            'starts_at' => now()->subMinute(),
            'expires_at' => null,
            'metadata' => ['test' => true],
        ]);
    }

    private function studyPlan(): array
    {
        $user = User::factory()->create();

        $plan = Plan::create([
            'user_id' => $user->id,
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => 'AP対策',
            'category' => '資格学習',
            'priority_mode' => 'auto',
            'start_date' => today(),
            'deadline' => today()->addMonth(),
            'is_public' => false,
        ]);

        $task = Task::create([
            'plan_id' => $plan->id,
            'title' => '科目A 横断演習',
            'description' => '応用情報技術者試験 科目Aを横断的に確認する',
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
