<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Question;
use App\Models\QuestionPack;
use App\Models\StudyPracticeAttempt;
use App\Models\StudyPracticeSession;
use App\Models\Task;
use App\Models\User;
use App\Services\AdminAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuestionBankV402Test extends TestCase
{
    use RefreshDatabase;

    public function test_published_ap_pack_replaces_external_handoff_and_is_graded_directly(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $pack = $this->pack(10);

        $show = route('plans.tasks.study_practice.show', [$plan, $task]);

        $this->actingAs($user)
            ->get($show)
            ->assertOk()
            ->assertSee('Canovia Question Bank')
            ->assertSee('Canovia問題集から演習を始める')
            ->assertDontSee('演習準備プロンプトをコピー');

        $prepareRequestId = (string) Str::uuid();

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.prepare', [$plan, $task]), [
                'prepare_request_id' => $prepareRequestId,
            ])
            ->assertRedirect($show)
            ->assertSessionHasNoErrors();

        $session = StudyPracticeSession::where('prepare_request_id', $prepareRequestId)->firstOrFail();

        $this->assertSame('question_bank', $session->question_provider);
        $this->assertSame('direct', $session->question_provider_mode);
        $this->assertSame(StudyPracticeSession::STATUS_READY, $session->status);
        $this->assertCount(10, $session->selected_questions);
        $this->assertSame($pack->id, (int) data_get($session->provider_payload, 'pack.id'));

        $questions = $pack->questions()->get();
        $answers = [];
        foreach ($questions as $index => $question) {
            $answers['bank_'.$question->id] = [
                'answer' => $index < 8 ? 'A' : 'B',
            ];
        }

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.answers', [$plan, $task]), [
                'answers' => $answers,
            ])
            ->assertRedirect($show)
            ->assertSessionHasNoErrors();

        $session->refresh();
        $attempt = StudyPracticeAttempt::latest('id')->firstOrFail();

        $this->assertSame(StudyPracticeSession::STATUS_ASSESSED, $session->status);
        $this->assertSame('question_bank_grader', $session->assessment_provider);
        $this->assertSame('direct', $session->assessment_provider_mode);
        $this->assertSame(80, $attempt->score_percent);
        $this->assertSame($task->progress_percent, $attempt->recommended_task_progress_percent);
        $this->assertSame($session->id, $attempt->study_practice_session_id);

        $this->actingAs($user)
            ->get($show)
            ->assertOk()
            ->assertSee('80%')
            ->assertSee('Question Bankの採点ルールで8/10問を正解しました。')
            ->assertDontSee('評価プロンプトをコピー');
    }

    public function test_insufficient_pack_coverage_falls_back_to_external_ai(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $this->pack(9);

        $this->actingAs($user)
            ->get(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('外部AI')
            ->assertSee('演習準備プロンプトをコピー')
            ->assertDontSee('Canovia問題集から演習を始める');
    }

    public function test_optional_reasoning_written_by_user_routes_bank_question_to_external_assessment(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $pack = $this->pack(10, withReasoning: true);
        $show = route('plans.tasks.study_practice.show', [$plan, $task]);
        $prepareRequestId = (string) Str::uuid();

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.prepare', [$plan, $task]), [
                'prepare_request_id' => $prepareRequestId,
            ])
            ->assertSessionHasNoErrors();

        $answers = [];
        foreach ($pack->questions()->get() as $question) {
            $answers['bank_'.$question->id] = [
                'answer' => 'A',
                'reasoning' => 'この選択肢が正しいと判断した理由を説明します。',
            ];
        }

        $this->actingAs($user)
            ->post(route('plans.tasks.study_practice.answers', [$plan, $task]), [
                'answers' => $answers,
            ])
            ->assertRedirect($show)
            ->assertSessionHasNoErrors();

        $session = StudyPracticeSession::where('prepare_request_id', $prepareRequestId)->firstOrFail();

        $this->assertSame(StudyPracticeSession::STATUS_ANSWERED, $session->status);
        $this->assertSame('external_ai', $session->assessment_provider);
        $this->assertSame('handoff', $session->assessment_provider_mode);
        $this->assertStringContainsString(
            'grading_context',
            (string) data_get($session->assessment_payload, 'evaluation_prompt'),
        );
        $this->assertStringContainsString(
            'exact_choice',
            (string) data_get($session->assessment_payload, 'evaluation_prompt'),
        );
        $this->assertDatabaseCount('study_practice_attempts', 0);

        $this->actingAs($user)
            ->get($show)
            ->assertOk()
            ->assertSee('評価プロンプトをコピー');
    }

    public function test_generic_subject_match_cannot_select_another_qualification_pack(): void
    {
        [$user, $plan, $task] = $this->studyPlan();

        $pack = QuestionPack::create([
            'slug' => 'fe-a-wrong-pack',
            'title' => '基本情報技術者試験 科目A',
            'exam_code' => 'FE',
            'subject' => '科目A',
            'version' => '1',
            'status' => 'published',
            'downloadable' => true,
            'metadata' => [
                'match_terms' => ['FE', '基本情報', '基本情報技術者試験'],
            ],
        ]);

        foreach (range(1, 10) as $index) {
            Question::create([
                'question_pack_id' => $pack->id,
                'external_key' => 'fe-'.$index,
                'source_type' => 'canovia_original',
                'prompt' => "FE 科目A {$index}",
                'response_schema' => [[
                    'id' => 'answer',
                    'type' => 'single_choice',
                    'label' => '回答',
                    'required' => true,
                    'choices' => [
                        ['id' => 'A', 'label' => 'A'],
                        ['id' => 'B', 'label' => 'B'],
                    ],
                ]],
                'grading_rule' => [
                    'type' => 'exact_choice',
                    'field_id' => 'answer',
                    'answer' => 'A',
                ],
                'learning_metadata' => ['concepts' => ['科目A']],
                'difficulty' => 3,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        $this->actingAs($user)
            ->get(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('外部AI')
            ->assertDontSee('Canovia問題集から演習を始める');
    }

    public function test_admin_can_import_draft_publish_it_and_cannot_overwrite_published_pack(): void
    {
        $payload = $this->importPayload();

        $this->withSession([AdminAccessService::SESSION_KEY => true])
            ->post(route('admin.question_packs.import'), [
                'pack_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ])
            ->assertRedirect(route('admin.question_packs.index'))
            ->assertSessionHasNoErrors();

        $pack = QuestionPack::where('slug', 'ap-a-admin-test')->firstOrFail();

        $this->assertSame('draft', $pack->status);
        $this->assertSame(2, $pack->questions()->count());

        $this->withSession([AdminAccessService::SESSION_KEY => true])
            ->patch(route('admin.question_packs.status', $pack), ['status' => 'published'])
            ->assertSessionHasNoErrors();

        $this->assertSame('published', $pack->fresh()->status);

        $this->withSession([AdminAccessService::SESSION_KEY => true])
            ->from(route('admin.question_packs.index'))
            ->patch(route('admin.question_packs.status', $pack), ['status' => 'draft'])
            ->assertSessionHasErrors('status');

        $this->assertSame('published', $pack->fresh()->status);

        $this->withSession([AdminAccessService::SESSION_KEY => true])
            ->from(route('admin.question_packs.index'))
            ->post(route('admin.question_packs.import'), [
                'pack_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ])
            ->assertRedirect(route('admin.question_packs.index'))
            ->assertSessionHasErrors('pack_json');
    }

    public function test_reimporting_draft_deactivates_questions_omitted_from_authoritative_json(): void
    {
        $payload = $this->importPayload();

        $this->withSession([AdminAccessService::SESSION_KEY => true])
            ->post(route('admin.question_packs.import'), [
                'pack_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

        $payload['questions'] = [$payload['questions'][0]];

        $this->withSession([AdminAccessService::SESSION_KEY => true])
            ->post(route('admin.question_packs.import'), [
                'pack_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ])
            ->assertSessionHasNoErrors();

        $pack = QuestionPack::where('slug', 'ap-a-admin-test')->firstOrFail();

        $this->assertSame(2, $pack->questions()->count());
        $this->assertSame(1, $pack->questions()->where('is_active', true)->count());
    }

    private function pack(int $count, bool $withReasoning = false): QuestionPack
    {
        $pack = QuestionPack::create([
            'slug' => 'ap-a-v402-'.Str::lower(Str::random(6)),
            'title' => '応用情報技術者試験 科目A',
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
            $responseSchema = [[
                'id' => 'answer',
                'type' => 'single_choice',
                'label' => '回答',
                'required' => true,
                'choices' => [
                    ['id' => 'A', 'label' => '正しい選択肢'],
                    ['id' => 'B', 'label' => '誤った選択肢'],
                ],
            ]];

            if ($withReasoning) {
                $responseSchema[] = [
                    'id' => 'reasoning',
                    'type' => 'textarea',
                    'label' => '考え方',
                    'required' => false,
                    'choices' => [],
                ];
            }

            Question::create([
                'question_pack_id' => $pack->id,
                'external_key' => 'q'.$index,
                'source_type' => 'canovia_original',
                'source_reference' => null,
                'prompt' => "AP確認問題 {$index}",
                'response_schema' => $responseSchema,
                'grading_rule' => [
                    'type' => 'exact_choice',
                    'field_id' => 'answer',
                    'answer' => 'A',
                ],
                'learning_metadata' => [
                    'concepts' => ['ネットワーク'],
                    'weakness_targets' => [],
                    'tags' => ['科目A'],
                    'keywords' => [],
                ],
                'explanation' => 'Aが正答です。',
                'difficulty' => 3,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        return $pack->fresh();
    }

    private function importPayload(): array
    {
        return [
            'schema_version' => '1.0',
            'pack' => [
                'slug' => 'ap-a-admin-test',
                'title' => '応用情報 科目A Admin Test',
                'exam_code' => 'AP',
                'subject' => '科目A',
                'version' => '1',
                'downloadable' => true,
                'metadata' => [
                    'match_terms' => ['AP', '応用情報'],
                ],
            ],
            'questions' => [
                [
                    'external_key' => 'official-001',
                    'source_type' => 'official',
                    'source_reference' => '公式出典 001',
                    'prompt' => '問題1',
                    'response_schema' => [[
                        'id' => 'answer',
                        'type' => 'single_choice',
                        'label' => '回答',
                        'required' => true,
                        'choices' => [
                            ['id' => 'A', 'label' => 'A'],
                            ['id' => 'B', 'label' => 'B'],
                        ],
                    ]],
                    'grading_rule' => [
                        'type' => 'exact_choice',
                        'field_id' => 'answer',
                        'answer' => 'A',
                    ],
                    'learning_metadata' => [
                        'concepts' => ['ネットワーク'],
                        'weakness_targets' => ['DNS'],
                        'tags' => ['科目A'],
                        'keywords' => [],
                    ],
                    'explanation' => '解説1',
                    'difficulty' => 3,
                    'sort_order' => 1,
                    'is_active' => true,
                ],
                [
                    'external_key' => 'official-002',
                    'source_type' => 'official',
                    'source_reference' => '公式出典 002',
                    'prompt' => '問題2',
                    'response_schema' => [[
                        'id' => 'answer',
                        'type' => 'number',
                        'label' => '回答',
                        'required' => true,
                        'choices' => [],
                    ]],
                    'grading_rule' => [
                        'type' => 'numeric_tolerance',
                        'field_id' => 'answer',
                        'answer' => 42,
                        'tolerance' => 0,
                    ],
                    'learning_metadata' => [
                        'concepts' => ['計算'],
                        'weakness_targets' => [],
                        'tags' => ['科目A'],
                        'keywords' => [],
                    ],
                    'explanation' => '解説2',
                    'difficulty' => 3,
                    'sort_order' => 2,
                    'is_active' => true,
                ],
            ],
        ];
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
            'title' => '科目A ネットワーク演習',
            'description' => 'AP科目Aの理解度を確認する',
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
