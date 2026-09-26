<?php

namespace App\Http\Controllers;

use App\Models\PracticeQuestionCandidate;
use App\Models\PracticeQuestionDemand;
use App\Models\QuestionPack;
use App\Services\AdminAccessService;
use App\Services\PracticeQuestionCandidatePromotionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminPracticeDemandController extends Controller
{
    public function __construct(private readonly AdminAccessService $access) {}

    public function index(Request $request)
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['7', '30', '90', 'all'])],
            'exam_profile' => ['nullable', 'string', 'max:80'],
            'assembly_mode' => ['nullable', 'string', 'max:32'],
            'candidate_status' => ['nullable', Rule::in(array_merge(['all'], PracticeQuestionCandidate::STATUSES))],
        ]);

        $period = (string) ($validated['period'] ?? '30');
        $examProfile = trim((string) ($validated['exam_profile'] ?? ''));
        $assemblyMode = trim((string) ($validated['assembly_mode'] ?? ''));
        $candidateStatus = (string) ($validated['candidate_status'] ?? PracticeQuestionCandidate::STATUS_PENDING);

        $query = PracticeQuestionDemand::query();
        if ($period !== 'all') {
            $query->where('created_at', '>=', now()->subDays((int) $period));
        }
        if ($examProfile !== '') {
            $query->where('exam_profile_key', $examProfile);
        }
        if ($assemblyMode !== '') {
            $query->where('assembly_mode', $assemblyMode);
        }

        $sessionCount = (clone $query)->count();
        $requestedCount = (int) (clone $query)->sum('requested_count');
        $bankSelectedCount = (int) (clone $query)->sum('bank_selected_count');
        $generatedRequestedCount = (int) (clone $query)->sum('generated_requested_count');
        $generatedCount = (int) (clone $query)->sum('generated_count');

        $analysisLimit = 5000;
        $analysisRows = (clone $query)
            ->latest('created_at')
            ->limit($analysisLimit)
            ->get([
                'id',
                'exam_profile_key',
                'assembly_mode',
                'requested_count',
                'bank_selected_count',
                'generated_requested_count',
                'generated_count',
                'focus_topics',
                'created_at',
            ]);

        $profileStats = $analysisRows
            ->groupBy(fn (PracticeQuestionDemand $demand) => $demand->exam_profile_key ?: 'unclassified')
            ->map(function ($rows, string $key) {
                $requested = (int) $rows->sum('requested_count');
                $bank = (int) $rows->sum('bank_selected_count');
                $gap = (int) $rows->sum('generated_requested_count');

                return [
                    'key' => $key,
                    'sessions' => $rows->count(),
                    'requested_count' => $requested,
                    'bank_selected_count' => $bank,
                    'generated_requested_count' => $gap,
                    'bank_fill_rate' => $requested > 0 ? round(($bank / $requested) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('generated_requested_count')
            ->values()
            ->take(20);

        $topicStats = [];
        foreach ($analysisRows as $demand) {
            $topics = collect($demand->focus_topics ?? [])
                ->filter(fn ($topic) => is_string($topic) && trim($topic) !== '')
                ->map(fn ($topic) => trim($topic))
                ->unique();

            foreach ($topics as $topic) {
                $topicStats[$topic] ??= [
                    'topic' => $topic,
                    'sessions' => 0,
                    'gap_sessions' => 0,
                    'bank_only_sessions' => 0,
                    'latest_at' => null,
                ];

                $topicStats[$topic]['sessions']++;
                if ((int) $demand->generated_requested_count > 0) {
                    $topicStats[$topic]['gap_sessions']++;
                } else {
                    $topicStats[$topic]['bank_only_sessions']++;
                }

                if (
                    $topicStats[$topic]['latest_at'] === null
                    || $demand->created_at?->gt($topicStats[$topic]['latest_at'])
                ) {
                    $topicStats[$topic]['latest_at'] = $demand->created_at;
                }
            }
        }

        $topicStats = collect($topicStats)
            ->map(function (array $stat) {
                $stat['gap_rate'] = $stat['sessions'] > 0
                    ? round(($stat['gap_sessions'] / $stat['sessions']) * 100, 1)
                    : 0.0;

                return $stat;
            })
            ->sort(function (array $left, array $right) {
                return [
                    $right['gap_sessions'],
                    $right['sessions'],
                    (string) $right['topic'],
                ] <=> [
                    $left['gap_sessions'],
                    $left['sessions'],
                    (string) $left['topic'],
                ];
            })
            ->values()
            ->take(30);

        $demands = (clone $query)
            ->latest('created_at')
            ->paginate(30, ['*'], 'demand_page')
            ->withQueryString();

        $candidateQuery = PracticeQuestionCandidate::query()
            ->with(['latestDemand', 'promotedPack', 'promotedQuestion'])
            ->latest('last_seen_at')
            ->latest('id');

        if ($candidateStatus !== 'all') {
            $candidateQuery->where('status', $candidateStatus);
        }

        $candidates = $candidateQuery
            ->paginate(20, ['*'], 'candidate_page')
            ->withQueryString();

        $candidateCounts = collect(PracticeQuestionCandidate::STATUSES)
            ->mapWithKeys(fn (string $status) => [
                $status => PracticeQuestionCandidate::query()->where('status', $status)->count(),
            ]);

        $availableProfiles = PracticeQuestionDemand::query()
            ->whereNotNull('exam_profile_key')
            ->distinct()
            ->orderBy('exam_profile_key')
            ->pluck('exam_profile_key');

        $availableAssemblyModes = PracticeQuestionDemand::query()
            ->distinct()
            ->orderBy('assembly_mode')
            ->pluck('assembly_mode');

        return view('admin.practice_demand.index', [
            'period' => $period,
            'examProfile' => $examProfile,
            'assemblyMode' => $assemblyMode,
            'candidateStatus' => $candidateStatus,
            'availableProfiles' => $availableProfiles,
            'availableAssemblyModes' => $availableAssemblyModes,
            'summary' => [
                'sessions' => $sessionCount,
                'requested_count' => $requestedCount,
                'bank_selected_count' => $bankSelectedCount,
                'generated_requested_count' => $generatedRequestedCount,
                'generated_count' => $generatedCount,
                'bank_fill_rate' => $requestedCount > 0
                    ? round(($bankSelectedCount / $requestedCount) * 100, 1)
                    : 0.0,
                'generation_fill_rate' => $generatedRequestedCount > 0
                    ? round(($generatedCount / $generatedRequestedCount) * 100, 1)
                    : 100.0,
            ],
            'profileStats' => $profileStats,
            'topicStats' => $topicStats,
            'demands' => $demands,
            'analysisCapped' => $sessionCount > $analysisLimit,
            'candidateCounts' => $candidateCounts,
            'candidates' => $candidates,
        ]);
    }

    public function showCandidate(Request $request, PracticeQuestionCandidate $candidate)
    {
        $this->ensureAuthorized($request);

        $candidate->load(['latestDemand', 'promotedPack', 'promotedQuestion']);
        $draftPacks = QuestionPack::query()
            ->where('status', 'draft')
            ->withCount('questions')
            ->orderBy('title')
            ->get();

        $payload = is_array($candidate->question_payload) ? $candidate->question_payload : [];
        $fields = collect($payload['response_fields'] ?? [])->filter(fn ($field) => is_array($field))->values();
        $answerField = $fields->first(fn ($field) => ($field['type'] ?? null) !== 'textarea') ?? $fields->first();
        $fieldId = trim((string) data_get($answerField, 'id', 'answer'));
        $fieldType = (string) data_get($answerField, 'type', 'single_choice');

        $gradingRule = match ($fieldType) {
            'multiple_choice' => [
                'type' => 'exact_multiple',
                'field_id' => $fieldId,
                'answers' => [],
            ],
            'number' => [
                'type' => 'numeric_tolerance',
                'field_id' => $fieldId,
                'answer' => 0,
                'tolerance' => 0,
            ],
            'short_text', 'textarea' => [
                'type' => 'ai_rubric',
                'field_id' => $fieldId,
                'rubric' => '',
            ],
            default => [
                'type' => 'exact_choice',
                'field_id' => $fieldId,
                'answer' => '',
            ],
        };

        $focusTopics = collect(data_get($candidate->review_hints, 'focus_topics', []))
            ->filter(fn ($topic) => is_string($topic) && trim($topic) !== '')
            ->map(fn ($topic) => trim($topic))
            ->unique()
            ->values()
            ->all();

        $learningMetadata = [
            'concepts' => [],
            'weakness_targets' => $focusTopics,
            'tags' => array_values(array_filter([$candidate->exam_profile_key])),
            'keywords' => [],
        ];

        return view('admin.practice_demand.candidate', [
            'candidate' => $candidate,
            'draftPacks' => $draftPacks,
            'gradingRuleTemplate' => json_encode(
                $gradingRule,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ),
            'learningMetadataTemplate' => json_encode(
                $learningMetadata,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
            ),
        ]);
    }

    public function promoteCandidate(
        Request $request,
        PracticeQuestionCandidate $candidate,
        PracticeQuestionCandidatePromotionService $promotion,
    ) {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'question_pack_id' => ['required', 'integer', 'exists:question_packs,id'],
            'external_key' => ['nullable', 'string', 'max:120'],
            'grading_rule_json' => ['required', 'string', 'max:30000'],
            'learning_metadata_json' => ['nullable', 'string', 'max:30000'],
            'explanation' => ['nullable', 'string', 'max:12000'],
            'difficulty' => ['required', 'integer', 'min:1', 'max:5'],
            'source_reference' => ['nullable', 'string', 'max:500'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $gradingRule = $this->decodeJsonObject(
            (string) $validated['grading_rule_json'],
            'grading_rule_json',
            'grading_rule',
        );
        $learningMetadata = trim((string) ($validated['learning_metadata_json'] ?? '')) === ''
            ? []
            : $this->decodeJsonObject(
                (string) $validated['learning_metadata_json'],
                'learning_metadata_json',
                'learning_metadata',
            );

        $pack = QuestionPack::query()->findOrFail((int) $validated['question_pack_id']);
        $question = $promotion->promote(
            $candidate,
            $pack,
            [
                'external_key' => $validated['external_key'] ?? null,
                'grading_rule' => $gradingRule,
                'learning_metadata' => $learningMetadata,
                'explanation' => $validated['explanation'] ?? null,
                'difficulty' => (int) $validated['difficulty'],
                'source_reference' => $validated['source_reference'] ?? null,
                'review_note' => $validated['review_note'] ?? null,
            ],
            $request->user(),
        );

        return redirect()
            ->route('admin.practice_demand.candidates.show', $candidate)
            ->with('status', "{$pack->title} のDraftへ {$question->external_key} として昇格しました。");
    }

    public function rejectCandidate(Request $request, PracticeQuestionCandidate $candidate)
    {
        $this->ensureAuthorized($request);

        if ($candidate->status !== PracticeQuestionCandidate::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'candidate' => '確認待ちのCandidateだけを見送りできます。',
            ]);
        }

        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:2000'],
        ]);

        $candidate->update([
            'status' => PracticeQuestionCandidate::STATUS_REJECTED,
            'review_data' => [
                'review_note' => trim((string) $validated['review_note']),
            ],
            'reviewed_by_user_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route('admin.practice_demand.candidates.show', $candidate)
            ->with('status', 'Candidateを見送りにしました。再確認が必要なら確認待ちへ戻せます。');
    }

    public function reopenCandidate(Request $request, PracticeQuestionCandidate $candidate)
    {
        $this->ensureAuthorized($request);

        if ($candidate->status !== PracticeQuestionCandidate::STATUS_REJECTED) {
            throw ValidationException::withMessages([
                'candidate' => '見送り中のCandidateだけを確認待ちへ戻せます。',
            ]);
        }

        $candidate->update([
            'status' => PracticeQuestionCandidate::STATUS_PENDING,
            'review_data' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ]);

        return redirect()
            ->route('admin.practice_demand.candidates.show', $candidate)
            ->with('status', 'Candidateを確認待ちへ戻しました。');
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJsonObject(string $raw, string $field, string $label): array
    {
        $decoded = json_decode(trim($raw), true);
        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                $field => "{$label}をJSONオブジェクトとして読み取れませんでした。",
            ]);
        }

        return $decoded;
    }

    private function ensureAuthorized(Request $request): void
    {
        if (! $this->access->authorized($request)) {
            abort(403);
        }
    }
}
