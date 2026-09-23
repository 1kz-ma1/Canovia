@extends('layouts.app')

@section('title', 'AIで資料を整理 | Canovia')

@section('content')
    <section class="mb-7 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-violet-300">AI RESOURCE ORGANIZER</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-50">AIで資料を整理</h1>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-400">資料を追加する操作とは分けて、登録済み資料をどのタスクへ置くかだけAIに判断させます。</p>
        </div>
        <a href="{{ route('plans.resources.index', $plan) }}" class="btn-secondary">関連資料へ戻る</a>
    </section>

    @if (session('status'))<div class="assistant-notice assistant-notice-info mb-6">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="assistant-notice mb-6 border border-rose-400/30 bg-rose-500/10 text-rose-100">
            <ul class="list-inside list-disc space-y-1 text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($plan->resources->isEmpty() || $plan->tasks->isEmpty())
        <section class="page-card p-6">
            <h2 class="text-xl font-bold text-slate-50">整理する材料がまだありません</h2>
            <p class="mt-2 text-sm text-slate-400">関連資料とタスクの両方があるとAI整理を利用できます。</p>
        </section>
    @else
        <section class="grid gap-6 xl:grid-cols-[1fr_1fr]">
            <div class="page-card p-5 sm:p-6">
                <p class="text-sm font-semibold text-cyan-300">1. AIへ渡す</p>
                <h2 class="mt-1 text-xl font-bold text-slate-50">この相談文をそのまま送る</h2>
                <textarea id="resource-assistant-prompt" readonly tabindex="-1" aria-hidden="true" class="sr-only">{{ $prompt }}</textarea>
                <div class="mt-4 rounded-2xl border border-cyan-300/15 bg-cyan-300/[0.035] p-4">
                    <p class="text-sm font-semibold text-slate-100">資料整理の相談文はCanovia側で準備済みです</p>
                    <p class="mt-1 text-xs leading-5 text-slate-400">普段使っているAIへ、そのまま送ってください。</p>
                    <button type="button" class="btn-primary mt-4" data-copy-target="#resource-assistant-prompt">相談文をコピー</button>
                    <details class="ai-handoff-details mt-4 rounded-xl border border-slate-800 bg-slate-950/35 p-3">
                        <summary class="cursor-pointer text-xs font-semibold text-slate-300">この相談文に含まれる情報</summary>
                        <ul class="mt-3 space-y-2 text-xs leading-5 text-slate-500">
                            <li>・対象PlanとTask一覧</li>
                            <li>・登録済み資料のタイトル・URL・メモ</li>
                            <li>・資料をどのTaskへ紐づけるか判断する条件</li>
                            <li>・Canoviaへ戻すJSON形式と対象ID</li>
                        </ul>
                    </details>
                    <p class="mt-3 text-xs leading-5 text-slate-500">相談文には登録した資料URLも含まれるため、送信先AIと共有してよいURLか確認してください。</p>
                </div>
            </div>

            <div class="page-card p-5 sm:p-6">
                <p class="text-sm font-semibold text-violet-300">2. AIのJSONを戻す</p>
                <h2 class="mt-1 text-xl font-bold text-slate-50">割り当て案をプレビュー</h2>
                <form method="POST" action="{{ route('plans.resources.assistant.preview', $plan) }}" class="mt-4">
                    @csrf
                    <button
                        type="button"
                        class="btn-primary"
                        data-paste-target="#resource_assignment_json"
                        data-paste-submit="1"
                        data-paste-fallback="#resourceAssignmentJsonManual"
                        data-paste-status="#resourceAssignmentJsonPasteStatus"
                    >クリップボードから貼り付けて確認</button>
                    <p id="resourceAssignmentJsonPasteStatus" class="mt-2 hidden text-xs leading-5 text-slate-400" aria-live="polite"></p>

                    <details id="resourceAssignmentJsonManual" class="ai-handoff-details mt-4 rounded-xl border border-slate-800 bg-slate-950/35 p-3" @if($errors->has('assignment_json') || old('assignment_json')) open @endif>
                        <summary class="cursor-pointer text-xs font-semibold text-slate-300">手動で貼り付ける / JSONを確認する</summary>
                        <textarea id="resource_assignment_json" name="assignment_json" rows="10" class="form-control mt-3 font-mono text-xs leading-6" placeholder="AIの最後のJSON回答を貼り付けてください">{{ old('assignment_json') }}</textarea>
                        <button type="submit" class="btn-secondary mt-3">このJSONを読み込む</button>
                    </details>
                </form>
            </div>
        </section>

        @if (is_array($preview))
            <section class="page-card mt-6 p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-emerald-300">3. 確認して反映</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-50">AIの整理案</h2>
                        @if (! empty($preview['summary']))<p class="mt-2 text-sm leading-6 text-slate-400">{{ $preview['summary'] }}</p>@endif
                    </div>
                    <form method="POST" action="{{ route('plans.resources.assistant.reset', $plan) }}">@csrf<button type="submit" class="btn-secondary">やり直す</button></form>
                </div>

                <div class="mt-5 space-y-3">
                    @foreach ($preview['assignments'] as $assignment)
                        <div class="rounded-2xl border border-white/8 bg-white/[0.03] p-4">
                            <h3 class="font-bold text-slate-50">{{ $assignment['resource_title'] }}</h3>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse ($assignment['task_titles'] as $taskTitle)
                                    <span class="rounded-full border border-cyan-300/15 bg-cyan-300/[0.05] px-3 py-1 text-xs text-cyan-100">{{ $taskTitle }}</span>
                                @empty
                                    <span class="text-xs text-slate-500">タスクへ紐づけない</span>
                                @endforelse
                            </div>
                            @if (! empty($assignment['reason']))<p class="mt-3 text-xs leading-5 text-slate-500">{{ $assignment['reason'] }}</p>@endif
                        </div>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('plans.resources.assistant.apply', $plan) }}" class="mt-5" onsubmit="return confirm('この割り当てを関連資料へ反映しますか？');">
                    @csrf
                    <button type="submit" class="btn-primary">この内容で紐づけを更新</button>
                </form>
            </section>
        @endif
    @endif

    {{-- Clipboard copy/paste is handled centrally in resources/js/app.js. --}}

@endsection
