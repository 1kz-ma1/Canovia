@extends('layouts.app')

@section('title', 'AIと初期計画をつくる | Canovia')

@section('content')
    <div class="initial-plan-page mx-auto max-w-5xl space-y-5 pb-32 sm:space-y-6 md:space-y-8 md:pb-10">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 font-heading sm:text-3xl">
                    AIと初期計画をつくる
                </h1>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
                    Canoviaが相談用の文章を用意します。普段使っているAIで相談し、最後の回答をここへ戻すと、タスクと進む順番をまとめて登録できます。
                </p>
            </div>

            <a href="{{ route('plans.show', $plan) }}" class="btn-secondary">
                計画へ戻る
            </a>
        </div>

        <section class="info-card">
            <div>
                <h2 class="text-lg font-bold text-slate-900 font-heading sm:text-xl">この計画について相談します</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $plan->title }}</p>
            </div>
        </section>

        <section class="info-card">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-slate-100 font-heading sm:text-lg">今回AIに共有する未来メモ</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-400">「AIへの参考情報として使う」がONの内容だけを、本人理解のために相談文へ追加しています。</p>
                </div>
                <a href="{{ route('future_memos.index') }}" class="btn-secondary px-3 py-2 text-xs">未来メモを編集</a>
            </div>
            @if (($futureMemos ?? collect())->isNotEmpty())
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($futureMemos as $memo)
                        <div class="rounded-2xl border border-cyan-300/10 bg-slate-950/30 p-3">
                            <p class="text-[10px] font-black tracking-[.12em] text-cyan-300">{{ $memo->kindLabel() }} · {{ $memo->categoryLabel() }}</p>
                            <p class="mt-1 line-clamp-3 text-xs leading-5 text-slate-300">{{ $memo->content }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-4 rounded-2xl border border-dashed border-cyan-300/20 p-4">
                    <p class="text-sm font-bold text-slate-200">未来メモはまだありません</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">先に1つ残しておくと、AIがあなたの希望を計画へ反映しやすくなります。</p>
                    <a href="{{ route('future_memos.index') }}" class="mt-3 inline-flex text-xs font-black text-cyan-300">未来メモを作る →</a>
                </div>
            @endif
        </section>

        <section class="info-card space-y-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900 font-heading sm:text-xl">
                    1. 相談用の文章をコピー
                </h2>
                <p class="mt-2 text-sm leading-7 text-slate-600">
                    コピーした文章をChatGPT、Gemini、Claudeなど、普段使っているAIへ貼り付けてください。
                    情報が足りない場合はAIから質問されるので、そのまま会話を続けて大丈夫です。
                </p>
            </div>

            <textarea
                id="aiPrompt"
                class="form-control min-h-[360px] font-mono text-sm"
                readonly
            >{{ $prompt }}</textarea>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" class="btn-primary" data-ai-copy-prompt data-onboarding-target="ai-copy">
                    相談用の文章をコピー
                </button>
                <p class="text-sm text-emerald-500" data-ai-copy-status aria-live="polite"></p>
            </div>
        </section>

        <section class="info-card space-y-4" data-onboarding-target="ai-import">
            <div>
                <h2 class="text-lg font-bold text-slate-900 font-heading sm:text-xl">
                    2. AIの回答をCanoviaへ戻す
                </h2>
                <p class="mt-2 text-sm leading-7 text-slate-600">
                    AIの最後の回答をそのまま貼り付けてください。説明文やコードブロックが一緒に入っていても、CanoviaがJSON部分を探して読み込みます。
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('plans.ai_task_assistant.import', $plan) }}"
                class="space-y-4"
                data-ai-plan-generation-import
            >
                @csrf

                <div>
                    <label for="tasks_json" class="mb-2 block text-sm font-semibold text-slate-700">
                        AIの最後の回答
                    </label>
                    <textarea
                        id="tasks_json"
                        name="tasks_json"
                        class="form-control min-h-[320px] font-mono text-sm"
                        placeholder="AIの最後の回答をここへ貼り付け"
                        data-ai-json-input
                        required
                    >{{ old('tasks_json') }}</textarea>
                </div>

                @if ($errors->has('tasks_json'))
                    @php
                        $jsonErrorMessage = $errors->first('tasks_json');
                        $jsonRepairPrompt = implode("\n", [
                            'Canoviaの初期計画JSONでエラーが発生しました。',
                            '下の「元のCanoviaプロンプト」を仕様と対象計画の唯一の正として扱ってください。',
                            'エラー解消に必要な箇所だけ修正し、タスク内容・時間・順序など正しい情報はできるだけ保持してください。',
                            'target_plan.idは '.$plan->id.' のままにし、別の計画IDを使わないでください。',
                            'priorityとactivation_costは必ず1〜5です。タスクの実行順はpriorityを6以上にせず、reorder_tasksで表現してください。',
                            '修正後はJSONとして構文解析できることと、Canoviaのエラー内容が解消されていることを確認してください。',
                            '説明文・Markdown・コードフェンスを付けず、有効なJSONだけを返してください。',
                            '',
                            '【Canoviaのエラー】',
                            $jsonErrorMessage,
                            '',
                            '【エラーになったJSON】',
                            old('tasks_json', ''),
                            '',
                            '【元のCanoviaプロンプト】',
                            $prompt,
                        ]);
                    @endphp
                    <div class="assistant-message-row assistant-message-left">
                        <div class="assistant-avatar">CV</div>
                        <div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
                            <p class="assistant-speaker">Canovia サポーター</p>
                            <h3 class="mt-2 text-lg font-bold text-slate-100">初期計画JSONを確認できませんでした</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-300">
                                入力内容は残しています。下の修正依頼をコピーして、JSONを作ったAIへそのまま送ってください。
                            </p>
                            <div class="assistant-notice assistant-notice-error mt-4">
                                <p class="font-bold">Canoviaが検出した内容</p>
                                <p class="mt-1 text-sm leading-6">{{ $jsonErrorMessage }}</p>
                            </div>
                            <textarea id="initialPlanJsonRepairPrompt" class="form-control mt-4 min-h-[220px] font-mono text-xs" readonly>{{ $jsonRepairPrompt }}</textarea>
                            <button type="button" class="btn-primary mt-3" data-initial-plan-copy-repair>修正依頼をコピー</button>
                            <p class="mt-2 text-xs text-slate-400" data-initial-plan-copy-repair-status aria-live="polite"></p>
                        </div>
                    </div>
                @endif

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-amber-800">
                    <p class="font-semibold">反映前にCanoviaが確認します</p>
                    <p>
                        対象の計画やタスク内容を確認してから登録します。形式が違っていても、入力した内容は消えません。
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn-primary" data-ai-import-submit>
                        計画に反映する
                    </button>

                    <a href="{{ route('plans.show', $plan) }}" class="btn-secondary">
                        あとで
                    </a>
                </div>
            </form>
        </section>
    </div>

    <script>
        (() => {
            const copyButton = document.querySelector('[data-ai-copy-prompt]');
            const copyStatus = document.querySelector('[data-ai-copy-status]');
            const prompt = document.getElementById('aiPrompt');
            const repairButton = document.querySelector('[data-initial-plan-copy-repair]');
            const repairPrompt = document.getElementById('initialPlanJsonRepairPrompt');
            const repairStatus = document.querySelector('[data-initial-plan-copy-repair-status]');

            const copyText = async (value) => {
                try {
                    await navigator.clipboard.writeText(value);
                    return true;
                } catch (_) {
                    const textarea = document.createElement('textarea');
                    textarea.value = value;
                    textarea.setAttribute('readonly', '');
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    textarea.setSelectionRange(0, textarea.value.length);
                    let copied = false;
                    try { copied = document.execCommand('copy'); } catch (_) {}
                    textarea.remove();
                    return copied;
                }
            };

            copyButton?.addEventListener('click', async () => {
                if (!prompt) return;
                const copied = await copyText(prompt.value);
                if (copyStatus) {
                    copyStatus.textContent = copied
                        ? 'コピーしました。普段使っているAIに貼り付けてください。'
                        : '自動コピーできませんでした。相談用の文章を手動でコピーしてください。';
                }
            });

            repairButton?.addEventListener('click', async () => {
                if (!repairPrompt) return;
                const copied = await copyText(repairPrompt.value);
                if (repairStatus) {
                    repairStatus.textContent = copied
                        ? 'コピーしました。JSONを作ったAIへそのまま送ってください。'
                        : '自動コピーできませんでした。上の修正依頼を手動でコピーしてください。';
                }
            });
        })();
    </script>
@endsection
