<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PracticeQuestionCandidate;
use App\Models\PracticeQuestionDemand;
use App\Models\QuestionPack;
use App\Models\StudyPracticeSession;
use App\Models\Task;
use App\Models\User;
use App\Services\PracticeQuestionCandidateRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PracticeDemandCandidatesV419Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_practice_supply_summary_and_candidate_queue(): void
    {
        [$admin, $plan, $task] = $this->studyPlan();
        config(['canovia.super_admin_user_id' => $admin->id]);

        $demand = $this->demand($plan, $task, [
            'exam_profile_key' => 'ap_subject_a_exam',
            'assembly_mode' => 'hybrid',
            'requested_count' => 10,
            'bank_selected_count' => 4,
            'generated_requested_count' => 6,
            'generated_count' => 6,
            'focus_topics' => ['DNS', 'MTU'],
        ]);

        PracticeQuestionCandidate::create([
            'fingerprint' => hash('sha256', 'admin-list'),
            'status' => PracticeQuestionCandidate::STATUS_PENDING,
            'provider' => 'native_ai',
            'exam_profile_key' => 'ap_subject_a_exam',
            'first_practice_question_demand_id' => $demand->id,
            'latest_practice_question_demand_id' => $demand->id,
            'question_payload' => $this->questionPayload(),
            'review_hints' => ['focus_topics' => ['DNS']],
            'generation_count' => 1,
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.practice_demand.index', [
                'period' => '30',
                'candidate_status' => 'pending',
            ]))
            ->assertOk()
            ->assertSee('演習需要とQuestion Candidate')
            ->assertSee('ap_subject_a_exam')
            ->assertSee('DNS')
            ->assertSee('60.0%')
            ->assertSee('Native補完問題');
    }

    public function test_native_question_candidate_recording_is_idempotent_per_demand_and_counts_repeat_demand(): void
    {
        [$user, $plan, $task] = $this->studyPlan();

        $session = StudyPracticeSession::create([
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'user_id' => $user->id,
            'session_token' => (string) Str::uuid(),
            'prepare_request_id' => (string) Str::uuid(),
            'status' => StudyPracticeSession::STATUS_READY,
            'strategy' => 'weakness_reinforcement',
            'strategy_version' => 'v41.4',
            'selector_type' => 'hybrid_question_assembly',
            'selector_version' => 'hybrid-v1',
            'question_provider' => 'hybrid_ai',
            'question_provider_mode' => 'direct',
            'assessment_provider' => 'external_ai',
            'assessment_provider_mode' => 'handoff',
        ]);

        $demand1 = $this->demand($plan, $task, [
            'user_id' => $user->id,
            'study_practice_session_id' => $session->id,
            'prepare_request_id' => $session->prepare_request_id,
            'exam_profile_key' => 'ap_subject_a_exam',
            'assembly_mode' => 'hybrid',
            'requested_count' => 10,
            'bank_selected_count' => 9,
            'generated_requested_count' => 1,
            'generated_count' => 1,
            'focus_topics' => ['DNS'],
        ]);

        $prepared = [
            'provider' => 'hybrid_ai',
            'questions' => [$this->questionPayload()],
            'selected_questions' => [[
                'question_ref' => 'native_1',
                'question_id' => null,
                'source_type' => 'native_ai',
            ]],
            'payload' => [
                'native_ai' => [
                    'model' => 'gpt-test',
                ],
            ],
        ];
        $strategy = [
            'key' => 'weakness_reinforcement',
            'exam_profile' => [
                'key' => 'ap_subject_a_exam',
                'label' => 'AP科目A',
            ],
            'focus_topics' => ['DNS'],
            'question_mix' => [
                'primary' => 4,
                'secondary' => 2,
                'diagnostic' => 4,
            ],
        ];

        $recorder = app(PracticeQuestionCandidateRecorder::class);
        $recorder->record($session, $demand1, $strategy, $prepared);
        $recorder->record($session, $demand1, $strategy, $prepared);

        $candidate = PracticeQuestionCandidate::firstOrFail();
        $this->assertSame(1, $candidate->generation_count);
        $this->assertSame($demand1->id, $candidate->first_practice_question_demand_id);
        $this->assertSame($demand1->id, $candidate->latest_practice_question_demand_id);

        $demand2 = $this->demand($plan, $task, [
            'user_id' => $user->id,
            'study_practice_session_id' => null,
            'exam_profile_key' => 'ap_subject_a_exam',
            'assembly_mode' => 'generated_only',
            'requested_count' => 10,
            'bank_selected_count' => 0,
            'generated_requested_count' => 10,
            'generated_count' => 10,
            'focus_topics' => ['DNS'],
        ]);

        $recorder->record($session, $demand2, $strategy, $prepared);

        $candidate->refresh();
        $this->assertSame(2, $candidate->generation_count);
        $this->assertSame($demand2->id, $candidate->latest_practice_question_demand_id);
        $this->assertDatabaseCount('practice_question_candidates', 1);
    }

    public function test_admin_can_promote_reviewed_candidate_only_into_draft_pack(): void
    {
        [$admin, $plan, $task] = $this->studyPlan();
        config(['canovia.super_admin_user_id' => $admin->id]);

        $demand = $this->demand($plan, $task);
        $candidate = $this->candidate($demand);
        $draft = $this->pack('candidate-review-v1', 'draft');

        $this->actingAs($admin)
            ->post(route('admin.practice_demand.candidates.promote', $candidate), [
                'question_pack_id' => $draft->id,
                'external_key' => 'dns-ai-reviewed-001',
                'grading_rule_json' => json_encode([
                    'type' => 'exact_choice',
                    'field_id' => 'answer',
                    'answer' => 'A',
                ]),
                'learning_metadata_json' => json_encode([
                    'concepts' => ['ネットワーク'],
                    'weakness_targets' => ['DNS'],
                    'tags' => ['科目A'],
                    'keywords' => ['名前解決'],
                ]),
                'explanation' => 'Aが正解である理由を人が確認した。',
                'difficulty' => 3,
                'source_reference' => 'Canovia Native AI candidate #'.$candidate->id,
                'review_note' => '問題文・選択肢・正答を確認済み。',
            ])
            ->assertRedirect(route('admin.practice_demand.candidates.show', $candidate))
            ->assertSessionHasNoErrors();

        $candidate->refresh();

        $this->assertSame(PracticeQuestionCandidate::STATUS_PROMOTED, $candidate->status);
        $this->assertSame($draft->id, $candidate->promoted_question_pack_id);
        $this->assertNotNull($candidate->promoted_question_id);
        $this->assertSame($admin->id, $candidate->reviewed_by_user_id);

        $this->assertDatabaseHas('questions', [
            'id' => $candidate->promoted_question_id,
            'question_pack_id' => $draft->id,
            'external_key' => 'dns-ai-reviewed-001',
            'source_type' => 'ai_generated',
            'is_active' => true,
        ]);
    }

    public function test_candidate_cannot_be_promoted_directly_into_published_pack(): void
    {
        [$admin, $plan, $task] = $this->studyPlan();
        config(['canovia.super_admin_user_id' => $admin->id]);

        $candidate = $this->candidate($this->demand($plan, $task));
        $published = $this->pack('already-published-v1', 'published');

        $this->actingAs($admin)
            ->from(route('admin.practice_demand.candidates.show', $candidate))
            ->post(route('admin.practice_demand.candidates.promote', $candidate), [
                'question_pack_id' => $published->id,
                'external_key' => 'must-not-promote',
                'grading_rule_json' => json_encode([
                    'type' => 'exact_choice',
                    'field_id' => 'answer',
                    'answer' => 'A',
                ]),
                'learning_metadata_json' => json_encode([
                    'concepts' => ['ネットワーク'],
                    'weakness_targets' => ['DNS'],
                    'tags' => ['科目A'],
                    'keywords' => [],
                ]),
                'difficulty' => 3,
            ])
            ->assertRedirect(route('admin.practice_demand.candidates.show', $candidate))
            ->assertSessionHasErrors('question_pack_id');

        $this->assertDatabaseCount('questions', 0);
        $this->assertSame(
            PracticeQuestionCandidate::STATUS_PENDING,
            $candidate->fresh()->status,
        );
    }

    public function test_non_admin_cannot_open_practice_demand_admin(): void
    {
        [$admin] = $this->studyPlan();
        $other = User::factory()->create();
        config(['canovia.super_admin_user_id' => $admin->id]);

        $this->actingAs($other)
            ->get(route('admin.practice_demand.index'))
            ->assertForbidden();
    }

    private function candidate(PracticeQuestionDemand $demand): PracticeQuestionCandidate
    {
        return PracticeQuestionCandidate::create([
            'fingerprint' => hash('sha256', 'candidate-'.$demand->id),
            'status' => PracticeQuestionCandidate::STATUS_PENDING,
            'provider' => 'native_ai',
            'model' => 'gpt-test',
            'exam_profile_key' => $demand->exam_profile_key,
            'first_practice_question_demand_id' => $demand->id,
            'latest_practice_question_demand_id' => $demand->id,
            'question_payload' => $this->questionPayload(),
            'review_hints' => [
                'strategy_key' => 'weakness_reinforcement',
                'focus_topics' => ['DNS'],
            ],
            'generation_count' => 1,
            'last_seen_at' => now(),
        ]);
    }

    private function questionPayload(): array
    {
        return [
            'id' => 'native_1',
            'prompt' => 'Native補完問題: DNSの役割として適切なものはどれか。',
            'work_input' => 'none',
            'response_fields' => [[
                'id' => 'answer',
                'type' => 'single_choice',
                'label' => '回答',
                'required' => true,
                'placeholder' => '',
                'choices' => [
                    ['id' => 'A', 'label' => 'ドメイン名をIPアドレスへ対応付ける'],
                    ['id' => 'B', 'label' => 'ファイルを圧縮する'],
                    ['id' => 'C', 'label' => 'CPUをスケジューリングする'],
                    ['id' => 'D', 'label' => '主記憶を仮想化する'],
                ],
            ]],
        ];
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function demand(Plan $plan, Task $task, array $overrides = []): PracticeQuestionDemand
    {
        return PracticeQuestionDemand::create(array_merge([
            'user_id' => $plan->user_id,
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'study_practice_session_id' => null,
            'question_pack_id' => null,
            'prepare_request_id' => (string) Str::uuid(),
            'strategy_key' => 'weakness_reinforcement',
            'exam_profile_key' => 'ap_subject_a_exam',
            'assembly_mode' => 'hybrid',
            'generation_provider' => 'native_ai',
            'requested_count' => 10,
            'bank_selected_count' => 5,
            'generated_requested_count' => 5,
            'generated_count' => 5,
            'focus_topics' => ['DNS'],
            'coverage' => [],
            'metadata' => [
                'plan_title' => $plan->title,
                'task_title' => $task->title,
            ],
        ], $overrides));
    }

    private function pack(string $slug, string $status): QuestionPack
    {
        return QuestionPack::create([
            'slug' => $slug,
            'title' => 'Candidate Review Pack',
            'exam_code' => 'AP',
            'subject' => '科目A',
            'version' => '1',
            'status' => $status,
            'downloadable' => true,
            'metadata' => [
                'match_terms' => ['AP', '応用情報'],
            ],
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
