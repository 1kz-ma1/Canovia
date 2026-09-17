@extends('layouts.app')

@section('title', '未来の方向を整理 | Canovia')

@section('content')
    @php
        $categoryLabels = [
            'career' => 'キャリア',
            'learning' => '学習・資格',
            'project' => '制作・開発',
            'life' => '生活',
            'health' => '健康',
            'money' => 'お金',
            'hobby' => '趣味',
            'other' => 'その他',
        ];
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <header class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="pk-v18-eyebrow">FIND YOUR DIRECTION</p>
                <h1 class="mt-2 text-3xl font-black text-slate-50">AIと次の方向を整理する</h1>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-400">未来メモをCanoviaが相談文にまとめます。普段使っているAIとの会話で方向を整理し、候補が決まったらそのまま計画作成へ進めます。</p>
            </div>
            <a href="{{ route('future_memos.index') }}" class="btn-secondary">未来メモへ戻る</a>
        </header>

        @if (session('status'))
            <div class="assistant-notice assistant-notice-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="assistant-notice assistant-notice-error">
                <p class="font-bold">入力内容を確認してください。</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="page-card p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="pk-v18-card-kicker">CONTEXT</p>
                    <h2 class="mt-1 text-lg font-black text-slate-100">AIに共有する未来メモ</h2>
                    <p class="mt-1 text-xs leading-5 text-slate-400">「AIへの相談で使う」がONのメモだけを相談文へ入れます。関係の薄い内容は無理に使わないようAIにも指示します。</p>
                </div>
                <a href="{{ route('future_memos.index') }}" class="text-xs font-bold text-sky-300">編集する →</a>
            </div>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                @forelse ($memos->where('use_for_ai', true) as $memo)
                    <div class="rounded-xl border border-slate-800 bg-slate-950/30 p-3">
                        <p class="text-[10px] font-black uppercase tracking-[0.12em] text-sky-300">{{ $memo->kindLabel() }} · {{ $memo->categoryLabel() }}</p>
                        <p class="mt-1 text-sm leading-6 text-slate-200">{{ $memo->content }}</p>
                    </div>
                @empty
                    <div class="sm:col-span-2 rounded-xl border border-amber-300/15 bg-amber-300/5 p-4 text-sm leading-6 text-slate-300">
                        AIに共有する未来メモがありません。先に1件だけでも残すと、方向整理をあなた向けにしやすくなります。
                    </div>
                @endforelse
            </div>
        </section>

        <section class="page-card p-5 sm:p-6">
            <div>
                <p class="pk-v18-card-kicker">STEP 1</p>
                <h2 class="mt-1 text-lg font-black text-slate-100">今の状況を少しだけ足す</h2>
                <p class="mt-1 text-sm leading-6 text-slate-400">全部任意です。普段のAIとの会話ですでに伝えているなら、空欄でも構いません。</p>
            </div>

            <form method="POST" action="{{ route('future_memos.assistant.prompt') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="future-current-state" class="form-label">今の状況 <span class="font-normal text-slate-500">（任意）</span></label>
                    <textarea id="future-current-state" name="current_state" rows="3" class="form-control mt-2" placeholder="例：就活を始めたいが、何から進めるか決まっていない">{{ old('current_state', $draft['current_state'] ?? '') }}</textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="future-available-time" class="form-label">使えそうな時間 <span class="font-normal text-slate-500">（任意）</span></label>
                        <textarea id="future-available-time" name="available_time" rows="3" class="form-control mt-2" placeholder="例：平日1時間、休日は2〜3時間">{{ old('available_time', $draft['available_time'] ?? '') }}</textarea>
                    </div>
                    <div>
                        <label for="future-constraints" class="form-label">避けたいこと・制約 <span class="font-normal text-slate-500">（任意）</span></label>
                        <textarea id="future-constraints" name="constraints" rows="3" class="form-control mt-2" placeholder="例：今の個人開発は止めたくない">{{ old('constraints', $draft['constraints'] ?? '') }}</textarea>
                    </div>
                </div>
                <button type="submit" class="btn-primary">AIに相談する文章を生成</button>
            </form>
        </section>

        @if ($draft && ! empty($draft['prompt']))
            <section class="page-card p-5 sm:p-6">
                <div>
                    <p class="pk-v18-card-kicker">STEP 2</p>
                    <h2 class="mt-1 text-lg font-black text-slate-100">普段使っているAIへ送る</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-400">不足情報があればAIから最大5問まで質問されます。会話を続け、最後にJSONが返ってきたら次の欄へ貼り付けてください。</p>
                </div>

                <textarea id="futureDirectionPrompt" class="form-control mt-4 min-h-[420px] font-mono text-xs" readonly>{{ $draft['prompt'] }}</textarea>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button type="button" class="btn-primary" data-future-prompt-copy>相談文をコピー</button>
                    <span class="text-sm text-emerald-300" data-future-prompt-status aria-live="polite"></span>
                </div>
            </section>

            <section class="page-card p-5 sm:p-6">
                <div>
                    <p class="pk-v18-card-kicker">STEP 3</p>
                    <h2 class="mt-1 text-lg font-black text-slate-100">AIの最後の回答を戻す</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-400">候補をCanoviaで見比べられる形にします。ここで勝手に計画を作ることはありません。</p>
                </div>

                <form method="POST" action="{{ route('future_memos.assistant.preview') }}" class="mt-5 space-y-4">
                    @csrf
                    <textarea name="response_json" rows="12" class="form-control font-mono text-xs" placeholder="AIの最後の回答をそのまま貼り付け">{{ old('response_json') }}</textarea>
                    <button type="submit" class="btn-primary">候補を読み込む</button>
                </form>
            </section>
        @endif

        @if ($proposal && ! empty($proposal['goal_candidates']))
            <section class="space-y-4">
                <div>
                    <p class="pk-v18-card-kicker">YOUR OPTIONS</p>
                    <h2 class="mt-1 text-xl font-black text-slate-100">AIからの目標候補</h2>
                    @if (! empty($proposal['summary']))
                        <p class="mt-2 text-sm leading-6 text-slate-400">{{ $proposal['summary'] }}</p>
                    @endif
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($proposal['goal_candidates'] as $candidate)
                        @php
                            $planDescription = collect([
                                $candidate['reason'] ? '背景: ' . $candidate['reason'] : null,
                                $candidate['timeframe'] ? '期間目安: ' . $candidate['timeframe'] : null,
                                $candidate['first_step'] ? '最初の一歩: ' . $candidate['first_step'] : null,
                            ])->filter()->implode("\n");
                            $planCategory = match ($candidate['category']) {
                                'learning' => '資格学習',
                                'project' => '制作活動',
                                default => 'その他',
                            };
                        @endphp
                        <article class="page-card p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <span class="rounded-full border border-sky-400/20 px-2 py-1 text-[10px] font-bold text-sky-300">{{ $categoryLabels[$candidate['category']] ?? 'その他' }}</span>
                                    <h3 class="mt-3 text-lg font-black leading-7 text-slate-100">{{ $candidate['title'] }}</h3>
                                </div>
                            </div>
                            @if ($candidate['reason'])
                                <p class="mt-3 text-sm leading-6 text-slate-400">{{ $candidate['reason'] }}</p>
                            @endif
                            <dl class="mt-4 space-y-2 text-xs leading-5">
                                @if ($candidate['timeframe'])
                                    <div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-300">期間</dt><dd class="text-slate-400">{{ $candidate['timeframe'] }}</dd></div>
                                @endif
                                @if ($candidate['first_step'])
                                    <div class="flex gap-2"><dt class="shrink-0 font-bold text-slate-300">最初</dt><dd class="text-slate-400">{{ $candidate['first_step'] }}</dd></div>
                                @endif
                            </dl>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <a href="{{ route('plans.create', ['title' => $candidate['title'], 'description' => $planDescription, 'category' => $planCategory, 'from_future_memo' => 1]) }}" class="btn-primary">この目標で計画を作る</a>
                                <form method="POST" action="{{ route('future_memos.store') }}">
                                    @csrf
                                    <input type="hidden" name="kind" value="want_to_do">
                                    <input type="hidden" name="category" value="{{ $candidate['category'] }}">
                                    <input type="hidden" name="content" value="{{ $candidate['title'] }}">
                                    <input type="hidden" name="use_for_ai" value="1">
                                    <button type="submit" class="btn-secondary">未来メモに残す</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($draft || $proposal)
            <form method="POST" action="{{ route('future_memos.assistant.reset') }}">
                @csrf
                <button type="submit" class="text-sm font-semibold text-slate-500 hover:text-slate-300">方向整理を最初からやり直す</button>
            </form>
        @endif
    </div>

    <script>
        (() => {
            const button = document.querySelector('[data-future-prompt-copy]');
            const status = document.querySelector('[data-future-prompt-status]');
            const prompt = document.getElementById('futureDirectionPrompt');
            button?.addEventListener('click', async () => {
                if (!prompt) return;
                try {
                    await navigator.clipboard.writeText(prompt.value);
                    if (status) status.textContent = 'コピーしました。普段使っているAIへ貼り付けてください。';
                } catch (_) {
                    prompt.focus();
                    prompt.select();
                    const copied = document.execCommand?.('copy');
                    if (status) status.textContent = copied ? 'コピーしました。' : '選択された文章を手動でコピーしてください。';
                }
            });
        })();
    </script>
@endsection
