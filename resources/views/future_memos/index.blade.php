@extends('layouts.app')

@section('title', '未来メモ | Canovia')

@section('content')
    @php
        $kindOptions = [
            'want_to_do' => ['label' => 'やりたいこと', 'icon' => '✦'],
            'ideal_self' => ['label' => 'なりたい自分', 'icon' => '🧭'],
            'concern' => ['label' => '気になっていること', 'icon' => '💭'],
            'value' => ['label' => '大事にしたいこと', 'icon' => '🌱'],
        ];
        $categoryOptions = [
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
        <header class="pk-v18-page-hero min-h-[10rem]">
            <div class="relative z-10 min-w-0">
                <p class="pk-v18-eyebrow">YOUR DIRECTION</p>
                <h1>未来メモ</h1>
                <p>まだ計画にしなくていい「やってみたい」「こうなりたい」を置いておく場所です。AIに相談するとき、あなたらしい提案を受けるための材料にもなります。</p>
            </div>
            <div class="pk-v18-page-guide" aria-hidden="true">
                <span>曖昧なままでも<br>残してOK ✦</span>
                <img src="/brand/mascot-guide.webp" alt="">
            </div>
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
                    <p class="pk-v18-card-kicker">ADD A NOTE</p>
                    <h2 class="mt-1 text-lg font-black text-slate-100">今思っていることを1つ残す</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-400">最初から全部埋めなくて大丈夫です。1件だけでも、計画づくりのパーソナライズに使えます。</p>
                </div>
                @if ($memos->isNotEmpty())
                    <a href="{{ route('future_memos.assistant') }}" class="btn-primary">✨ AIで方向を整理</a>
                @endif
            </div>

            <form method="POST" action="{{ route('future_memos.store') }}" class="mt-5 grid gap-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="future-memo-kind" class="form-label">どんなメモ？</label>
                        <select id="future-memo-kind" name="kind" class="form-control mt-2" required>
                            @foreach ($kindOptions as $value => $option)
                                <option value="{{ $value }}" @selected(old('kind', 'want_to_do') === $value)>{{ $option['icon'] }} {{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="future-memo-category" class="form-label">カテゴリ <span class="font-normal text-slate-500">（任意）</span></label>
                        <select id="future-memo-category" name="category" class="form-control mt-2">
                            <option value="">未分類</option>
                            @foreach ($categoryOptions as $value => $label)
                                <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="future-memo-content" class="form-label">内容</label>
                    <textarea id="future-memo-content" name="content" rows="3" class="form-control mt-2" placeholder="例：エンジニアとして一人でサービスを公開できるようになりたい" required>{{ old('content') }}</textarea>
                </div>

                <label class="flex items-start gap-3 rounded-xl border border-sky-400/15 bg-sky-500/5 p-4">
                    <input type="hidden" name="use_for_ai" value="0">
                    <input type="checkbox" name="use_for_ai" value="1" class="mt-1" @checked(old('use_for_ai', '1') === '1')>
                    <span>
                        <span class="block font-semibold text-slate-100">AIへの相談で使う</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-400">計画作成などで相談文を生成するとき、このメモを本人の希望としてAIへ共有できます。共有前に内容は画面で確認できます。</span>
                    </span>
                </label>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn-primary">未来メモを保存</button>
                    <a href="{{ route('home') }}" class="btn-secondary">ホームへ戻る</a>
                </div>
            </form>
        </section>

        @if ($memos->isEmpty())
            <section class="page-card p-6 text-center">
                <div class="text-3xl" aria-hidden="true">✦</div>
                <h2 class="mt-3 text-lg font-black text-slate-100">まだ未来メモはありません</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">「やってみたい」「こうなれたら嬉しい」くらいの粒度で十分です。計画にするかどうかは後から決められます。</p>
            </section>
        @else
            <section class="space-y-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="pk-v18-card-kicker">YOUR NOTES</p>
                        <h2 class="mt-1 text-lg font-black text-slate-100">保存した未来メモ</h2>
                    </div>
                    <a href="{{ route('future_memos.assistant') }}" class="btn-primary">✨ AIで方向を整理</a>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($memos as $memo)
                        <article class="page-card p-4 sm:p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-black uppercase tracking-[0.14em] text-sky-300">{{ $kindOptions[$memo->kind]['icon'] ?? '✦' }} {{ $memo->kindLabel() }}</p>
                                    <p class="mt-2 whitespace-pre-wrap text-sm font-semibold leading-6 text-slate-100">{{ $memo->content }}</p>
                                    <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                                        <span class="rounded-full border border-slate-700 px-2 py-1 text-slate-400">{{ $memo->categoryLabel() }}</span>
                                        <span class="rounded-full border px-2 py-1 {{ $memo->use_for_ai ? 'border-emerald-400/20 text-emerald-300' : 'border-slate-700 text-slate-500' }}">{{ $memo->use_for_ai ? 'AI共有ON' : 'AI共有OFF' }}</span>
                                    </div>
                                </div>
                            </div>

                            <details class="mt-4 rounded-xl border border-slate-800 bg-slate-950/25 p-3">
                                <summary class="cursor-pointer text-sm font-semibold text-slate-300">編集</summary>
                                <form method="POST" action="{{ route('future_memos.update', $memo) }}" class="mt-4 space-y-3">
                                    @csrf
                                    @method('PUT')
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <select name="kind" class="form-control">
                                            @foreach ($kindOptions as $value => $option)
                                                <option value="{{ $value }}" @selected($memo->kind === $value)>{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <select name="category" class="form-control">
                                            <option value="">未分類</option>
                                            @foreach ($categoryOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($memo->category === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <textarea name="content" rows="3" class="form-control" required>{{ $memo->content }}</textarea>
                                    <label class="flex items-center gap-2 text-sm text-slate-300">
                                        <input type="hidden" name="use_for_ai" value="0">
                                        <input type="checkbox" name="use_for_ai" value="1" @checked($memo->use_for_ai)>
                                        AIへの相談で使う
                                    </label>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit" class="btn-secondary">保存</button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('future_memos.destroy', $memo) }}" class="mt-2" onsubmit="return confirm('この未来メモを削除しますか？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-rose-300 hover:text-rose-200">削除する</button>
                                </form>
                            </details>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
