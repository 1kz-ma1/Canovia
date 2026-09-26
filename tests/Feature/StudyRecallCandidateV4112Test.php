<?php

namespace Tests\Feature;

use App\Enums\ProductKey;
use App\Models\NativeAiRun;
use App\Models\Plan;
use App\Models\StudyRecallCandidate;
use App\Models\StudyRecallItem;
use App\Models\StudyRecallSource;
use App\Models\Task;
use App\Models\User;
use App\Models\UserProductGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudyRecallCandidateV4112Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        config([
            'canovia.super_admin_user_id' => null,
            'canovia.admin_email' => null,
            'native_ai.driver' => 'openai',
            'native_ai.providers.openai.base_url' => 'https://api.openai.com/v1',
            'native_ai.providers.openai.api_key' => 'test-key',
            'native_ai.providers.openai.model' => 'gpt-5.6-luna',
        ]);

        Storage::fake('local');
    }

    public function test_free_user_cannot_run_material_extraction(): void
    {
        [$user, $plan, $task] = $this->scenario();

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.candidates.extract', [$plan, $task]), [
                'source_text' => 'abandon は「放棄する」という意味。',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertDatabaseCount('study_recall_sources', 0);
        $this->assertDatabaseCount('study_recall_candidates', 0);
    }

    public function test_premium_user_extracts_text_into_pending_candidates_not_deck_items(): void
    {
        [$user, $plan, $task] = $this->scenario();
        $this->grantPremium($user);
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(
                $this->responseBody($this->candidateEnvelope()),
                200,
            ),
        ]);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.candidates.extract', [$plan, $task]), [
                'source_text' => "abandon: 放棄する\nmaintain: 維持する",
            ])
            ->assertRedirect(route('plans.tasks.study_recall.show', [$plan, $task]))
            ->assertSessionHasNoErrors();

        $source = StudyRecallSource::firstOrFail();
        $this->assertSame('text', $source->source_type);
        $this->assertSame('ready', $source->status);
        $this->assertSame(2, $source->candidate_count);

        $this->assertDatabaseCount('study_recall_candidates', 2);
        $this->assertDatabaseCount('study_recall_items', 0);
        $this->assertTrue(StudyRecallCandidate::query()->get()->every(
            fn (StudyRecallCandidate $candidate) => $candidate->status === 'pending'
        ));

        $run = NativeAiRun::firstOrFail();
        $this->assertSame('study_recall_candidate_extraction', $run->purpose);
        $this->assertSame('succeeded', $run->status);

        $this->actingAs($user)
            ->get(route('plans.tasks.study_recall.show', [$plan, $task]))
            ->assertOk()
            ->assertSee('Deckへ入れる前に確認')
            ->assertSee('abandon')
            ->assertSee('根拠 95/100');
    }

    public function test_pdf_is_sent_to_responses_api_as_input_file(): void
    {
        [$user, $plan, $task] = $this->scenario();
        $this->grantPremium($user);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(
                $this->responseBody($this->candidateEnvelope(oneOnly: true)),
                200,
            ),
        ]);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.candidates.extract', [$plan, $task]), [
                'source_file' => $this->pdfUpload('vocabulary.pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $source = StudyRecallSource::firstOrFail();
        $this->assertSame('pdf', $source->source_type);
        $this->assertSame('application/pdf', $source->mime_type);
        Storage::disk('local')->assertExists($source->storage_path);

        Http::assertSent(function ($request) {
            $content = data_get($request->data(), 'input.0.content', []);
            $file = collect($content)->firstWhere('type', 'input_file');

            return is_array($file)
                && ($file['filename'] ?? null) === 'vocabulary.pdf'
                && str_starts_with((string) ($file['file_data'] ?? ''), 'data:application/pdf;base64,')
                && ($file['detail'] ?? null) === 'auto';
        });
    }

    public function test_screenshot_is_sent_to_responses_api_as_input_image(): void
    {
        [$user, $plan, $task] = $this->scenario();
        $this->grantPremium($user);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(
                $this->responseBody($this->candidateEnvelope(oneOnly: true)),
                200,
            ),
        ]);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.candidates.extract', [$plan, $task]), [
                'source_file' => $this->pngUpload('page.png'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $source = StudyRecallSource::firstOrFail();
        $this->assertSame('image', $source->source_type);
        $this->assertSame('image/png', $source->mime_type);

        Http::assertSent(function ($request) {
            $content = data_get($request->data(), 'input.0.content', []);
            $image = collect($content)->firstWhere('type', 'input_image');

            return is_array($image)
                && str_starts_with((string) ($image['image_url'] ?? ''), 'data:image/png;base64,')
                && ($image['detail'] ?? null) === 'high';
        });
    }

    public function test_human_review_can_edit_and_batch_promote_only_selected_candidates(): void
    {
        [$user, $plan, $task] = $this->scenario();
        $source = $this->source($plan, $task);
        $first = $this->candidate($source, $plan, $task, 'abandon', '放棄する');
        $second = $this->candidate($source, $plan, $task, 'maintain', '維持する');

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.candidates.review', [$plan, $task]), [
                'decision' => 'promote',
                'candidates' => [
                    $first->id => [
                        'selected' => '1',
                        'prompt' => 'abandon の意味は？',
                        'answer' => '放棄する、捨てる',
                        'note' => 'TOEIC頻出',
                    ],
                    $second->id => [
                        'selected' => '0',
                        'prompt' => $second->prompt,
                        'answer' => $second->answer,
                        'note' => '',
                    ],
                ],
            ])
            ->assertRedirect(route('plans.tasks.study_recall.show', [$plan, $task]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('study_recall_items', 1);
        $item = StudyRecallItem::firstOrFail();
        $this->assertSame('abandon の意味は？', $item->prompt);
        $this->assertSame('放棄する、捨てる', $item->answer);
        $this->assertSame('TOEIC頻出', $item->note);

        $this->assertSame('promoted', $first->fresh()->status);
        $this->assertSame($item->id, $first->fresh()->promoted_item_id);
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_duplicate_promoted_card_is_reused_instead_of_created_twice(): void
    {
        [$user, $plan, $task] = $this->scenario();
        $source = $this->source($plan, $task);
        $candidate = $this->candidate($source, $plan, $task, 'abandon', '放棄する');

        $existing = StudyRecallItem::create([
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'prompt' => 'abandon',
            'answer' => '放棄する',
            'tags' => [],
            'fingerprint' => hash('sha256', 'abandon|放棄する'),
            'repetitions' => 0,
            'lapse_count' => 0,
            'interval_days' => 0,
            'ease_factor' => 2.50,
            'due_at' => null,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.candidates.review', [$plan, $task]), [
                'decision' => 'promote',
                'candidates' => [
                    $candidate->id => [
                        'selected' => '1',
                        'prompt' => 'abandon',
                        'answer' => '放棄する',
                        'note' => '',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('study_recall_items', 1);
        $this->assertSame($existing->id, $candidate->fresh()->promoted_item_id);
        $this->assertSame('promoted', $candidate->fresh()->status);
    }

    public function test_selected_candidates_can_be_rejected_without_creating_cards(): void
    {
        [$user, $plan, $task] = $this->scenario();
        $source = $this->source($plan, $task);
        $candidate = $this->candidate($source, $plan, $task, 'abandon', '放棄する');

        $this->actingAs($user)
            ->post(route('plans.tasks.study_recall.candidates.review', [$plan, $task]), [
                'decision' => 'reject',
                'candidates' => [
                    $candidate->id => [
                        'selected' => '1',
                        'prompt' => $candidate->prompt,
                        'answer' => $candidate->answer,
                        'note' => '',
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('rejected', $candidate->fresh()->status);
        $this->assertDatabaseCount('study_recall_items', 0);
    }

    public function test_source_file_is_not_publicly_accessible_to_another_user(): void
    {
        [$owner, $plan, $task] = $this->scenario();
        $source = $this->source($plan, $task, [
            'source_type' => 'pdf',
            'original_name' => 'material.pdf',
            'mime_type' => 'application/pdf',
            'storage_path' => 'study-recall-sources/'.$plan->id.'/'.$task->id.'/material.pdf',
        ]);
        Storage::disk('local')->put($source->storage_path, '%PDF-1.4 test');

        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('plans.tasks.study_recall.sources.file', [$plan, $task, $source]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('plans.tasks.study_recall.sources.file', [$plan, $task, $source]))
            ->assertOk();
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

    private function responseBody(array $payload): array
    {
        return [
            'id' => 'resp_recall',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
            ]],
            'usage' => [
                'input_tokens' => 120,
                'output_tokens' => 80,
                'total_tokens' => 200,
            ],
        ];
    }

    private function candidateEnvelope(bool $oneOnly = false): array
    {
        $candidates = [[
            'prompt' => 'abandon',
            'answer' => '放棄する',
            'note' => '動詞',
            'tags' => ['TOEIC', 'vocabulary'],
            'source_excerpt' => 'abandon: 放棄する',
            'confidence' => 95,
        ]];

        if (! $oneOnly) {
            $candidates[] = [
                'prompt' => 'maintain',
                'answer' => '維持する',
                'note' => null,
                'tags' => ['TOEIC'],
                'source_excerpt' => 'maintain: 維持する',
                'confidence' => 92,
            ];
        }

        return ['candidates' => $candidates];
    }

    private function source(Plan $plan, Task $task, array $overrides = []): StudyRecallSource
    {
        return StudyRecallSource::create(array_merge([
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'user_id' => $plan->user_id,
            'source_type' => 'text',
            'source_text' => 'test source',
            'status' => 'ready',
            'candidate_count' => 0,
        ], $overrides));
    }

    private function candidate(
        StudyRecallSource $source,
        Plan $plan,
        Task $task,
        string $prompt,
        string $answer,
    ): StudyRecallCandidate {
        return StudyRecallCandidate::create([
            'study_recall_source_id' => $source->id,
            'plan_id' => $plan->id,
            'task_id' => $task->id,
            'prompt' => $prompt,
            'answer' => $answer,
            'tags' => ['TOEIC'],
            'source_excerpt' => $prompt.': '.$answer,
            'confidence' => 90,
            'status' => 'pending',
            'fingerprint' => hash('sha256', mb_strtolower($prompt).'|'.mb_strtolower($answer)),
        ]);
    }

    private function pdfUpload(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'recall-pdf-');
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function pngUpload(string $name): UploadedFile
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=',
            true,
        );
        $path = tempnam(sys_get_temp_dir(), 'recall-image-');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    private function scenario(): array
    {
        $user = User::factory()->create();

        $plan = Plan::create([
            'user_id' => $user->id,
            'owner_token' => Str::random(64),
            'public_slug' => (string) Str::uuid(),
            'title' => 'TOEIC 800点',
            'description' => '語彙学習',
            'category' => '資格学習',
            'start_date' => today(),
            'deadline' => today()->addMonths(2),
            'is_public' => false,
        ]);

        $task = Task::create([
            'plan_id' => $plan->id,
            'title' => 'TOEIC英単語を暗記する',
            'description' => '頻出語彙を覚える',
            'estimated_minutes' => 60,
            'remaining_minutes' => 60,
            'progress_percent' => 0,
            'status' => 'todo',
            'priority' => 1,
            'activation_cost' => 2,
            'sort_order' => 1,
        ]);

        return [$user, $plan, $task];
    }
}
