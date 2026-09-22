<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Task;
use App\Models\User;
use App\Services\PlanToolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudyPracticeToolV392Test extends TestCase
{
    use RefreshDatabase;

    public function test_study_plan_exposes_ai_practice_as_a_task_tool(): void
    {
        [$user, $plan, $task] = $this->studyPlan();

        $plan->load(['resources', 'tasks.resources', 'tasks.artifacts']);
        $task = $plan->tasks->first();

        $tools = collect(app(PlanToolService::class)->forTask($plan, $task, true));

        $this->assertTrue($tools->contains(fn ($tool) => $tool['id'] === 'ai_practice'));
        $this->assertTrue($tools->contains(fn ($tool) => $tool['id'] === 'timer'));
    }

    public function test_non_study_plan_does_not_expose_ai_practice(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user, '個人開発');
        $task = $this->task($plan, '実装する');
        $plan->load(['resources', 'tasks.resources', 'tasks.artifacts']);

        $tools = collect(app(PlanToolService::class)->forTask($plan, $plan->tasks->first(), true));

        $this->assertFalse($tools->contains(fn ($tool) => $tool['id'] === 'ai_practice'));
        $this->assertTrue($tools->contains(fn ($tool) => $tool['id'] === 'artifacts'));
    }

    public function test_plan_header_exposes_direct_ai_practice_entry_for_study_plan(): void
    {
        [$user, $plan, $task] = $this->studyPlan();

        $this->actingAs($user)
            ->get(route('plans.show', $plan))
            ->assertOk()
            ->assertSee('✦ AI演習')
            ->assertSee(route('plans.tasks.study_practice.show', [$plan, $task]), false);
    }

    public function test_likely_study_plan_explains_category_mismatch_instead_of_hiding_tool_reason(): void
    {
        $user = User::factory()->create();
        $plan = $this->plan($user, 'その他');
        $plan->update(['title' => '応用情報技術者試験 AP 対策']);
        $this->task($plan, 'ネットワークを復習する');

        $this->actingAs($user)
            ->get(route('plans.show', $plan))
            ->assertOk()
            ->assertSee('AI演習を使えそうですが、Planカテゴリが一致していません')
            ->assertSee('カテゴリを「資格学習」に変更すると');
    }

    public function test_question_json_can_be_imported_and_rendered_as_canovia_form(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $payload = [
            'schema_version' => '1.0',
            'flow' => 'study_practice',
            'target_plan' => ['id' => $plan->id],
            'target_task' => ['id' => $task->id],
            'title' => 'ネットワーク確認',
            'questions' => [
                [
                    'id' => 'q1',
                    'type' => 'single_choice',
                    'prompt' => 'DNSの役割として適切なものはどれか。',
                    'choices' => [
                        ['id' => 'A', 'label' => '名前解決'],
                        ['id' => 'B', 'label' => '暗号化'],
                    ],
                ],
            ],
        ];

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.import', [$plan, $task]), [
                'questions_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]));

        $this->actingAs($user)
            ->get(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('ネットワーク確認')
            ->assertSee('DNSの役割として適切なものはどれか。')
            ->assertSee('名前解決');
    }

    public function test_smart_quoted_question_json_is_absorbed_without_ai_repair_roundtrip(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $json = '｛“schema_version”：“1.0”，“flow”：“study_practice”，“target_plan”：｛“id”：'.$plan->id.'｝，“target_task”：｛“id”：'.$task->id.'｝，“title”：“引用符補正確認”，“questions”：［｛“id”：“q1”，“type”：“text”，“prompt”：“いわゆる“ゼロトラスト”を説明せよ”，“choices”：［］｝］｝';

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.import', [$plan, $task]), [
                'questions_json' => $json,
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('引用符補正確認')
            ->assertSee('いわゆる“ゼロトラスト”を説明せよ');
    }

    public function test_question_can_combine_choice_and_reasoning_fields(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $payload = [
            'schema_version' => '1.0',
            'flow' => 'study_practice',
            'target_plan' => ['id' => $plan->id],
            'target_task' => ['id' => $task->id],
            'title' => '選択＋思考過程',
            'questions' => [[
                'id' => 'q1',
                'prompt' => 'DNSの役割として最も適切なものを選び、理由も説明してください。',
                'response_fields' => [
                    [
                        'id' => 'answer',
                        'type' => 'single_choice',
                        'label' => '回答',
                        'required' => true,
                        'choices' => [
                            ['id' => 'A', 'label' => '名前解決'],
                            ['id' => 'B', 'label' => '暗号化'],
                        ],
                    ],
                    [
                        'id' => 'reasoning',
                        'type' => 'textarea',
                        'label' => '考え方・判断理由',
                        'required' => false,
                    ],
                ],
            ]],
        ];

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.import', [$plan, $task]), [
                'questions_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('選択＋思考過程')
            ->assertSee('考え方・判断理由')
            ->assertSee('answers[q1][answer]', false)
            ->assertSee('answers[q1][reasoning]', false);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.answers', [$plan, $task]), [
                'answers' => [
                    'q1' => [
                        'answer' => 'A',
                        'reasoning' => 'DNSはドメイン名とIPアドレスの対応を扱うため。',
                    ],
                ],
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('DNSはドメイン名とIPアドレスの対応を扱うため。');
    }

    public function test_wrong_task_target_is_rejected(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $payload = [
            'flow' => 'study_practice',
            'target_plan' => ['id' => $plan->id],
            'target_task' => ['id' => $task->id + 999],
            'questions' => [[
                'id' => 'q1',
                'type' => 'text',
                'prompt' => '説明してください。',
                'choices' => [],
            ]],
        ];

        $this->actingAs($user)
            ->from(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->post(route('plans.tasks.study_practice.import', [$plan, $task]), [
                'questions_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ])
            ->assertSessionHasErrors('questions_json');
    }

    public function test_invalid_question_json_shows_a_repair_prompt_with_exact_target_context(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $show = route('plans.tasks.study_practice.show', [$plan, $task]);

        $this->actingAs($user)
            ->from($show)
            ->post(route('plans.tasks.study_practice.import', [$plan, $task]), [
                'questions_json' => '{"schema_version":"1.0","flow":"study_practice",',
            ])
            ->assertRedirect($show)
            ->assertSessionHasErrors('questions_json');

        $this->actingAs($user)
            ->get($show)
            ->assertOk()
            ->assertSee('修正依頼を作りました')
            ->assertSee('修正依頼をコピー')
            ->assertSee('target_plan.idは '.$plan->id)
            ->assertSee('target_task.idは '.$task->id)
            ->assertSee('flowは&quot;study_practice&quot;', false)
            ->assertSee('元のCanovia問題作成プロンプト');
    }

    public function test_invalid_assessment_json_shows_a_repair_prompt_with_evaluation_context(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $show = route('plans.tasks.study_practice.show', [$plan, $task]);
        $session = [
            "study_practice.{$plan->id}.{$task->id}" => [
                'title' => '確認',
                'questions' => [[
                    'id' => 'q1',
                    'type' => 'text',
                    'prompt' => 'DNSを説明してください。',
                    'choices' => [],
                ]],
                'answers' => [['question_id' => 'q1', 'answer' => '名前解決']],
                'evaluation_prompt' => 'ORIGINAL EVALUATION PROMPT',
                'assessment' => null,
                'attempt_id' => null,
                'attempt_token' => (string) Str::uuid(),
            ],
        ];

        $this->actingAs($user)
            ->withSession($session)
            ->from($show)
            ->post(route('plans.tasks.study_practice.assessment', [$plan, $task]), [
                'assessment_json' => '{"flow":"study_assessment",',
            ])
            ->assertRedirect($show)
            ->assertSessionHasErrors('assessment_json');

        $this->actingAs($user)
            ->get($show)
            ->assertOk()
            ->assertSee('評価JSONの修正依頼を作りました')
            ->assertSee('修正依頼をコピー')
            ->assertSee('target_plan.idは '.$plan->id)
            ->assertSee('target_task.idは '.$task->id)
            ->assertSee('ORIGINAL EVALUATION PROMPT');
    }

    public function test_answers_create_evaluation_prompt_and_assessment_preview(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $questions = [
            'title' => '確認',
            'questions' => [[
                'id' => 'q1',
                'type' => 'text',
                'prompt' => 'DNSの役割を説明してください。',
                'choices' => [],
            ]],
        ];

        $this->actingAs($user)
            ->withSession(["study_practice.{$plan->id}.{$task->id}" => $questions])
            ->post(route('plans.tasks.study_practice.answers', [$plan, $task]), [
                'answers' => ['q1' => 'ドメイン名をIPアドレスへ対応付ける'],
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]));

        $assessment = [
            'flow' => 'study_assessment',
            'target_plan' => ['id' => $plan->id],
            'target_task' => ['id' => $task->id],
            'score_percent' => 90,
            'question_feedback' => [[
                'question_id' => 'q1',
                'correctness' => 'correct',
                'feedback' => '最終回答は正しいです。',
                'reasoning_feedback' => '名前解決という役割を根拠にできています。',
                'misconceptions' => [],
            ]],
            'strengths' => ['名前解決を理解'],
            'weaknesses' => ['レコード種別'],
            'recommended_task_progress_percent' => 75,
            'evidence_summary' => '主要概念は説明できている。',
            'next_action' => 'DNSレコードの類題を3問解く',
        ];

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.assessment', [$plan, $task]), [
                'assessment_json' => json_encode($assessment, JSON_UNESCAPED_UNICODE),
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]));

        $this->actingAs($user)
            ->get(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('90%')
            ->assertSee('75%')
            ->assertSee('DNSレコードの類題を3問解く')
            ->assertSee('問題ごとのフィードバック')
            ->assertSee('名前解決という役割を根拠にできています。');
    }

    private function studyPlan(): array
    {
        $user = User::factory()->create();
        $plan = $this->plan($user, '資格学習');
        $task = $this->task($plan, 'ネットワーク分野の演習');

        return [$user, $plan, $task];
    }

    private function plan(User $user, string $category): Plan
    {
        return Plan::create([
            'user_id' => $user->id,
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => 'テスト計画',
            'category' => $category,
            'start_date' => today(),
            'deadline' => today()->addMonth(),
            'is_public' => false,
        ]);
    }

    private function task(Plan $plan, string $title): Task
    {
        return Task::create([
            'plan_id' => $plan->id,
            'title' => $title,
            'description' => '理解度を確認する',
            'estimated_minutes' => 60,
            'remaining_minutes' => 60,
            'progress_percent' => 0,
            'status' => 'todo',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 1,
        ]);
    }
}
