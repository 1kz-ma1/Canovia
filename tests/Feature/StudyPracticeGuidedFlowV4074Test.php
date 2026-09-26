<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\StudyPracticeAttempt;
use App\Models\Task;
use App\Models\User;
use App\Services\StudyPracticePromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudyPracticeGuidedFlowV4074Test extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_next_step_guides_the_user_into_the_next_practice(): void
    {
        [$user, $plan, $task] = $this->studyPlan();
        $key = "study_practice.{$plan->id}.{$task->id}";

        $this->actingAs($user)
            ->withSession([
                $key => [
                    'title' => 'ネットワーク確認',
                    'questions' => [[
                        'id' => 'q1',
                        'type' => 'text',
                        'prompt' => 'MTUとTCP分割の関係を説明してください。',
                        'choices' => [],
                    ]],
                    'answers' => [[
                        'question_id' => 'q1',
                        'fields' => [[
                            'field_id' => 'answer',
                            'type' => 'textarea',
                            'label' => '回答',
                            'value' => 'MTUを超える場合に分割が必要になる。',
                        ]],
                    ]],
                    'draft_answers' => [
                        'q1' => ['answer' => 'MTUを超える場合に分割が必要になる。'],
                    ],
                    'evaluation_prompt' => 'evaluation prompt',
                    'assessment' => null,
                    'attempt_id' => null,
                    'attempt_token' => (string) Str::uuid(),
                    'practice_session_id' => null,
                ],
            ])
            ->post(route('plans.tasks.study_practice.assessment', [$plan, $task]), [
                'assessment_json' => json_encode([
                    'schema_version' => '1.0',
                    'flow' => 'study_assessment',
                    'target_plan' => ['id' => $plan->id],
                    'target_task' => ['id' => $task->id],
                    'score_percent' => 72,
                    'question_feedback' => [[
                        'question_id' => 'q1',
                        'correctness' => 'partial',
                        'feedback' => '基本方向は合っています。',
                        'reasoning_feedback' => 'IPフラグメンテーションとの違いを整理しましょう。',
                        'misconceptions' => ['MTUとMSSの混同'],
                    ]],
                    'strengths' => ['MTU超過時に分割が関係することを理解している'],
                    'weaknesses' => ['MTU計算', 'TCP分割'],
                    'recommended_task_progress_percent' => 65,
                    'evidence_summary' => '基本概念は理解しているが、MTU/MSSと分割単位に混同が残る。',
                    'next_action' => 'MTUとTCP分割の類題を5問解く',
                    'next_step' => [
                        'kind' => 'practice',
                        'label' => 'MTUとTCP分割の類題を5問解く',
                        'reason' => 'MTU/MSSと分割単位の混同が残っているため。',
                        'focus_topics' => ['MTU', 'TCP分割'],
                        'question_count' => 5,
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertSessionHas('study_practice_scroll_to', 'practice-assessment');

        $attempt = StudyPracticeAttempt::firstOrFail();

        $this->assertSame('practice', data_get($attempt->assessment, 'next_step.kind'));
        $this->assertSame(['MTU', 'TCP分割'], data_get($attempt->assessment, 'next_step.focus_topics'));
        $this->assertSame(5, data_get($attempt->assessment, 'next_step.question_count'));

        $this->get(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('NEXT ACTION')
            ->assertSee('MTUとTCP分割の類題を5問解く')
            ->assertSee('結果を反映して次へ')
            ->assertSee('回答済み 1問 · 今回の回答を見直す')
            ->assertSee('AI評価の受け渡しを確認する');

        $this->post(route('plans.tasks.study_practice.apply', [$plan, $task]), [
            'attempt_id' => $attempt->id,
            'request_hash' => $attempt->request_hash,
        ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertSessionHas('study_practice_scroll_to', 'practice-assessment');

        $this->get(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('この内容で次の演習へ');

        $this->post(route('plans.tasks.study_practice.reset', [$plan, $task]), [
            'continue' => '1',
        ])
            ->assertRedirect(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertSessionHas('status', '前回の結果を引き継いで、次の演習を準備します。');

        $this->get(route('plans.tasks.study_practice.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('弱点補強')
            ->assertSee('5問目安')
            ->assertSee('MTU')
            ->assertSee('TCP分割');
    }

    public function test_evaluation_prompt_requires_a_structured_next_step(): void
    {
        [, $plan, $task] = $this->studyPlan();

        $prompt = app(StudyPracticePromptService::class)->evaluationPrompt(
            $plan,
            $task,
            [[
                'id' => 'q1',
                'prompt' => '確認問題',
                'response_fields' => [[
                    'id' => 'answer',
                    'type' => 'textarea',
                    'label' => '回答',
                    'required' => true,
                    'choices' => [],
                ]],
            ]],
            [[
                'question_id' => 'q1',
                'fields' => [[
                    'field_id' => 'answer',
                    'type' => 'textarea',
                    'label' => '回答',
                    'value' => '回答内容',
                ]],
            ]],
        );

        $this->assertStringContainsString('next_stepは「この評価を見た直後にCanovia上で何をすべきか」', $prompt);
        $this->assertStringContainsString('practice / review / continue_task / complete_task / plan_update', $prompt);
        $this->assertStringContainsString('"next_step": {', $prompt);
        $this->assertStringContainsString('"focus_topics": ["DNS", "CNAME"]', $prompt);
        $this->assertStringContainsString('"question_count": 5', $prompt);
    }

    public function test_result_is_rendered_before_collapsed_previous_steps(): void
    {
        $view = file_get_contents(resource_path('views/study_practice/show.blade.php'));

        $resultPosition = strpos($view, '<section id="practice-assessment"');
        $answersPosition = strpos($view, '<details id="practice-questions"');
        $evaluationPosition = strpos($view, '<details id="practice-evaluation"');

        $this->assertIsInt($resultPosition);
        $this->assertIsInt($answersPosition);
        $this->assertIsInt($evaluationPosition);
        $this->assertLessThan($answersPosition, $resultPosition);
        $this->assertLessThan($evaluationPosition, $resultPosition);
        $this->assertStringContainsString('結果・次Action', $view);
        $this->assertStringContainsString('今回の回答を見直す', $view);
        $this->assertStringContainsString('AI評価の受け渡しを確認する', $view);
        $this->assertStringContainsString('詳しい評価を確認', $view);
        $this->assertSame(1, substr_count($view, "route('plans.tasks.study_practice.apply'"));
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
            'start_date' => today(),
            'deadline' => today()->addMonth(),
            'is_public' => false,
        ]);

        $task = Task::create([
            'plan_id' => $plan->id,
            'title' => 'ネットワーク弱点補強',
            'description' => 'MTU/TCP分割を理解する',
            'estimated_minutes' => 120,
            'remaining_minutes' => 90,
            'progress_percent' => 35,
            'status' => 'doing',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 1,
        ]);

        return [$user, $plan, $task];
    }
}
