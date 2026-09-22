<?php

namespace App\Http\Controllers;

use App\Models\QuestionPack;
use App\Services\AdminAccessService;
use App\Services\AiJsonInputNormalizer;
use App\Services\QuestionPackImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class AdminQuestionPackController extends Controller
{
    public function __construct(private readonly AdminAccessService $access) {}

    public function index(Request $request)
    {
        $this->ensureAuthorized($request);

        $packs = QuestionPack::query()
            ->withCount('questions')
            ->withCount([
                'questions as active_questions_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->latest('updated_at')
            ->paginate(30);

        $template = [
            'schema_version' => '1.0',
            'pack' => [
                'slug' => 'ap-a-v1',
                'title' => '応用情報技術者試験 科目A',
                'exam_code' => 'AP',
                'subject' => '科目A',
                'version' => '1',
                'downloadable' => true,
                'metadata' => [
                    'match_terms' => ['AP', '応用情報', '応用情報技術者試験'],
                    'locale' => 'ja-JP',
                ],
            ],
            'questions' => [[
                'external_key' => 'sample-001',
                'source_type' => 'official',
                'source_reference' => '出典を年度・区分・問番号などで記載',
                'prompt' => '問題文',
                'response_schema' => [[
                    'id' => 'answer',
                    'type' => 'single_choice',
                    'label' => '回答',
                    'required' => true,
                    'choices' => [
                        ['id' => 'A', 'label' => '選択肢A'],
                        ['id' => 'B', 'label' => '選択肢B'],
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
                    'keywords' => ['名前解決'],
                ],
                'explanation' => '正答の理由・復習用解説',
                'difficulty' => 3,
                'sort_order' => 1,
                'is_active' => true,
            ]],
        ];

        return view('admin.question_packs.index', [
            'packs' => $packs,
            'importTemplate' => json_encode(
                $template,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ),
        ]);
    }

    public function import(
        Request $request,
        AiJsonInputNormalizer $normalizer,
        QuestionPackImportService $importer,
    ) {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'pack_json' => ['required', 'string', 'max:2500000'],
        ]);

        try {
            $json = $normalizer->normalize($validated['pack_json']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'pack_json' => $exception->getMessage(),
            ]);
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'pack_json' => 'Question Pack JSONを読み取れませんでした。',
            ]);
        }

        $result = $importer->import($decoded);

        return redirect()
            ->route('admin.question_packs.index')
            ->with(
                'status',
                "{$result['pack']->title} を取り込みました。新規 {$result['created']}問 / 更新 {$result['updated']}問 / 無効化 {$result['deactivated']}問です."
            );
    }

    public function updateStatus(Request $request, QuestionPack $questionPack)
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(QuestionPack::STATUSES)],
        ]);

        $status = (string) $validated['status'];

        if ($status === 'published') {
            $activeCount = $questionPack->questions()->where('is_active', true)->count();
            if ($activeCount === 0) {
                throw ValidationException::withMessages([
                    'status' => '公開するには有効な問題が1問以上必要です。',
                ]);
            }

            $metadata = collect($questionPack->metadata ?? []);
            $hasRoutingTerm = trim((string) $questionPack->exam_code) !== ''
                || collect($metadata->get('match_terms', []))->filter()->isNotEmpty();

            if (! $hasRoutingTerm) {
                throw ValidationException::withMessages([
                    'status' => '公開するにはexam_codeまたはmetadata.match_termsが必要です。',
                ]);
            }
        }

        $questionPack->update(['status' => $status]);

        return back()->with(
            'status',
            "{$questionPack->title} の状態を {$status} へ変更しました。"
        );
    }

    private function ensureAuthorized(Request $request): void
    {
        if (! $this->access->authorized($request)) {
            abort(403);
        }
    }
}
