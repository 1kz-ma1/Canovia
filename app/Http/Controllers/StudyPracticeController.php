<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\StudyPracticeAttempt;
use App\Models\Task;
use App\Services\AiJsonInputNormalizer;
use App\Services\BehaviorIdentityService;
use App\Services\PlanOwnershipService;
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
        StudyPracticePromptService $promptService,
        BehaviorIdentityService $identity,
    ) {
        $this->authorizeTask($request, $plan, $task, $ownership);
        abort_unless(trim((string) $plan->category) === '資格学習', 404);

        $state = $request->session()->get($this->sessionKey($plan, $task), []);
        $actorToken = $identity->resolve($request);
        $attemptQuery = $this->attemptQuery($request, $plan, $task, $actorToken);
        $recentAttempts = (clone $attemptQuery)->latest('created_at')->latest('id')->take(5)->get();
        $currentAttempt = ! empty($state['attempt_id'])
            ? (clone $attemptQuery)->whereKey((int) $state['attempt_id'])->first()
            : null;
        $generationPrompt = $promptService->generationPrompt($plan, $task, $recentAttempts);

        return view('study_practice.show', [
            'plan' => $plan,
            'task' => $task,
            'generationPrompt' => $generationPrompt,
            'exerciseTitle' => $state['title'] ?? null,
            'questions' => $state['questions'] ?? [],
            'answers' => $state['answers'] ?? [],
            'evaluationPrompt' => $state['evaluation_prompt'] ?? null,
            'assessment' => $state['assessment'] ?? null,
            'currentAttempt' => $currentAttempt,
            'recentAttempts' => $recentAttempts,
        ]);
    }

    public function import(
        Request $request,
        Plan $plan,
        Task $task,
        PlanOwnershipService $ownership,
        AiJsonInputNormalizer $normalizer,
    ) {
        $this->authorizeTask($request, $plan, $task, $ownership);
        abort_unless(trim((string) $plan->category) === '資格学習', 404);

        $validated = $request->validate([
            'questions_json' => ['required', 'string', 'max:120000'],
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

        $request->session()->put($this->sessionKey($plan, $task), [
            'title' => $title !== '' ? mb_substr($title, 0, 120) : 'AI演習',
            'questions' => $questions,
            'answers' => [],
            'evaluation_prompt' => null,
            'assessment' => null,
            'attempt_id' => null,
            'attempt_token' => (string) Str::uuid(),
        ]);

        return redirect()
            ->route('plans.tasks.study_practice.show', [$plan, $task])
            ->with('success', count($questions).'問の演習を読み込みました。Canovia上で回答できます。');
    }

    public function submitAnswers(
        Request $request,
        Plan $plan,
        Task $task,
        PlanOwnershipService $ownership,
        StudyPracticePromptService $promptService,
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
        $answers = [];

        foreach ($questions as $question) {
            $id = (string) $question['id'];
            $value = $rawAnswers[$id] ?? null;

            if ($question['type'] === 'multiple_choice') {
                $value = is_array($value)
                    ? array_values(array_unique(array_map('strval', $value)))
                    : [];
                if ($value === []) {
                    throw ValidationException::withMessages(["answers.{$id}" => 'この問題に回答してください。']);
                }
            } else {
                $value = is_scalar($value) ? trim((string) $value) : '';
                if ($value === '') {
                    throw ValidationException::withMessages(["answers.{$id}" => 'この問題に回答してください。']);
                }
            }

            $answers[] = [
                'question_id' => $id,
                'answer' => $value,
            ];
        }

        $state['answers'] = $answers;
        $state['evaluation_prompt'] = $promptService->evaluationPrompt($plan, $task, $questions, $answers);
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
            'strengths' => $this->stringList($decoded['strengths'] ?? []),
            'weaknesses' => $this->stringList($decoded['weaknesses'] ?? []),
            'recommended_task_progress_percent' => $recommendedProgress,
            'evidence_summary' => mb_substr(trim((string) ($decoded['evidence_summary'] ?? '')), 0, 2000),
            'next_action' => mb_substr(trim((string) ($decoded['next_action'] ?? '')), 0, 1000),
        ];

        $actorToken = $identity->resolve($request);
        $identityScope = $request->user() ? 'user:'.(int) $request->user()->id : 'actor:'.$actorToken;
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

        $attempt = StudyPracticeAttempt::query()->createOrFirst(
            ['request_hash' => $requestHash],
            [
                'plan_id' => $plan->id,
                'task_id' => $task->id,
                'user_id' => $request->user()?->id,
                'actor_token' => $request->user() ? null : $actorToken,
                'exercise_title' => mb_substr((string) ($state['title'] ?? 'AI演習'), 0, 120),
                'questions' => $state['questions'] ?? [],
                'answers' => $state['answers'] ?? [],
                'assessment' => $assessment,
                'score_percent' => $assessment['score_percent'],
                'strengths' => $assessment['strengths'],
                'weaknesses' => $assessment['weaknesses'],
                'recommended_task_progress_percent' => $assessment['recommended_task_progress_percent'],
                'evidence_summary' => $assessment['evidence_summary'] ?: null,
                'next_action' => $assessment['next_action'] ?: null,
            ]
        );

        $state['assessment'] = $assessment;
        $state['attempt_id'] = $attempt->id;
        $request->session()->put($key, $state);

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
        }, 3);

        return redirect()
            ->route('plans.tasks.study_practice.show', [$plan, $task])
            ->with('success', $alreadyApplied
                ? 'この学習結果はすでにTaskへ反映済みです。重複反映は行いませんでした。'
                : '学習結果をTaskへ反映しました。弱点と次のActionは次回のAI演習にも引き継がれます。');
    }

    public function reset(Request $request, Plan $plan, Task $task, PlanOwnershipService $ownership)
    {
        $this->authorizeTask($request, $plan, $task, $ownership);
        abort_unless(trim((string) $plan->category) === '資格学習', 404);
        $request->session()->forget($this->sessionKey($plan, $task));

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
     * @return array<int, array<string, mixed>>
     */
    private function normalizeQuestions(mixed $raw): array
    {
        if (! is_array($raw) || $raw === [] || count($raw) > 20) {
            throw ValidationException::withMessages(['questions_json' => 'questionsは1〜20問で返してください。']);
        }

        $allowedTypes = ['single_choice', 'multiple_choice', 'text', 'number'];
        $questions = [];
        $seen = [];

        foreach (array_values($raw) as $index => $question) {
            if (! is_array($question)) {
                throw ValidationException::withMessages(['questions_json' => '各questionはJSONオブジェクトで返してください。']);
            }

            $id = trim((string) ($question['id'] ?? 'q'.($index + 1)));
            $type = trim((string) ($question['type'] ?? ''));
            $prompt = trim((string) ($question['prompt'] ?? ''));

            if ($id === '' || preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1 || isset($seen[$id])) {
                throw ValidationException::withMessages([
                    'questions_json' => 'question.idは英数字・_・-だけを使い、重複しない64文字以内の値にしてください。',
                ]);
            }
            if (! in_array($type, $allowedTypes, true)) {
                throw ValidationException::withMessages(['questions_json' => 'question.typeがCanoviaの対応形式ではありません。']);
            }
            if ($prompt === '') {
                throw ValidationException::withMessages(['questions_json' => '問題文が空のquestionがあります。']);
            }

            $choices = [];
            if (in_array($type, ['single_choice', 'multiple_choice'], true)) {
                $rawChoices = $question['choices'] ?? [];
                if (! is_array($rawChoices) || count($rawChoices) < 2 || count($rawChoices) > 6) {
                    throw ValidationException::withMessages(['questions_json' => '選択式問題のchoicesは2〜6件にしてください。']);
                }

                $seenChoiceIds = [];
                foreach (array_values($rawChoices) as $choiceIndex => $choice) {
                    if (! is_array($choice)) {
                        throw ValidationException::withMessages(['questions_json' => 'choiceはidとlabelを持つJSONオブジェクトにしてください。']);
                    }
                    $choiceId = trim((string) ($choice['id'] ?? chr(65 + $choiceIndex)));
                    $label = trim((string) ($choice['label'] ?? ''));
                    if (
                        $choiceId === ''
                        || preg_match('/^[A-Za-z0-9_-]{1,20}$/', $choiceId) !== 1
                        || isset($seenChoiceIds[$choiceId])
                        || $label === ''
                    ) {
                        throw ValidationException::withMessages([
                            'questions_json' => 'choice.idは英数字・_・-だけの重複しない20文字以内の値にし、labelも入力してください。',
                        ]);
                    }
                    $seenChoiceIds[$choiceId] = true;
                    $choices[] = ['id' => $choiceId, 'label' => mb_substr($label, 0, 1000)];
                }
            }

            $seen[$id] = true;
            $questions[] = [
                'id' => mb_substr($id, 0, 64),
                'type' => $type,
                'prompt' => mb_substr($prompt, 0, 4000),
                'choices' => $choices,
            ];
        }

        return $questions;
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

    private function sessionKey(Plan $plan, Task $task): string
    {
        return "study_practice.{$plan->id}.{$task->id}";
    }
}
