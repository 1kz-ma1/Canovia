<?php

namespace App\Http\Controllers;

use App\Enums\FeatureKey;
use App\Models\Plan;
use App\Models\StudyPracticeAttempt;
use App\Models\StudyPracticeSession;
use App\Models\Task;
use App\Services\AiJsonInputNormalizer;
use App\Services\BehaviorIdentityService;
use App\Services\FeatureAccessService;
use App\Services\PlanOwnershipService;
use App\Services\StudyPracticeOrchestrator;
use App\Services\StudyPracticePromptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StudyPracticeController extends Controller
{
    public function show(
        Request $request,
        Plan $plan,
        Task $task,
        PlanOwnershipService $ownership,
        StudyPracticeOrchestrator $orchestrator,
        BehaviorIdentityService $identity,
        FeatureAccessService $featureAccess,
    ) {
        $this->authorizeTask($request, $plan, $task, $ownership);
        abort_unless(trim((string) $plan->category) === '資格学習', 404);

        $key = $this->sessionKey($plan, $task);
        $state = $request->session()->get($key, []);
        if (! empty($state['questions']) && collect($state['questions'])->contains(
            fn ($question) => is_array($question) && empty($question['response_fields'])
        )) {
            $state['questions'] = $this->normalizeQuestions($state['questions']);
            $request->session()->put($key, $state);
        }

        $actorToken = $identity->resolve($request);
        $attemptQuery = $this->attemptQuery($request, $plan, $task, $actorToken);
        $recentAttempts = (clone $attemptQuery)->latest('created_at')->latest('id')->take(5)->get();
        $currentAttempt = ! empty($state['attempt_id'])
            ? (clone $attemptQuery)->whereKey((int) $state['attempt_id'])->first()
            : null;
        $currentPracticeSession = ! empty($state['practice_session_id'])
            ? $this->practiceSessionQuery($request, $plan, $task, $actorToken)
                ->whereKey((int) $state['practice_session_id'])
                ->first()
            : null;

        // If the browser/PHP session was interrupted while answering, recover the
        // newest resumable StudyPracticeSession silently. The answering screen
        // should feel continuous rather than asking the learner to "resume".
        if (! $currentPracticeSession && empty($state['questions'])) {
            $currentPracticeSession = $this->practiceSessionQuery($request, $plan, $task, $actorToken)
                ->whereIn('status', [
                    StudyPracticeSession::STATUS_READY,
                    StudyPracticeSession::STATUS_IN_PROGRESS,
                ])
                ->whereNotNull('questions_snapshot')
                ->latest('updated_at')
                ->latest('id')
                ->first();

            if (
                $currentPracticeSession
                && is_array($currentPracticeSession->questions_snapshot)
                && $currentPracticeSession->questions_snapshot !== []
            ) {
                $state = [
                    'title' => $currentPracticeSession->exercise_title ?: 'AI演習',
                    'questions' => $currentPracticeSession->questions_snapshot,
                    'answers' => [],
                    'draft_answers' => is_array($currentPracticeSession->draft_answers)
                        ? $currentPracticeSession->draft_answers
                        : [],
                    'evaluation_prompt' => null,
                    'assessment' => null,
                    'attempt_id' => null,
                    'attempt_token' => (string) Str::uuid(),
                    'practice_session_id' => $currentPracticeSession->id,
                ];
                $request->session()->put($key, $state);
            }
        }

        $questionPackAllowed = $featureAccess->canUse(
            $request->user(),
            FeatureKey::QuestionPack,
            ['plan_id' => (int) $plan->id, 'task_id' => (int) $task->id],
        );
        $orchestration = $orchestrator->previewHandoff(
            $plan,
            $task,
            $recentAttempts,
            $questionPackAllowed ? null : 'external_ai',
        );
        $practiceStrategy = $currentPracticeSession
            ? (array) data_get($currentPracticeSession->selection_context, 'strategy', $orchestration['strategy'])
            : $orchestration['strategy'];
        $practiceProvider = $currentPracticeSession
            ? [
                'provider' => $currentPracticeSession->question_provider,
                'mode' => $currentPracticeSession->question_provider_mode,
            ]
            : $orchestration['provider'];
        $generationPrompt = $currentPracticeSession
            ? (string) data_get($currentPracticeSession->provider_payload, 'generation_prompt', data_get($orchestration, 'provider.payload.generation_prompt', ''))
            : (string) data_get($orchestration, 'provider.payload.generation_prompt', '');
        $prepareRequestId = old('prepare_request_id')
            ?: ($currentPracticeSession?->prepare_request_id ?? (string) Str::uuid());
        $draftAnswers = $this->draftAnswersForView($state, $currentPracticeSession);

        return view('study_practice.show', [
            'plan' => $plan,
            'task' => $task,
            'generationPrompt' => $generationPrompt,
            'practiceStrategy' => $practiceStrategy,
            'practiceProvider' => $practiceProvider,
            'currentPracticeSession' => $currentPracticeSession,
            'prepareRequestId' => $prepareRequestId,
            'exerciseTitle' => $state['title'] ?? null,
            'questions' => $state['questions'] ?? [],
            'answers' => $state['answers'] ?? [],
            'draftAnswers' => $draftAnswers,
            'evaluationPrompt' => $state['evaluation_prompt'] ?? null,
            'assessment' => $state['assessment'] ?? null,
            'currentAttempt' => $currentAttempt,
            'recentAttempts' => $recentAttempts,
        ]);
    }

    public function prepare(
        Request $request,
        Plan $plan,
        Task $task,
        PlanOwnershipService $ownership,
        StudyPracticeOrchestrator $orchestrator,
        BehaviorIdentityService $identity,
        FeatureAccessService $featureAccess,
    ) {
        $this->authorizeTask($request, $plan, $task, $ownership);
        abort_unless(trim((string) $plan->category) === '資格学習', 404);
        $featureAccess->authorizeUse(
            $request->user(),
            FeatureKey::QuestionPack,
            ['plan_id' => (int) $plan->id, 'task_id' => (int) $task->id],
        );

        $validated = $request->validate([
            'prepare_request_id' => ['required', 'uuid'],
        ]);

        $actorToken = $identity->resolve($request);
        $attemptQuery = $this->attemptQuery($request, $plan, $task, $actorToken);
        $recentAttempts = (clone $attemptQuery)->latest('created_at')->latest('id')->take(5)->get();

        try {
            $practiceSession = $orchestrator->prepare(
                $plan,
                $task,
                $recentAttempts,
                $request->user()?->id,
                $request->user() ? null : $actorToken,
                (string) $validated['prepare_request_id'],
                'question_bank',
            );
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'prepare_request_id' => $exception->getMessage(),
            ]);
        }

        if ($practiceSession->question_provider_mode !== 'direct') {
            throw ValidationException::withMessages([
                'prepare_request_id' => 'Question Bankの準備状態が変わりました。画面を再読み込みして演習方法を確認してください。',
            ]);
        }

        $questions = data_get($practiceSession->provider_payload, 'questions', []);
        if (! is_array($questions) || $questions === []) {
            throw ValidationException::withMessages([
                'prepare_request_id' => 'Question Bankから問題を準備できませんでした。',
            ]);
        }

        $title = trim((string) data_get(
            $practiceSession->provider_payload,
            'title',
            'Canovia Question Bank演習',
        ));
        $exerciseTitle = $title !== '' ? mb_substr($title, 0, 120) : 'Canovia Question Bank演習';

        $practiceSession->update([
            'exercise_title' => $exerciseTitle,
            'questions_snapshot' => $questions,
        ]);

        $request->session()->put($this->sessionKey($plan, $task), [
            'title' => $exerciseTitle,
            'questions' => $questions,
            'answers' => [],
            'draft_answers' => is_array($practiceSession->draft_answers) ? $practiceSession->draft_answers : [],
            'evaluation_prompt' => null,
            'assessment' => null,
            'attempt_id' => null,
            'attempt_token' => (string) Str::uuid(),
            'practice_session_id' => $practiceSession->id,
        ]);

        return redirect()
            ->route('plans.tasks.study_practice.show', [$plan, $task])
            ->with('success', count($questions).'問をCanovia Question Bankから準備しました。');
    }

    public function import(
        Request $request,
        Plan $plan,
        Task $task,
        PlanOwnershipService $ownership,
        AiJsonInputNormalizer $normalizer,
        StudyPracticeOrchestrator $orchestrator,
        BehaviorIdentityService $identity,
    ) {
        $this->authorizeTask($request, $plan, $task, $ownership);
        abort_unless(trim((string) $plan->category) === '資格学習', 404);

        $validated = $request->validate([
            'questions_json' => ['required', 'string', 'max:120000'],
            'prepare_request_id' => ['nullable', 'uuid'],
        ]);

        try {
            $json = $normalizer->normalize($validated['questions_json']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['questions_json' => $exception->getMessage()]);
        }

        $decoded = json_decode($json, true);
        $this->assertEnvelope($decoded, 'study_practice', $plan, $task, 'questions_json');

        $questions = $this->normalizeQuestions($decoded['questions'] ?? null);
        $title = trim((string) ($decoded['title'] ?? 'AI演習'));
        $exerciseTitle = $title !== '' ? mb_substr($title, 0, 120) : 'AI演習';

        $actorToken = $identity->resolve($request);
        $attemptQuery = $this->attemptQuery($request, $plan, $task, $actorToken);
        $recentAttempts = (clone $attemptQuery)->latest('created_at')->latest('id')->take(5)->get();
        $practiceSession = $orchestrator->prepare(
            $plan,
            $task,
            $recentAttempts,
            $request->user()?->id,
            $request->user() ? null : $actorToken,
            (string) ($validated['prepare_request_id'] ?? Str::uuid()),
            'external_ai',
        );

        $practiceSession->update([
            'status' => StudyPracticeSession::STATUS_READY,
            'exercise_title' => $exerciseTitle,
            'selected_questions' => collect($questions)->map(fn (array $question) => [
                'question_ref' => (string) $question['id'],
                'question_id' => null,
                'source_type' => (string) $practiceSession->question_provider,
            ])->values()->all(),
            'questions_snapshot' => $questions,
            'draft_answers' => null,
            'draft_saved_at' => null,
        ]);

        $request->session()->put($this->sessionKey($plan, $task), [
            'title' => $exerciseTitle,
            'questions' => $questions,
            'answers' => [],
            'draft_answers' => [],
            'evaluation_prompt' => null,
            'assessment' => null,
            'attempt_id' => null,
            'attempt_token' => (string) Str::uuid(),
            'practice_session_id' => $practiceSession->id,
        ]);

        return redirect()
            ->route('plans.tasks.study_practice.show', [$plan, $task])
            ->with('success', count($questions).'問の演習を読み込みました。Canovia上で回答できます。');
    }

    public function saveDraft(
        Request $request,
        Plan $plan,
        Task $task,
        PlanOwnershipService $ownership,
        BehaviorIdentityService $identity,
    ) {
        $this->authorizeTask($request, $plan, $task, $ownership);
        abort_unless(trim((string) $plan->category) === '資格学習', 404);

        $validated = $request->validate([
            'practice_session_id' => ['required', 'integer', 'min:1'],
            'answers_json' => ['required', 'string', 'max:120000'],
        ]);

        $decoded = json_decode((string) $validated['answers_json'], true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'answers_json' => '途中回答を保存できませんでした。',
            ]);
        }

        $actorToken = $identity->resolve($request);
        $practiceSession = $this->practiceSessionQuery($request, $plan, $task, $actorToken)
            ->whereKey((int) $validated['practice_session_id'])
            ->whereIn('status', [
                StudyPracticeSession::STATUS_READY,
                StudyPracticeSession::STATUS_IN_PROGRESS,
            ])
            ->first();

        if (! $practiceSession) {
            return response()->noContent(409);
        }

        $questions = $practiceSession->questions_snapshot;
        if (! is_array($questions) || $questions === []) {
            $state = $request->session()->get($this->sessionKey($plan, $task), []);
            $questions = $state['questions'] ?? [];
        }

        if (! is_array($questions) || $questions === []) {
            return response()->noContent(409);
        }

        $draftAnswers = $this->normalizeDraftAnswers($decoded, $questions);
        $practiceSession->update([
            'status' => StudyPracticeSession::STATUS_IN_PROGRESS,
            'questions_snapshot' => $questions,
            'draft_answers' => $draftAnswers,
            'draft_saved_at' => now(),
        ]);

        $key = $this->sessionKey($plan, $task);
        $state = $request->session()->get($key, []);
        if ((int) ($state['practice_session_id'] ?? 0) === (int) $practiceSession->id) {
            $state['draft_answers'] = $draftAnswers;
            $request->session()->put($key, $state);
        }

        return response()->noContent();
    }

    public function submitAnswers(
        Request $request,
        Plan $plan,
        Task $task,
        PlanOwnershipService $ownership,
        StudyPracticePromptService $promptService,
        StudyPracticeOrchestrator $orchestrator,
        BehaviorIdentityService $identity,
    ) {
        $this->authorizeTask($request, $plan, $task, $ownership);
        abort_unless(trim((string) $plan->category) === '資格学習', 404);

        $key = $this->sessionKey($plan, $task);
        $state = $request->session()->get($key, []);
        $questions = $state['questions'] ?? [];

        if (! is_array($questions) || $questions === []) {
            return redirect()->route('plans.tasks.study_practice.show', [$plan, $task])
                ->withErrors(['answers' => '先に問題JSONを読み込んでください。']);
        }

        $rawAnswers = $request->input('answers', []);
        $draftAnswers = $this->normalizeDraftAnswers($rawAnswers, $questions);
        $answers = [];

        foreach ($questions as $question) {
            $questionId = (string) $question['id'];
            $questionInput = $rawAnswers[$questionId] ?? [];

            // Backward compatibility for old forms/tests that posted
            // answers[q1]=value instead of answers[q1][answer]=value.
            if (! is_array($questionInput)) {
                $questionInput = ['answer' => $questionInput];
            }

            $responseFields = $question['response_fields'] ?? [];
            if (! is_array($responseFields) || $responseFields === []) {
                $legacyType = (string) ($question['type'] ?? 'text');
                $responseFields = [[
                    'id' => 'answer',
                    'type' => match ($legacyType) {
                        'single_choice' => 'single_choice',
                        'multiple_choice' => 'multiple_choice',
                        'number' => 'number',
                        default => 'textarea',
                    },
                    'label' => '回答',
                    'required' => true,
                    'choices' => $question['choices'] ?? [],
                ]];
            }

            $fields = [];
            foreach ($responseFields as $field) {
                $fieldId = (string) $field['id'];
                $type = (string) $field['type'];
                $required = (bool) ($field['required'] ?? true);
                $value = $questionInput[$fieldId] ?? null;

                if ($type === 'multiple_choice') {
                    $value = is_array($value)
                        ? array_values(array_unique(array_map('strval', $value)))
                        : [];

                    $allowed = collect($field['choices'] ?? [])->pluck('id')->map('strval')->all();
                    $value = array_values(array_filter($value, fn ($item) => in_array($item, $allowed, true)));

                    if ($required && $value === []) {
                        throw ValidationException::withMessages([
                            "answers.{$questionId}.{$fieldId}" => ($field['label'] ?? '回答').'を入力してください。',
                        ]);
                    }
                } else {
                    $value = is_scalar($value) ? trim((string) $value) : '';

                    if ($required && $value === '') {
                        throw ValidationException::withMessages([
                            "answers.{$questionId}.{$fieldId}" => ($field['label'] ?? '回答').'を入力してください。',
                        ]);
                    }

                    if ($type === 'single_choice' && $value !== '') {
                        $allowed = collect($field['choices'] ?? [])->pluck('id')->map('strval')->all();
                        if (! in_array($value, $allowed, true)) {
                            throw ValidationException::withMessages([
                                "answers.{$questionId}.{$fieldId}" => '選択肢をもう一度選んでください。',
                            ]);
                        }
                    }

                    if ($type === 'number' && $value !== '' && ! is_numeric($value)) {
                        throw ValidationException::withMessages([
                            "answers.{$questionId}.{$fieldId}" => '数値で回答してください。',
                        ]);
                    }

                    $value = mb_substr($value, 0, $type === 'textarea' ? 12000 : 3000);
                }

                $fields[] = [
                    'field_id' => $fieldId,
                    'type' => $type,
                    'label' => $field['label'] ?? $fieldId,
                    'value' => $value,
                ];
            }

            $answers[] = [
                'question_id' => $questionId,
                'fields' => $fields,
            ];
        }

        $state['answers'] = $answers;
        $state['draft_answers'] = $draftAnswers;
        $actorToken = $identity->resolve($request);
        $practiceSession = ! empty($state['practice_session_id'])
            ? $this->practiceSessionQuery($request, $plan, $task, $actorToken)
                ->whereKey((int) $state['practice_session_id'])
                ->first()
            : null;

        if ($practiceSession) {
            $practiceSession->update([
                'draft_answers' => $draftAnswers,
                'draft_saved_at' => now(),
            ]);

            $assessmentHandoff = $orchestrator->prepareAssessment(
                $practiceSession,
                $plan,
                $task,
                $questions,
                $answers,
            );

            if ((string) ($assessmentHandoff['mode'] ?? '') === 'direct') {
                $assessment = data_get($assessmentHandoff, 'payload.assessment');

                if (! is_array($assessment)) {
                    throw ValidationException::withMessages([
                        'answers' => 'Canoviaの機械採点結果を作成できませんでした。',
                    ]);
                }

                $state['evaluation_prompt'] = null;
                $state['assessment'] = $assessment;
                $attempt = $this->persistAssessment(
                    $request,
                    $plan,
                    $task,
                    $state,
                    $assessment,
                    $actorToken,
                );
                $state['attempt_id'] = $attempt->id;
                $practiceSession->update(['status' => StudyPracticeSession::STATUS_ASSESSED]);
                $request->session()->put($key, $state);

                return redirect()
                    ->route('plans.tasks.study_practice.show', [$plan, $task])
                    ->with('success', 'Canovia Question Bankの採点ルールで評価しました。結果を確認できます。');
            }

            $state['evaluation_prompt'] = (string) data_get(
                $assessmentHandoff,
                'payload.evaluation_prompt',
                ''
            );
            $practiceSession->update(['status' => StudyPracticeSession::STATUS_ANSWERED]);
        } else {
            // V40 compatibility for an in-progress browser session created
            // before StudyPracticeSession existed.
            $state['evaluation_prompt'] = $promptService->evaluationPrompt($plan, $task, $questions, $answers);
        }

        $state['assessment'] = null;
        $state['attempt_id'] = null;
        $request->session()->put($key, $state);

        return redirect()
            ->route('plans.tasks.study_practice.show', [$plan, $task])
            ->with('success', '回答をまとめました。評価用プロンプトをAIへ送ってください。');
    }

    public function previewAssessment(
        Request $request,
        Plan $plan,
        Task $task,
        PlanOwnershipService $ownership,
        AiJsonInputNormalizer $normalizer,
        BehaviorIdentityService $identity,
    ) {
        $this->authorizeTask($request, $plan, $task, $ownership);
        abort_unless(trim((string) $plan->category) === '資格学習', 404);

        $validated = $request->validate([
            'assessment_json' => ['required', 'string', 'max:80000'],
        ]);

        try {
            $json = $normalizer->normalize($validated['assessment_json']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['assessment_json' => $exception->getMessage()]);
        }

        $decoded = json_decode($json, true);
        $this->assertEnvelope($decoded, 'study_assessment', $plan, $task, 'assessment_json');

        $key = $this->sessionKey($plan, $task);
        $state = $request->session()->get($key, []);
        if (empty($state['evaluation_prompt']) || empty($state['answers'])) {
            throw ValidationException::withMessages([
                'assessment_json' => '先にCanovia上で問題へ回答し、評価用プロンプトを生成してください。',
            ]);
        }

        $score = filter_var($decoded['score_percent'] ?? null, FILTER_VALIDATE_INT);
        $recommendedProgress = filter_var($decoded['recommended_task_progress_percent'] ?? null, FILTER_VALIDATE_INT);

        if ($score === false || $score < 0 || $score > 100) {
            throw ValidationException::withMessages(['assessment_json' => 'score_percentは0〜100の整数で返してください。']);
        }
        if ($recommendedProgress === false || $recommendedProgress < 0 || $recommendedProgress > 100) {
            throw ValidationException::withMessages(['assessment_json' => 'recommended_task_progress_percentは0〜100の整数で返してください。']);
        }

        $assessment = [
            'score_percent' => $score,
            'question_feedback' => $this->normalizeQuestionFeedback(
                $decoded['question_feedback'] ?? [],
                $state['questions'] ?? [],
            ),
            'strengths' => $this->stringList($decoded['strengths'] ?? []),
            'weaknesses' => $this->stringList($decoded['weaknesses'] ?? []),
            'recommended_task_progress_percent' => $recommendedProgress,
            'evidence_summary' => mb_substr(trim((string) ($decoded['evidence_summary'] ?? '')), 0, 2000),
            'next_action' => mb_substr(trim((string) ($decoded['next_action'] ?? '')), 0, 1000),
        ];

        $actorToken = $identity->resolve($request);
        $attempt = $this->persistAssessment(
            $request,
            $plan,
            $task,
            $state,
            $assessment,
            $actorToken,
        );
        $state['assessment'] = $assessment;
        $state['attempt_id'] = $attempt->id;
        $request->session()->put($key, $state);

        if (! empty($state['practice_session_id'])) {
            $this->practiceSessionQuery($request, $plan, $task, $actorToken)
                ->whereKey((int) $state['practice_session_id'])
                ->update(['status' => StudyPracticeSession::STATUS_ASSESSED]);
        }

        return redirect()
            ->route('plans.tasks.study_practice.show', [$plan, $task])
            ->with('success', $attempt->wasRecentlyCreated
                ? 'AIの評価を学習履歴へ保存しました。内容を確認してTaskへ反映できます。'
                : '同じ評価はすでに保存済みです。既存の学習履歴を開きました。');
    }

    public function applyAssessment(
        Request $request,
        Plan $plan,
        Task $task,
        PlanOwnershipService $ownership,
        BehaviorIdentityService $identity,
    ) {
        $this->authorizeTask($request, $plan, $task, $ownership);
        abort_unless(trim((string) $plan->category) === '資格学習', 404);

        $validated = $request->validate([
            'attempt_id' => ['required', 'integer', 'min:1'],
            'request_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $actorToken = $identity->resolve($request);
        $alreadyApplied = false;

        DB::transaction(function () use ($request, $plan, $task, $validated, $actorToken, &$alreadyApplied) {
            $attempt = $this->attemptQuery($request, $plan, $task, $actorToken)
                ->whereKey((int) $validated['attempt_id'])
                ->where('request_hash', $validated['request_hash'])
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                throw ValidationException::withMessages([
                    'attempt_id' => 'この学習結果を確認できません。AI演習画面からもう一度開いてください。',
                ]);
            }

            if ($attempt->applied_at) {
                $alreadyApplied = true;

                if ($attempt->study_practice_session_id) {
                    StudyPracticeSession::query()
                        ->whereKey($attempt->study_practice_session_id)
                        ->where('plan_id', $plan->id)
                        ->where('task_id', $task->id)
                        ->update([
                            'status' => StudyPracticeSession::STATUS_COMPLETED,
                            'completed_at' => now(),
                        ]);
                }

                return;
            }

            $lockedTask = Task::query()
                ->where('plan_id', $plan->id)
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedTask->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'attempt_id' => 'このTaskは現在中止されています。学習結果を反映するには、先にTask状態を見直してください。',
                ]);
            }

            $progressBefore = (int) $lockedTask->progress_percent;
            $progressAfter = max($progressBefore, (int) $attempt->recommended_task_progress_percent);
            $remainingAfter = $progressAfter >= 100 ? 0 : $lockedTask->remaining_minutes;
            $statusAfter = match (true) {
                $progressAfter >= 100 => 'done',
                $progressAfter > 0 => 'doing',
                default => $lockedTask->status,
            };
            $reason = trim('AI演習 '.$attempt->score_percent.'%'.(
                $attempt->evidence_summary ? '：'.$attempt->evidence_summary : ''
            ));

            $lockedTask->update([
                'progress_percent' => $progressAfter,
                'remaining_minutes' => $remainingAfter,
                'progress_reason' => mb_substr($reason, 0, 4000),
                'next_action_note' => $attempt->next_action ?: $lockedTask->next_action_note,
                'status' => $statusAfter,
            ]);

            $attempt->update([
                'progress_before_percent' => $progressBefore,
                'progress_after_percent' => $progressAfter,
                'applied_at' => now(),
            ]);

            if ($attempt->study_practice_session_id) {
                StudyPracticeSession::query()
                    ->whereKey($attempt->study_practice_session_id)
                    ->where('plan_id', $plan->id)
                    ->where('task_id', $task->id)
                    ->update([
                        'status' => StudyPracticeSession::STATUS_COMPLETED,
                        'completed_at' => now(),
                    ]);
            }
        }, 3);

        return redirect()
            ->route('plans.tasks.study_practice.show', [$plan, $task])
            ->with('success', $alreadyApplied
                ? 'この学習結果はすでにTaskへ反映済みです。重複反映は行いませんでした。'
                : '学習結果をTaskへ反映しました。弱点と次のActionは次回のAI演習にも引き継がれます。');
    }

    public function reset(
        Request $request,
        Plan $plan,
        Task $task,
        PlanOwnershipService $ownership,
        BehaviorIdentityService $identity,
    ) {
        $this->authorizeTask($request, $plan, $task, $ownership);
        abort_unless(trim((string) $plan->category) === '資格学習', 404);

        $key = $this->sessionKey($plan, $task);
        $state = $request->session()->get($key, []);
        if (! empty($state['practice_session_id'])) {
            $actorToken = $identity->resolve($request);
            $this->practiceSessionQuery($request, $plan, $task, $actorToken)
                ->whereKey((int) $state['practice_session_id'])
                ->whereNotIn('status', [StudyPracticeSession::STATUS_COMPLETED, StudyPracticeSession::STATUS_ABANDONED])
                ->update(['status' => StudyPracticeSession::STATUS_ABANDONED]);
        }

        $request->session()->forget($key);

        return redirect()
            ->route('plans.tasks.study_practice.show', [$plan, $task])
            ->with('status', 'この演習をリセットしました。');
    }

    private function authorizeTask(Request $request, Plan $plan, Task $task, PlanOwnershipService $ownership): void
    {
        abort_unless((int) $task->plan_id === (int) $plan->id, 404);
        $ownership->authorizeTask($request, $task);
    }

    private function assertEnvelope(?array $decoded, string $flow, Plan $plan, Task $task, string $field): void
    {
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([$field => 'JSONオブジェクトを読み取れませんでした。']);
        }
        if (($decoded['flow'] ?? null) !== $flow) {
            throw ValidationException::withMessages([$field => "flowは{$flow}である必要があります。"]);
        }
        if ((int) data_get($decoded, 'target_plan.id') !== (int) $plan->id) {
            throw ValidationException::withMessages([$field => '別のPlan向けJSONです。開いているPlanのIDを変更せずAIへ返してください。']);
        }
        if ((int) data_get($decoded, 'target_task.id') !== (int) $task->id) {
            throw ValidationException::withMessages([$field => '別のTask向けJSONです。開いているTaskのIDを変更せずAIへ返してください。']);
        }
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, array<string, mixed>>
     */
    private function draftAnswersForView(array $state, ?StudyPracticeSession $practiceSession): array
    {
        if ($practiceSession && is_array($practiceSession->draft_answers)) {
            return $practiceSession->draft_answers;
        }

        if (is_array($state['draft_answers'] ?? null)) {
            return $state['draft_answers'];
        }

        $draft = [];
        foreach (($state['answers'] ?? []) as $answer) {
            if (! is_array($answer)) {
                continue;
            }

            $questionId = trim((string) ($answer['question_id'] ?? ''));
            if ($questionId === '') {
                continue;
            }

            if (is_array($answer['fields'] ?? null)) {
                foreach ($answer['fields'] as $field) {
                    if (! is_array($field)) {
                        continue;
                    }

                    $fieldId = trim((string) ($field['field_id'] ?? ''));
                    if ($fieldId !== '') {
                        $draft[$questionId][$fieldId] = $field['value'] ?? '';
                    }
                }
            } elseif (array_key_exists('answer', $answer)) {
                $draft[$questionId]['answer'] = $answer['answer'];
            }
        }

        return $draft;
    }

    /**
     * Keep partial answers safe without requiring the learner to have completed
     * every required field. Final validation still happens in submitAnswers().
     *
     * @param array<int, array<string, mixed>> $questions
     * @return array<string, array<string, mixed>>
     */
    private function normalizeDraftAnswers(mixed $raw, array $questions): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $draft = [];

        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }

            $questionId = (string) ($question['id'] ?? '');
            if ($questionId === '' || ! array_key_exists($questionId, $raw)) {
                continue;
            }

            $questionInput = $raw[$questionId];
            if (! is_array($questionInput)) {
                $questionInput = ['answer' => $questionInput];
            }

            foreach (($question['response_fields'] ?? []) as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $fieldId = (string) ($field['id'] ?? '');
                $type = (string) ($field['type'] ?? 'textarea');
                if ($fieldId === '' || ! array_key_exists($fieldId, $questionInput)) {
                    continue;
                }

                $value = $questionInput[$fieldId];

                if ($type === 'multiple_choice') {
                    $allowed = collect($field['choices'] ?? [])->pluck('id')->map('strval')->all();
                    $values = is_array($value) ? array_map('strval', $value) : [];
                    $draft[$questionId][$fieldId] = array_values(array_unique(
                        array_filter($values, fn ($item) => in_array($item, $allowed, true))
                    ));
                    continue;
                }

                $value = is_scalar($value) ? (string) $value : '';

                if ($type === 'single_choice') {
                    $allowed = collect($field['choices'] ?? [])->pluck('id')->map('strval')->all();
                    $value = in_array($value, $allowed, true) ? $value : '';
                }

                $draft[$questionId][$fieldId] = mb_substr(
                    $value,
                    0,
                    $type === 'textarea' ? 12000 : 3000,
                );
            }
        }

        return $draft;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeQuestions(mixed $raw): array
    {
        if (! is_array($raw) || $raw === [] || count($raw) > 20) {
            throw ValidationException::withMessages(['questions_json' => 'questionsは1〜20問で返してください。']);
        }

        $questions = [];
        $seen = [];

        foreach (array_values($raw) as $index => $question) {
            if (! is_array($question)) {
                throw ValidationException::withMessages(['questions_json' => '各questionはJSONオブジェクトで返してください。']);
            }

            $id = trim((string) ($question['id'] ?? 'q'.($index + 1)));
            $prompt = trim((string) ($question['prompt'] ?? ''));

            if ($id === '' || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1 || isset($seen[$id])) {
                throw ValidationException::withMessages([
                    'questions_json' => 'question.idは英数字・_・-だけを使い、重複しない64文字以内の値にしてください。',
                ]);
            }
            if ($prompt === '') {
                throw ValidationException::withMessages(['questions_json' => '問題文が空のquestionがあります。']);
            }

            $rawFields = $question['response_fields'] ?? null;

            if (! is_array($rawFields) || $rawFields === []) {
                // V39 compatibility: convert type + choices into one response field.
                $legacyType = trim((string) ($question['type'] ?? ''));
                $legacyMap = [
                    'single_choice' => 'single_choice',
                    'multiple_choice' => 'multiple_choice',
                    'number' => 'number',
                    'text' => 'textarea',
                ];

                if (! isset($legacyMap[$legacyType])) {
                    throw ValidationException::withMessages([
                        'questions_json' => 'response_fieldsを指定するか、互換形式のquestion.typeを使用してください。',
                    ]);
                }

                $rawFields = [[
                    'id' => 'answer',
                    'type' => $legacyMap[$legacyType],
                    'label' => '回答',
                    'required' => true,
                    'choices' => $question['choices'] ?? [],
                ]];
            }

            if (count($rawFields) < 1 || count($rawFields) > 4) {
                throw ValidationException::withMessages([
                    'questions_json' => '各questionのresponse_fieldsは1〜4件にしてください。',
                ]);
            }

            $fields = [];
            $seenFields = [];
            foreach (array_values($rawFields) as $fieldIndex => $field) {
                $fields[] = $this->normalizeResponseField($field, $fieldIndex, $seenFields);
            }

            if (! collect($fields)->contains(fn ($field) => (bool) ($field['required'] ?? false))) {
                throw ValidationException::withMessages([
                    'questions_json' => '各questionには最低1つrequired=trueのresponse_fieldが必要です。',
                ]);
            }

            $seen[$id] = true;
            $first = $fields[0];
            $legacyType = match ($first['type']) {
                'short_text', 'textarea' => 'text',
                default => $first['type'],
            };

            $questions[] = [
                'id' => mb_substr($id, 0, 64),
                'prompt' => mb_substr($prompt, 0, 4000),
                'response_fields' => $fields,
                // Compatibility keys remain while old attempts and consumers exist.
                'type' => $legacyType,
                'choices' => $first['choices'] ?? [],
            ];
        }

        return $questions;
    }

    /**
     * @param array<string, bool> $seenFields
     * @return array<string, mixed>
     */
    private function normalizeResponseField(mixed $raw, int $index, array &$seenFields): array
    {
        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                'questions_json' => 'response_fieldはJSONオブジェクトで返してください。',
            ]);
        }

        $allowed = ['single_choice', 'multiple_choice', 'number', 'short_text', 'textarea'];
        $id = trim((string) ($raw['id'] ?? 'field'.($index + 1)));
        $type = trim((string) ($raw['type'] ?? ''));
        $label = trim((string) ($raw['label'] ?? '回答'));
        $requiredRaw = $raw['required'] ?? true;
        $required = is_bool($requiredRaw)
            ? $requiredRaw
            : ! in_array(mb_strtolower(trim((string) $requiredRaw)), ['false', '0', 'no'], true);

        if ($id === '' || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1 || isset($seenFields[$id])) {
            throw ValidationException::withMessages([
                'questions_json' => 'response_field.idは英数字・_・-だけを使い、question内で重複しない64文字以内の値にしてください。',
            ]);
        }
        if (! in_array($type, $allowed, true)) {
            throw ValidationException::withMessages([
                'questions_json' => 'response_field.typeがCanoviaの対応形式ではありません。',
            ]);
        }
        if ($label === '') {
            $label = '回答';
        }

        $choices = [];
        if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
            $rawChoices = $raw['choices'] ?? [];
            if (! is_array($rawChoices) || count($rawChoices) < 2 || count($rawChoices) > 6) {
                throw ValidationException::withMessages([
                    'questions_json' => '選択式response_fieldのchoicesは2〜6件にしてください。',
                ]);
            }

            $seenChoiceIds = [];
            foreach (array_values($rawChoices) as $choiceIndex => $choice) {
                if (! is_array($choice)) {
                    throw ValidationException::withMessages([
                        'questions_json' => 'choiceはidとlabelを持つJSONオブジェクトにしてください。',
                    ]);
                }

                $choiceId = trim((string) ($choice['id'] ?? chr(65 + $choiceIndex)));
                $choiceLabel = trim((string) ($choice['label'] ?? ''));

                if (
                    $choiceId === ''
                    || preg_match('/^[\p{L}\p{N}_-]{1,20}$/u', $choiceId) !== 1
                    || isset($seenChoiceIds[$choiceId])
                    || $choiceLabel === ''
                ) {
                    throw ValidationException::withMessages([
                        'questions_json' => 'choice.idは文字・数字・_・-だけの重複しない20文字以内の値にし、labelも入力してください。',
                    ]);
                }

                $seenChoiceIds[$choiceId] = true;
                $choices[] = ['id' => $choiceId, 'label' => mb_substr($choiceLabel, 0, 1000)];
            }
        }

        $seenFields[$id] = true;

        return [
            'id' => $id,
            'type' => $type,
            'label' => mb_substr($label, 0, 120),
            'required' => $required,
            'placeholder' => mb_substr(trim((string) ($raw['placeholder'] ?? '')), 0, 240),
            'choices' => $choices,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $questions
     * @return array<int, array<string, mixed>>
     */
    private function normalizeQuestionFeedback(mixed $raw, array $questions): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $questionIds = collect($questions)->pluck('id')->map('strval')->all();
        $allowedCorrectness = ['correct', 'partial', 'incorrect', 'ungraded'];
        $result = [];
        $seen = [];

        foreach (array_values($raw) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $questionId = trim((string) ($item['question_id'] ?? ''));
            if ($questionId === '' || ! in_array($questionId, $questionIds, true) || isset($seen[$questionId])) {
                continue;
            }

            $correctness = trim((string) ($item['correctness'] ?? 'ungraded'));
            if (! in_array($correctness, $allowedCorrectness, true)) {
                $correctness = 'ungraded';
            }

            $seen[$questionId] = true;
            $result[] = [
                'question_id' => $questionId,
                'correctness' => $correctness,
                'feedback' => mb_substr(trim((string) ($item['feedback'] ?? '')), 0, 2000),
                'reasoning_feedback' => mb_substr(trim((string) ($item['reasoning_feedback'] ?? '')), 0, 2000),
                'misconceptions' => array_slice($this->stringList($item['misconceptions'] ?? []), 0, 8),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item) => is_scalar($item) && trim((string) $item) !== '')
            ->map(fn ($item) => mb_substr(trim((string) $item), 0, 1000))
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $assessment
     */
    private function persistAssessment(
        Request $request,
        Plan $plan,
        Task $task,
        array &$state,
        array $assessment,
        string $actorToken,
    ): StudyPracticeAttempt {
        $identityScope = $request->user()
            ? 'user:'.(int) $request->user()->id
            : 'actor:'.$actorToken;
        $attemptToken = (string) ($state['attempt_token'] ?? Str::uuid());
        $state['attempt_token'] = $attemptToken;

        $requestHash = hash('sha256', json_encode([
            'identity' => $identityScope,
            'attempt_token' => $attemptToken,
            'plan_id' => (int) $plan->id,
            'task_id' => (int) $task->id,
            'questions' => $state['questions'] ?? [],
            'answers' => $state['answers'] ?? [],
            'assessment' => $assessment,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return StudyPracticeAttempt::query()->createOrFirst(
            ['request_hash' => $requestHash],
            [
                'study_practice_session_id' => ! empty($state['practice_session_id'])
                    ? (int) $state['practice_session_id']
                    : null,
                'plan_id' => $plan->id,
                'task_id' => $task->id,
                'user_id' => $request->user()?->id,
                'actor_token' => $request->user() ? null : $actorToken,
                'exercise_title' => mb_substr((string) ($state['title'] ?? 'AI演習'), 0, 120),
                'questions' => $state['questions'] ?? [],
                'answers' => $state['answers'] ?? [],
                'assessment' => $assessment,
                'score_percent' => (int) ($assessment['score_percent'] ?? 0),
                'strengths' => $assessment['strengths'] ?? [],
                'weaknesses' => $assessment['weaknesses'] ?? [],
                'recommended_task_progress_percent' => (int) ($assessment['recommended_task_progress_percent'] ?? $task->progress_percent),
                'evidence_summary' => filled($assessment['evidence_summary'] ?? null)
                    ? (string) $assessment['evidence_summary']
                    : null,
                'next_action' => filled($assessment['next_action'] ?? null)
                    ? (string) $assessment['next_action']
                    : null,
            ]
        );
    }

    private function attemptQuery(Request $request, Plan $plan, Task $task, string $actorToken)
    {
        $query = StudyPracticeAttempt::query()
            ->where('plan_id', $plan->id)
            ->where('task_id', $task->id);

        if ($request->user()) {
            return $query->where('user_id', $request->user()->id);
        }

        return $query->whereNull('user_id')->where('actor_token', $actorToken);
    }

    private function practiceSessionQuery(Request $request, Plan $plan, Task $task, string $actorToken)
    {
        $query = StudyPracticeSession::query()
            ->where('plan_id', $plan->id)
            ->where('task_id', $task->id);

        if ($request->user()) {
            return $query->where('user_id', $request->user()->id);
        }

        return $query->whereNull('user_id')->where('actor_token', $actorToken);
    }

    private function sessionKey(Plan $plan, Task $task): string
    {
        return "study_practice.{$plan->id}.{$task->id}";
    }
}
