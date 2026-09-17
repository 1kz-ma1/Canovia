@extends('layouts.app')

@section('title', 'AIと初期計画をつくる | Canovia')

@section('content')
    <div class="mx-auto max-w-5xl space-y-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900 font-heading">
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
                <h2 class="text-xl font-bold text-slate-900 font-heading">この計画について相談します</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $plan->title }}</p>
            </div>
        </section>

        <section class="info-card">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-100 font-heading">今回AIに共有する未来メモ</h2>
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
                <h2 class="text-xl font-bold text-slate-900 font-heading">
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
                <h2 class="text-xl font-bold text-slate-900 font-heading">
                    2. AIの回答をCanoviaへ戻す
                </h2>
                <p class="mt-2 text-sm leading-7 text-slate-600">
                    AIの最後の回答をそのまま貼り付けてください。説明文やコードブロックが一緒に入っていても、CanoviaがJSON部分を探して読み込みます。
                </p>
            </div>

            @if ($errors->any())
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <p class="font-semibold">うまく読み込めませんでした。</p>
                    <p class="mt-1">入力内容は残っています。下の内容を直して、もう一度試してください。</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('plans.ai_task_assistant.import', $plan) }}"
                class="space-y-4"
                data-ai-import-form
                data-plan-id="{{ $plan->id }}"
                data-plan-title="{{ $plan->title }}"
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
                    >{{ old('tasks_json') }}</textarea>
                    <p class="mt-2 hidden text-sm font-semibold text-red-500" data-ai-json-client-error role="alert"></p>
                </div>

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
            const form = document.querySelector('[data-ai-import-form]');
            const input = document.querySelector('[data-ai-json-input]');
            const error = document.querySelector('[data-ai-json-client-error]');
            const submit = document.querySelector('[data-ai-import-submit]');

            const showError = (message) => {
                if (!error) return;
                error.textContent = message;
                error.classList.remove('hidden');
                input?.focus();
            };

            const clearError = () => {
                if (!error) return;
                error.textContent = '';
                error.classList.add('hidden');
            };

            const extractJson = (value) => {
                const text = String(value || '').trim();
                const fenced = text.match(/```(?:json)?\s*([\s\S]*?)\s*```/i);
                if (fenced?.[1]) return fenced[1].trim();
                if (text.startsWith('{') && text.endsWith('}')) return text;
                const start = text.indexOf('{');
                const end = text.lastIndexOf('}');
                return start >= 0 && end > start ? text.slice(start, end + 1).trim() : text;
            };

            copyButton?.addEventListener('click', async () => {
                if (!prompt) return;
                try {
                    await navigator.clipboard.writeText(prompt.value);
                    if (copyStatus) copyStatus.textContent = 'コピーしました。普段使っているAIに貼り付けてください。';
                } catch (_) {
                    prompt.focus();
                    prompt.select();
                    prompt.setSelectionRange(0, prompt.value.length);
                    const copied = document.execCommand?.('copy');
                    if (copyStatus) {
                        copyStatus.textContent = copied
                            ? 'コピーしました。普段使っているAIに貼り付けてください。'
                            : '自動コピーできませんでした。選択された文章を手動でコピーしてください。';
                    }
                }
            });

            input?.addEventListener('input', clearError);

            form?.addEventListener('submit', (event) => {
                clearError();
                const raw = input?.value || '';
                if (!raw.trim()) {
                    event.preventDefault();
                    showError('AIの最後の回答を貼り付けてください。');
                    return;
                }

                let parsed;
                try {
                    parsed = JSON.parse(extractJson(raw));
                } catch (_) {
                    event.preventDefault();
                    showError('JSON部分を見つけられませんでした。AIに「最後はJSONだけで出力して」と伝えて、もう一度貼り付けてください。');
                    return;
                }

                if (String(parsed?.schema_version || '') !== '2.0' || parsed?.flow !== 'plan_generation') {
                    event.preventDefault();
                    showError('Canovia用の計画データではないようです。上の相談用文章をもう一度AIへ貼り付けてください。');
                    return;
                }

                const target = parsed?.target_plan || {};
                if (Number(target.id || 0) !== Number(form.dataset.planId)
                    || String(target.title || '').trim() !== String(form.dataset.planTitle || '').trim()) {
                    event.preventDefault();
                    showError('別の計画向けの回答のようです。この画面の相談用文章から作った回答を貼り付けてください。');
                    return;
                }

                if (submit) {
                    submit.disabled = true;
                    submit.textContent = '確認して反映中…';
                }
            });
        })();
    </script>
@endsection
