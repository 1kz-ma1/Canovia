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
            <p class="mt-4 max-w-3xl text-sm leading-7 text-slate-300">AIは問題作成と評価を担当し、CanoviaはTaskの文脈・回答UI・学習履歴・進捗反映を担当します。評価を読み込んだだけではTaskを変更せず、確認後に「Taskへ反映」を押したときだけ更新します。</p>
        </section>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-300/20 bg-emerald-300/[0.06] px-4 py-3 text-sm text-emerald-100">{{ session('success') }}</div>
        @endif
        @if (session('status'))
            <div class="rounded-2xl border border-slate-700 bg-slate-900/70 px-4 py-3 text-sm text-slate-300">{{ session('status') }}</div>
        @endif

        <section class="page-card p-5 sm:p-6">
            <div class="flex items-center gap-3">
                <span class="grid h-8 w-8 place-items-center rounded-full bg-cyan-300/10 text-sm font-black text-cyan-200">1</span>
                <div><h2 class="font-black text-slate-100">AIに問題を作ってもらう</h2><p class="text-xs text-slate-500">Taskの内容とIDを含んだ専用プロンプトです。</p></div>
            </div>
            <textarea id="studyPracticeGenerationPrompt" readonly class="form-control mt-4 min-h-[300px] font-mono text-xs leading-6">{{ $generationPrompt }}</textarea>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" class="btn-primary" data-copy-text="{{ $generationPrompt }}">問題作成プロンプトをコピー</button>
                <span class="text-xs text-slate-500">普段使っているAIへそのまま送ってください。</span>
            </div>
        </section>

        <section class="page-card p-5 sm:p-6">
            <div class="flex items-center gap-3">
                <span class="grid h-8 w-8 place-items-center rounded-full bg-cyan-300/10 text-sm font-black text-cyan-200">2</span>
                <div><h2 class="font-black text-slate-100">問題JSONをCanoviaへ戻す</h2><p class="text-xs text-slate-500">説明文が少し混じっていてもCanovia側でJSON部分を抽出します。</p></div>
            </div>
            <form method="POST" action="{{ route('plans.tasks.study_practice.import', [$plan, $task]) }}" class="mt-4">
                @csrf
                <textarea name="questions_json" class="form-control min-h-[220px] font-mono text-xs" placeholder="AIが返したJSONを貼り付け">{{ old('questions_json') }}</textarea>
                @error('questions_json')<p class="mt-2 text-sm font-semibold text-rose-300">{{ $message }}</p>@enderror
                <button type="submit" class="btn-primary mt-3">問題を読み込む</button>
            </form>
        </section>

        @if ($questions)
            <section class="page-card p-5 sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-cyan-300/10 text-sm font-black text-cyan-200">3</span>
                    <div><h2 class="font-black text-slate-100">{{ $exerciseTitle ?: '演習に回答' }}</h2><p class="text-xs text-slate-500">{{ count($questions) }}問。回答は評価用プロンプトへまとめられます。</p></div>
                </div>

                <form method="POST" action="{{ route('plans.tasks.study_practice.answers', [$plan, $task]) }}" class="mt-5 space-y-4">
                    @csrf
                    @foreach ($questions as $index => $question)
                        <fieldset class="rounded-2xl border border-white/8 bg-white/[0.025] p-4">
                            <legend class="px-1 text-sm font-black text-slate-100">Q{{ $index + 1 }}</legend>
                            <p class="mt-2 whitespace-pre-wrap text-sm leading-7 text-slate-200">{{ $question['prompt'] }}</p>

                            @if ($question['type'] === 'single_choice')
                                <div class="mt-3 grid gap-2">
                                    @foreach ($question['choices'] as $choice)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-300">
                                            <input type="radio" name="answers[{{ $question['id'] }}]" value="{{ $choice['id'] }}" class="mt-1">
                                            <span><strong class="text-slate-100">{{ $choice['id'] }}</strong> {{ $choice['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @elseif ($question['type'] === 'multiple_choice')
                                <div class="mt-3 grid gap-2">
                                    @foreach ($question['choices'] as $choice)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-800 bg-slate-950/40 p-3 text-sm text-slate-300">
                                            <input type="checkbox" name="answers[{{ $question['id'] }}][]" value="{{ $choice['id'] }}" class="mt-1">
                                            <span><strong class="text-slate-100">{{ $choice['id'] }}</strong> {{ $choice['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @elseif ($question['type'] === 'number')
                                <input type="number" step="any" name="answers[{{ $question['id'] }}]" class="form-control mt-3" placeholder="数値を入力">
                            @else
                                <textarea name="answers[{{ $question['id'] }}]" class="form-control mt-3 min-h-28" placeholder="回答を入力"></textarea>
                            @endif
                            @error('answers.'.$question['id'])<p class="mt-2 text-sm font-semibold text-rose-300">{{ $message }}</p>@enderror
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
                    <div><h2 class="font-black text-slate-100">AIに採点・評価してもらう</h2><p class="text-xs text-slate-500">問題とあなたの回答をCanoviaがひとつの評価依頼にまとめました。</p></div>
                </div>
                <textarea readonly class="form-control mt-4 min-h-[320px] font-mono text-xs leading-6">{{ $evaluationPrompt }}</textarea>
                <button type="button" class="btn-primary mt-3" data-copy-text="{{ $evaluationPrompt }}">評価プロンプトをコピー</button>

                <form method="POST" action="{{ route('plans.tasks.study_practice.assessment', [$plan, $task]) }}" class="mt-5 border-t border-slate-800 pt-5">
                    @csrf
                    <label class="form-label" for="assessment_json">AIが返した評価JSON</label>
                    <textarea id="assessment_json" name="assessment_json" class="form-control mt-2 min-h-[220px] font-mono text-xs" placeholder="評価JSONを貼り付け">{{ old('assessment_json') }}</textarea>
                    @error('assessment_json')<p class="mt-2 text-sm font-semibold text-rose-300">{{ $message }}</p>@enderror
                    <button type="submit" class="btn-primary mt-3">評価を読み込んで確認</button>
                </form>
            </section>
        @endif

        @if ($assessment)
            <section class="page-card border-emerald-300/20 p-5 sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-300">ASSESSMENT PREVIEW</p>
                <div class="mt-3 flex flex-wrap items-end gap-4">
                    <div><p class="text-xs text-slate-500">今回の評価</p><strong class="text-4xl text-slate-50">{{ $assessment['score_percent'] }}%</strong></div>
                    <div><p class="text-xs text-slate-500">AI提案のTask進捗</p><strong class="text-2xl text-cyan-200">{{ $assessment['recommended_task_progress_percent'] }}%</strong></div>
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
                            <p class="mt-1 text-xs leading-5 text-slate-400">Task進捗は現在値とAI提案の高い方を使うため、演習結果だけで進捗が後退することはありません。評価根拠と次のActionもTaskへ残します。</p>
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
