@extends('layouts.app')

@section('title', 'AI演習 | Canovia')

@section('content')
    <div class="mx-auto max-w-5xl space-y-5">
        <section class="page-card border-cyan-300/20 p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-cyan-300">CANOVIA TOOL / AI PRACTICE</p>
                    <h1 class="mt-2 text-2xl font-black text-slate-50">AI演習</h1>
                    <p class="mt-2 text-sm text-slate-400">{{ $plan->displayIcon() }} {{ $plan->title }} / {{ $task->title }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('plans.show', $plan) }}" class="btn-secondary">Planへ戻る</a>
                    @if ($questions)
                        <form method="POST" action="{{ route('plans.tasks.study_practice.reset', [$plan, $task]) }}">
                            @csrf
                            <button type="submit" class="btn-secondary">演習をやり直す</button>
                        </form>
                    @endif
                </div>
            </div>
            <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-300">CanoviaがTaskと学習履歴から今回の演習方針を決め、問題ソースを自動選択します。Question Bankで十分にカバーできる場合はCanovia内で直接出題・採点し、不足する場合だけ外部AIへ引き継ぎます。</p>

            <div class="mt-4 rounded-2xl border border-cyan-300/15 bg-cyan-300/[0.04] p-4" data-guide-target="practice-strategy">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-cyan-300">PRACTICE STRATEGY</p>
                        <h2 class="mt-1 text-base font-black text-slate-100">{{ $practiceStrategy['label'] ?? 'Task理解度確認' }}</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-400">{{ $practiceStrategy['reason'] ?? '' }}</p>
                    </div>
                    <span class="badge badge-slate">{{ (int) ($practiceStrategy['target_question_count'] ?? 10) }}問目安</span>
                </div>

                @if (collect($practiceStrategy['focus_topics'] ?? [])->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (($practiceStrategy['focus_topics'] ?? []) as $topic)
                            <span class="badge badge-slate">{{ $topic }}</span>
                        @endforeach
                    </div>
                @endif

                @php
                    $providerKey = $practiceProvider['provider'] ?? 'external_ai';
                    $providerLabel = match ($providerKey) {
                        'question_bank' => 'Canovia Question Bank',
                        'external_ai' => '外部AI',
                        default => $providerKey,
                    };
                    $providerPackTitle = data_get($practiceProvider, 'payload.pack.title')
                        ?: data_get($currentPracticeSession?->provider_payload, 'pack.title');
                @endphp
                <p class="mt-3 text-[11px] leading-5 text-slate-500">
                    問題ソース：{{ $providerLabel }}
                    @if ($providerPackTitle)
                        · {{ $providerPackTitle }}
                    @endif
                    @if ($currentPracticeSession)
                        · Session #{{ $currentPracticeSession->id }}
                    @endif
                </p>
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-300/20 bg-emerald-300/[0.06] px-4 py-3 text-sm text-emerald-100">{{ session('success') }}</div>
        @endif
        @if (session('status'))
            <div class="rounded-2xl border border-slate-700 bg-slate-900/70 px-4 py-3 text-sm text-slate-300">{{ session('status') }}</div>
        @endif

        @php
            $questionJsonError = $errors->first('questions_json');
            $questionJsonRepairPrompt = $questionJsonError ? implode("\n", [
                'CanoviaのAI演習・問題JSONでエラーが発生しました。',
                '下の「元のCanovia問題作成プロンプト」を仕様と対象Plan・Taskの唯一の正として扱ってください。',
                'エラー解消に必要な箇所だけ修正し、問題文・response_fields・選択肢・難易度・出題意図など正しい内容はできるだけ保持してください。',
                'schema_versionは"1.0"、flowは"study_practice"のままにしてください。',
                'target_plan.idは '.$plan->id.'、target_task.idは '.$task->id.' のままにし、別のIDを推測・生成しないでください。',
                '修正後はJSONとして構文解析できることを確認してください。',
                '説明文・Markdown・コードフェンス・コメントを付けず、有効なJSONだけを返してください。',
                '',
                '【Canoviaのエラー】',
                $questionJsonError,
                '',
                '【エラーになったJSON】',
                old('questions_json', ''),
                '',
                '【元のCanovia問題作成プロンプト】',
                $generationPrompt,
            ]) : null;

            $assessmentJsonError = $errors->first('assessment_json');
            $assessmentJsonRepairPrompt = $assessmentJsonError ? implode("\n", [
                'CanoviaのAI演習・評価JSONでエラーが発生しました。',
                '下の「元のCanovia評価プロンプト」を仕様と対象Plan・Taskの唯一の正として扱ってください。',
                'エラー解消に必要な箇所だけ修正し、採点結果・question_feedback・思考過程フィードバック・強み・弱点・評価根拠・次のActionなど正しい内容はできるだけ保持してください。',
                'schema_versionは"1.0"、flowは"study_assessment"のままにしてください。',
                'target_plan.idは '.$plan->id.'、target_task.idは '.$task->id.' のままにし、別のIDを推測・生成しないでください。',
                'score_percentとrecommended_task_progress_percentは0〜100の整数にしてください。',
                '修正後はJSONとして構文解析できることを確認してください。',
                '説明文・Markdown・コードフェンス・コメントを付けず、有効なJSONだけを返してください。',
                '',
                '【Canoviaのエラー】',
                $assessmentJsonError,
                '',
                '【エラーになったJSON】',
                old('assessment_json', ''),
                '',
                '【元のCanovia評価プロンプト】',
                $evaluationPrompt ?: '（評価プロンプトを取得できませんでした。Canoviaで回答をまとめ直してください。）',
            ]) : null;
        @endphp

        @if (! $questions)
            @if (($practiceProvider['mode'] ?? 'handoff') === 'direct')
                <section class="page-card border-emerald-300/20 p-5 sm:p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-emerald-300/10 text-sm font-black text-emerald-200">1</span>
                        <div>
                            <h2 class="font-black text-slate-100">Canovia問題集から演習を始める</h2>
                            <p class="text-xs text-slate-500">今回のTaskをQuestion Bankだけで十分にカバーできます。外部AIへのコピペは不要です。</p>
                        </div>
                    </div>

                    @if (data_get($practiceProvider, 'payload.coverage.required_count'))
                        <div class="mt-4 grid gap-2 sm:grid-cols-3">
                            <div class="rounded-xl border border-slate-800 bg-slate-950/35 p-3">
                                <p class="text-[10px] text-slate-500">今回の問題数</p>
                                <strong class="mt-1 block text-lg text-slate-100">{{ data_get($practiceProvider, 'payload.coverage.required_count') }}</strong>
                            </div>
                            <div class="rounded-xl border border-slate-800 bg-slate-950/35 p-3">
                                <p class="text-[10px] text-slate-500">Pack内の有効問題</p>
                                <strong class="mt-1 block text-lg text-slate-100">{{ data_get($practiceProvider, 'payload.coverage.active_count') }}</strong>
                            </div>
                            <div class="rounded-xl border border-slate-800 bg-slate-950/35 p-3">
                                <p class="text-[10px] text-slate-500">重点分野一致</p>
                                <strong class="mt-1 block text-lg text-slate-100">{{ data_get($practiceProvider, 'payload.coverage.focus_match_count') }}</strong>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('plans.tasks.study_practice.prepare', [$plan, $task]) }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="prepare_request_id" value="{{ $prepareRequestId }}">
                        <button type="submit" class="btn-primary">演習を始める</button>
                    </form>
                </section>
            @else
                <section class="page-card p-5 sm:p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-cyan-300/10 text-sm font-black text-cyan-200">1</span>
                        <div>
                            <h2 class="font-black text-slate-100">演習問題を準備する</h2>
                            <p class="text-xs text-slate-500">Question BankのCoverageが足りないため、今回だけ外部AIへ引き継ぎます。</p>
                        </div>
                    </div>

                    <textarea id="studyPracticeGenerationPrompt" readonly tabindex="-1" aria-hidden="true" class="sr-only">{{ $generationPrompt }}</textarea>

                    <div class="mt-4 rounded-2xl border border-cyan-300/15 bg-cyan-300/[0.035] p-4">
                        <p class="text-sm font-semibold text-slate-100">AIへ渡す準備はCanovia側で完了しています</p>
                        <p class="mt-1 text-xs leading-5 text-slate-400">原文を読む必要はありません。普段使っているAIへ、そのままコピーして送ってください。</p>
                        <button type="button" class="btn-primary mt-4" data-copy-target="#studyPracticeGenerationPrompt">演習準備プロンプトをコピー</button>

                        <details class="ai-handoff-details mt-4 rounded-xl border border-slate-800 bg-slate-950/35 p-3">
                            <summary class="cursor-pointer text-xs font-semibold text-slate-300">このプロンプトに含まれる情報</summary>
                            <ul class="mt-3 space-y-2 text-xs leading-5 text-slate-500">
                                <li>・Plan / TaskのID・タイトル・説明・現在進捗</li>
                                <li>・Canoviaが決めた今回の演習方針と重点分野</li>
                                <li>・最近の演習履歴、弱点、次Action</li>
                                <li>・問題数、回答UI、JSON形式の制約</li>
                                <li>・対象Plan / Taskを変更しないための安全条件</li>
                            </ul>
                        </details>
                    </div>
                </section>

                <section class="page-card p-5 sm:p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid h-8 w-8 place-items-center rounded-full bg-cyan-300/10 text-sm font-black text-cyan-200">2</span>
                        <div>
                            <h2 class="font-black text-slate-100">問題をCanoviaへ戻す</h2>
                            <p class="text-xs text-slate-500">AI側で返答をコピーしたら、Canoviaでは1回押すだけで貼り付けと読み込みを進められます。</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('plans.tasks.study_practice.import', [$plan, $task]) }}" class="mt-4">
                        @csrf
                        <input type="hidden" name="prepare_request_id" value="{{ $prepareRequestId }}">

                        <button
                            type="button"
                            class="btn-primary"
                            data-paste-target="#questions_json"
                            data-paste-submit="1"
                            data-paste-fallback="#questionJsonManualInput"
                            data-paste-status="#questionJsonPasteStatus"
                        >クリップボードから貼り付けて読み込む</button>
                        <p id="questionJsonPasteStatus" class="mt-2 hidden text-xs leading-5 text-slate-400" aria-live="polite"></p>
                        @error('questions_json')<p class="mt-2 text-sm font-semibold text-rose-300">{{ $message }}</p>@enderror

                        <details id="questionJsonManualInput" class="ai-handoff-details mt-4 rounded-xl border border-slate-800 bg-slate-950/35 p-3" @if($questionJsonError || old('questions_json')) open @endif>
                            <summary class="cursor-pointer text-xs font-semibold text-slate-300">手動で貼り付ける / JSONを確認する</summary>
                            <textarea id="questions_json" name="questions_json" class="form-control mt-3 min-h-[180px] font-mono text-xs" placeholder="AIが返したJSONを貼り付け">{{ old('questions_json') }}</textarea>
                            <button type="submit" class="btn-secondary mt-3">このJSONを読み込む</button>
                        </details>

                        @if ($questionJsonRepairPrompt)
                            <div class="mt-4 rounded-2xl border border-rose-300/20 bg-rose-300/[0.04] p-4">
                                <p class="text-sm font-black text-rose-100">修正依頼を作りました</p>
                                <p class="mt-1 text-xs leading-5 text-slate-400">最初に問題を作ったAIへ修正依頼を送り、返ってきたJSONをもう一度貼り付けてください。</p>
                                <textarea id="studyPracticeQuestionRepairPrompt" readonly tabindex="-1" aria-hidden="true" class="sr-only">{{ $questionJsonRepairPrompt }}</textarea>
                                <button type="button" class="btn-primary mt-3" data-copy-target="#studyPracticeQuestionRepairPrompt">修正依頼をコピー</button>
                                <details class="ai-handoff-details mt-3 rounded-xl border border-rose-300/10 bg-slate-950/25 p-3">
                                    <summary class="cursor-pointer text-xs font-semibold text-rose-100/80">修正依頼に含まれる情報</summary>
                                    <p class="mt-2 text-xs leading-5 text-slate-500">Canoviaのエラー内容、返されたJSON、元の問題作成Prompt、正しいPlan / Task IDを含みます。</p>
                                </details>
                            </div>
                        @endif
                    </form>
                </section>
            @endif
        @endif

        @if ($questions)
            <section class="page-card p-5 sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-cyan-300/10 text-sm font-black text-cyan-200">{{ ($currentPracticeSession?->question_provider_mode ?? '') === 'direct' ? '2' : '3' }}</span>
                    <div>
                        <h2 class="font-black text-slate-100">{{ $exerciseTitle ?: '演習に回答' }}</h2>
                        <p class="text-xs text-slate-500">
                            {{ count($questions) }}問。
                            {{ ($currentPracticeSession?->question_provider ?? '') === 'question_bank' ? '機械採点できる問題はCanoviaがその場で採点します。' : '回答は評価用プロンプトへまとめられます。' }}
                        </p>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('plans.tasks.study_practice.answers', [$plan, $task]) }}"
                    class="mt-5 space-y-4"
                    @if ($currentPracticeSession && in_array($currentPracticeSession->status, ['ready', 'in_progress'], true))
                        data-study-practice-draft-form
                        data-draft-url="{{ route('plans.tasks.study_practice.draft', [$plan, $task]) }}"
                        data-draft-session-id="{{ $currentPracticeSession->id }}"
                        data-draft-session-token="{{ $currentPracticeSession->session_token }}"
                        data-draft-saved-at="{{ $currentPracticeSession->draft_saved_at?->toIso8601String() }}"
                    @endif
                >
                    @csrf
                    @foreach ($questions as $index => $question)
                        <fieldset class="rounded-2xl border border-white/8 bg-white/[0.025] p-4">
                            <legend class="px-1 text-sm font-black text-slate-100">Q{{ $index + 1 }}</legend>
                            <p class="canovia-study-question mt-2 whitespace-pre-wrap text-sm leading-7 text-slate-200">{{ $question['prompt'] }}</p>
                            @if (! empty($question['source_reference']))
                                <p class="mt-2 text-[10px] leading-4 text-slate-600">出典：{{ $question['source_reference'] }}</p>
                            @endif

                            <div class="mt-4 space-y-4">
                                @foreach (($question['response_fields'] ?? []) as $field)
                                    @php
                                        $fieldName = 'answers['.$question['id'].']['.$field['id'].']';
                                        $fieldError = 'answers.'.$question['id'].'.'.$field['id'];
                                        $fieldValue = old(
                                            $fieldError,
                                            data_get($draftAnswers ?? [], $question['id'].'.'.$field['id'])
                                        );
                                        $fieldValues = is_array($fieldValue)
                                            ? array_map('strval', $fieldValue)
                                            : [];
                                    @endphp
                                    <div class="rounded-xl border border-slate-800/80 bg-slate-950/30 p-3">
                                        <label class="text-xs font-bold text-slate-300">
                                            {{ $field['label'] }}
                                            <span class="ml-1 text-[10px] {{ ($field['required'] ?? true) ? 'text-cyan-300' : 'text-slate-600' }}">
                                                {{ ($field['required'] ?? true) ? '必須' : '任意' }}
                                            </span>
                                        </label>

                                        @if ($field['type'] === 'single_choice')
                                            <div class="mt-2 grid gap-2">
                                                @foreach ($field['choices'] as $choice)
                                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-300">
                                                        <input type="radio" name="{{ $fieldName }}" value="{{ $choice['id'] }}" class="mt-1" @checked((string) $fieldValue === (string) $choice['id'])>
                                                        <span><strong class="text-slate-100">{{ $choice['id'] }}</strong> {{ $choice['label'] }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($field['type'] === 'multiple_choice')
                                            <div class="mt-2 grid gap-2">
                                                @foreach ($field['choices'] as $choice)
                                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-300">
                                                        <input type="checkbox" name="{{ $fieldName }}[]" value="{{ $choice['id'] }}" class="mt-1" @checked(in_array((string) $choice['id'], $fieldValues, true))>
                                                        <span><strong class="text-slate-100">{{ $choice['id'] }}</strong> {{ $choice['label'] }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($field['type'] === 'number')
                                            <input type="number" step="any" name="{{ $fieldName }}" value="{{ $fieldValue }}" class="form-control mt-2" placeholder="{{ $field['placeholder'] ?: '数値を入力' }}">
                                        @elseif ($field['type'] === 'short_text')
                                            <input type="text" name="{{ $fieldName }}" value="{{ $fieldValue }}" class="form-control mt-2" placeholder="{{ $field['placeholder'] ?: '短く回答' }}">
                                        @else
                                            <textarea name="{{ $fieldName }}" class="form-control mt-2 min-h-28" placeholder="{{ $field['placeholder'] ?: '回答・考え方を入力' }}">{{ $fieldValue }}</textarea>
                                        @endif

                                        @error($fieldError)<p class="mt-2 text-sm font-semibold text-rose-300">{{ $message }}</p>@enderror
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    <button type="submit" class="btn-primary">回答をまとめて評価へ進む</button>
                </form>
            </section>
        @endif

        @if ($evaluationPrompt)
            <section class="page-card p-5 sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-violet-300/10 text-sm font-black text-violet-200">4</span>
                    <div>
                        <h2 class="font-black text-slate-100">AIに採点・評価してもらう</h2>
                        <p class="text-xs text-slate-500">問題とあなたの回答はCanoviaが評価依頼へまとめています。原文を読む必要はありません。</p>
                    </div>
                </div>

                <textarea id="studyPracticeEvaluationPrompt" readonly tabindex="-1" aria-hidden="true" class="sr-only">{{ $evaluationPrompt }}</textarea>

                <div class="mt-4 rounded-2xl border border-violet-300/15 bg-violet-300/[0.035] p-4">
                    <button type="button" class="btn-primary" data-copy-target="#studyPracticeEvaluationPrompt">評価プロンプトをコピー</button>
                    <details class="ai-handoff-details mt-4 rounded-xl border border-slate-800 bg-slate-950/35 p-3">
                        <summary class="cursor-pointer text-xs font-semibold text-slate-300">この評価依頼に含まれる情報</summary>
                        <ul class="mt-3 space-y-2 text-xs leading-5 text-slate-500">
                            <li>・今回出題された問題と回答内容</li>
                            <li>・選択回答とは別に、入力した計算過程・判断理由</li>
                            <li>・Question Bank問題では正答Rule・解説・学習メタデータ</li>
                            <li>・問題ごとの正誤、弱点、次Actionを返す評価形式</li>
                            <li>・正しいPlan / Task IDと進捗反映の安全条件</li>
                        </ul>
                    </details>
                </div>

                <form method="POST" action="{{ route('plans.tasks.study_practice.assessment', [$plan, $task]) }}" class="mt-5 border-t border-slate-800 pt-5">
                    @csrf

                    <p class="text-sm font-semibold text-slate-100">AIの評価をCanoviaへ戻す</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">AI側で評価JSONをコピーしてから、下のボタンを押してください。</p>

                    <button
                        type="button"
                        class="btn-primary mt-3"
                        data-paste-target="#assessment_json"
                        data-paste-submit="1"
                        data-paste-fallback="#assessmentJsonManualInput"
                        data-paste-status="#assessmentJsonPasteStatus"
                    >クリップボードから貼り付けて確認</button>
                    <p id="assessmentJsonPasteStatus" class="mt-2 hidden text-xs leading-5 text-slate-400" aria-live="polite"></p>
                    @error('assessment_json')<p class="mt-2 text-sm font-semibold text-rose-300">{{ $message }}</p>@enderror

                    <details id="assessmentJsonManualInput" class="ai-handoff-details mt-4 rounded-xl border border-slate-800 bg-slate-950/35 p-3" @if($assessmentJsonError || old('assessment_json')) open @endif>
                        <summary class="cursor-pointer text-xs font-semibold text-slate-300">手動で貼り付ける / 評価JSONを確認する</summary>
                        <textarea id="assessment_json" name="assessment_json" class="form-control mt-3 min-h-[180px] font-mono text-xs" placeholder="評価JSONを貼り付け">{{ old('assessment_json') }}</textarea>
                        <button type="submit" class="btn-secondary mt-3">この評価JSONを確認する</button>
                    </details>

                    @if ($assessmentJsonRepairPrompt)
                        <div class="mt-4 rounded-2xl border border-rose-300/20 bg-rose-300/[0.04] p-4">
                            <p class="text-sm font-black text-rose-100">評価JSONの修正依頼を作りました</p>
                            <p class="mt-1 text-xs leading-5 text-slate-400">評価を作ったAIへ修正依頼を送り、返ってきたJSONをもう一度貼り付けてください。</p>
                            <textarea id="studyPracticeAssessmentRepairPrompt" readonly tabindex="-1" aria-hidden="true" class="sr-only">{{ $assessmentJsonRepairPrompt }}</textarea>
                            <button type="button" class="btn-primary mt-3" data-copy-target="#studyPracticeAssessmentRepairPrompt">修正依頼をコピー</button>
                            <details class="ai-handoff-details mt-3 rounded-xl border border-rose-300/10 bg-slate-950/25 p-3">
                                <summary class="cursor-pointer text-xs font-semibold text-rose-100/80">修正依頼に含まれる情報</summary>
                                <p class="mt-2 text-xs leading-5 text-slate-500">Canoviaのエラー内容、評価JSON、元の評価Prompt、正しいPlan / Task IDを含みます。</p>
                            </details>
                        </div>
                    @endif
                </form>
            </section>
        @endif

        @if ($assessment)
            <section class="page-card border-emerald-300/20 p-5 sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-300">ASSESSMENT PREVIEW</p>
                <div class="mt-3 flex flex-wrap items-end gap-4">
                    <div><p class="text-xs text-slate-500">今回の評価</p><strong class="text-4xl text-slate-50">{{ $assessment['score_percent'] }}%</strong></div>
                    <div>
                        <p class="text-xs text-slate-500">{{ ($currentPracticeSession?->assessment_provider ?? '') === 'question_bank_grader' ? 'Task進捗（自動変更なし）' : '評価提案のTask進捗' }}</p>
                        <strong class="text-2xl text-cyan-200">{{ $assessment['recommended_task_progress_percent'] }}%</strong>
                    </div>
                    @if ($currentAttempt?->applied_at)
                        <span class="badge badge-green">Taskへ反映済み</span>
                    @else
                        <span class="badge badge-slate">確認待ち</span>
                    @endif
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-emerald-300/15 bg-emerald-300/[0.04] p-4">
                        <h3 class="font-bold text-emerald-100">理解できている点</h3>
                        <ul class="mt-2 space-y-2 text-sm text-slate-300">@forelse($assessment['strengths'] as $item)<li>・{{ $item }}</li>@empty<li class="text-slate-500">記載なし</li>@endforelse</ul>
                    </div>
                    <div class="rounded-2xl border border-amber-300/15 bg-amber-300/[0.04] p-4">
                        <h3 class="font-bold text-amber-100">補強する点</h3>
                        <ul class="mt-2 space-y-2 text-sm text-slate-300">@forelse($assessment['weaknesses'] as $item)<li>・{{ $item }}</li>@empty<li class="text-slate-500">記載なし</li>@endforelse</ul>
                    </div>
                </div>
                @if (collect($assessment['question_feedback'] ?? [])->isNotEmpty())
                    <div class="mt-5 space-y-3">
                        <h3 class="text-sm font-black text-slate-100">問題ごとのフィードバック</h3>
                        @foreach ($assessment['question_feedback'] as $feedback)
                            @php
                                $correctnessLabel = match ($feedback['correctness'] ?? 'ungraded') {
                                    'correct' => '正解',
                                    'partial' => '一部正解',
                                    'incorrect' => '要復習',
                                    default => '評価対象外',
                                };
                                $correctnessClass = match ($feedback['correctness'] ?? 'ungraded') {
                                    'correct' => 'badge-green',
                                    'partial' => 'badge-slate',
                                    'incorrect' => 'badge-amber',
                                    default => 'badge-slate',
                                };
                            @endphp
                            <article class="rounded-2xl border border-slate-800 bg-slate-950/35 p-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong class="text-sm text-slate-100">{{ $feedback['question_id'] }}</strong>
                                    <span class="badge {{ $correctnessClass }}">{{ $correctnessLabel }}</span>
                                </div>
                                @if ($feedback['feedback'])
                                    <p class="mt-2 text-sm leading-6 text-slate-300">{{ $feedback['feedback'] }}</p>
                                @endif
                                @if ($feedback['reasoning_feedback'])
                                    <div class="mt-2 rounded-xl border border-violet-300/15 bg-violet-300/[0.04] p-3">
                                        <p class="text-[11px] font-bold text-violet-200">思考過程フィードバック</p>
                                        <p class="mt-1 text-xs leading-5 text-slate-300">{{ $feedback['reasoning_feedback'] }}</p>
                                    </div>
                                @endif
                                @if (collect($feedback['misconceptions'] ?? [])->isNotEmpty())
                                    <p class="mt-2 text-xs leading-5 text-amber-100">誤解ポイント：{{ collect($feedback['misconceptions'])->implode(' / ') }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif

                @if ($assessment['evidence_summary'])
                    <div class="mt-4 rounded-xl border border-slate-800 bg-slate-950/35 p-4"><p class="text-xs font-bold text-slate-500">評価根拠</p><p class="mt-1 text-sm leading-6 text-slate-300">{{ $assessment['evidence_summary'] }}</p></div>
                @endif
                @if ($assessment['next_action'])
                    <div class="mt-3 rounded-xl border border-cyan-300/15 bg-cyan-300/[0.04] p-4"><p class="text-xs font-bold text-cyan-300">次のAction</p><p class="mt-1 text-sm font-semibold text-slate-100">{{ $assessment['next_action'] }}</p></div>
                @endif
                @if ($currentAttempt)
                    @if ($currentAttempt->applied_at)
                        <div class="mt-4 rounded-xl border border-emerald-300/15 bg-emerald-300/[0.04] p-4">
                            <p class="text-sm font-bold text-emerald-100">Taskへ反映済み</p>
                            <p class="mt-1 text-xs text-slate-400">進捗 {{ $currentAttempt->progress_before_percent ?? '—' }}% → {{ $currentAttempt->progress_after_percent ?? '—' }}%。AI演習だけを理由に、既存の進捗を下げることはありません。</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('plans.tasks.study_practice.apply', [$plan, $task]) }}" class="mt-4 rounded-xl border border-cyan-300/20 bg-cyan-300/[0.04] p-4" data-mutation-once>
                            @csrf
                            <input type="hidden" name="attempt_id" value="{{ $currentAttempt->id }}">
                            <input type="hidden" name="request_hash" value="{{ $currentAttempt->request_hash }}">
                            <p class="text-sm font-bold text-cyan-100">この結果をCanoviaへ反映しますか？</p>
                            <p class="mt-1 text-xs leading-5 text-slate-400">Task進捗は現在値と評価提案の高い方を使うため、演習結果だけで進捗が後退することはありません。評価根拠と次のActionもTaskへ残します。</p>
                            <button type="submit" class="btn-primary mt-3">この学習結果をTaskへ反映</button>
                        </form>
                    @endif
                @endif
            </section>
        @endif

        @if (($recentAttempts ?? collect())->isNotEmpty())
            <section class="page-card p-5 sm:p-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-violet-300">LEARNING HISTORY</p>
                    <h2 class="mt-1 text-lg font-black text-slate-100">このTaskのAI演習履歴</h2>
                    <p class="mt-1 text-xs text-slate-500">ここで見つかった弱点は、次回の問題生成Promptへ自動で引き継がれます。</p>
                </div>
                <div class="mt-4 space-y-3">
                    @foreach ($recentAttempts as $attempt)
                        <article class="rounded-2xl border border-white/8 bg-white/[0.025] p-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <strong class="text-slate-100">{{ $attempt->exercise_title ?: 'AI演習' }}</strong>
                                    <p class="mt-1 text-xs text-slate-500">{{ $attempt->created_at?->format('Y-m-d H:i') }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="badge badge-slate">score {{ $attempt->score_percent }}%</span>
                                    <span class="badge {{ $attempt->applied_at ? 'badge-green' : 'badge-slate' }}">{{ $attempt->applied_at ? '反映済み' : '未反映' }}</span>
                                </div>
                            </div>
                            @if (collect($attempt->weaknesses ?? [])->isNotEmpty())
                                <p class="mt-3 text-xs leading-5 text-amber-100">弱点：{{ collect($attempt->weaknesses)->implode(' / ') }}</p>
                            @endif
                            @if ($attempt->next_action)
                                <p class="mt-2 text-xs leading-5 text-cyan-100">次：{{ $attempt->next_action }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
