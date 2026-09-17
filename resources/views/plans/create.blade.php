@extends('layouts.app')

@section('title', '計画作成 | Canovia')

@section('content')
    <div class="mx-auto max-w-3xl space-y-5">
        <header class="pk-v18-page-hero min-h-[9rem]">
            <div class="relative z-10 min-w-0">
                <p class="pk-v18-eyebrow">QUICK CREATE</p>
                <h1>まず、やりたいことだけ。</h1>
                <p>細かい設定はあとで大丈夫。30秒で計画を作って、Canoviaを始めよう。</p>
            </div>
            <div class="pk-v18-page-guide" aria-hidden="true">
                <span>最初は<br>ざっくりでOK ✦</span>
                <img src="/brand/mascot-guide.webp" alt="">
            </div>
        </header>


        @if (($futureMemos ?? collect())->isEmpty())
            <section class="page-card border-cyan-300/20 p-4" data-future-memo-plan-hint>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="max-w-2xl">
                        <p class="text-[10px] font-black tracking-[.14em] text-cyan-300">PERSONALIZE</p>
                        <h2 class="mt-1 text-sm font-black text-slate-100">先に未来メモを1つ残すと、AIがあなた向けの計画を作りやすくなります</h2>
                        <p class="mt-1 text-xs leading-5 text-slate-400">必須ではありません。このまま計画を作って、あとから追加しても大丈夫です。</p>
                    </div>
                    <a href="{{ route('future_memos.index') }}" class="btn-secondary px-3 py-2 text-xs">未来メモを作る</a>
                </div>
            </section>
        @else
            <section class="page-card border-cyan-300/15 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><p class="text-[10px] font-black tracking-[.14em] text-cyan-300">PERSONALIZED</p><p class="mt-1 text-xs leading-5 text-slate-300">AIタスク生成時に、共有ONの未来メモ {{ $futureMemos->count() }}件を参考情報として使います。</p></div>
                    <a href="{{ route('future_memos.index') }}" class="text-xs font-black text-cyan-300">確認・編集 →</a>
                </div>
            </section>
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

        <form action="{{ route('plans.store') }}" method="POST" class="page-card space-y-5 p-5 sm:p-6" data-onboarding-target="plan-form">
            @csrf

            <div>
                <label for="title" class="form-label">何を達成したい？</label>
                <input
                    id="title"
                    type="text"
                    name="title"
                    value="{{ old('title', data_get($prefill ?? [], 'title')) }}"
                    placeholder="例：応用情報技術者試験に合格する"
                    required
                    autofocus
                    class="form-control mt-2 text-base font-semibold"
                >
                <p class="mt-2 text-xs leading-5 text-slate-500">計画名だけでも作成できます。タスクや進め方は次の画面でAIと整えられます。</p>
            </div>

            <div class="rounded-2xl border border-sky-400/15 bg-sky-500/5 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <label for="deadline" class="form-label">期限 <span class="font-normal text-slate-500">（任意）</span></label>
                        <p class="mt-1 text-xs leading-5 text-slate-500">まだ決めていなければ空欄でOK。あとからAIと相談して決められます。</p>
                    </div>
                    <span class="shrink-0 rounded-full border border-slate-700 px-2 py-1 text-[10px] font-bold text-slate-400">あとで設定可</span>
                </div>
                <input
                    id="deadline"
                    type="date"
                    name="deadline"
                    value="{{ old('deadline') }}"
                    class="form-control mt-3"
                >
            </div>

            <details class="rounded-2xl border border-slate-800 bg-slate-950/30 p-4" @if(old('description') || old('category') || old('start_date') || old('is_public') || old('visual_icon') || old('accent_key') || old('roadmap_world') || old('is_collaborative') || !empty($prefill)) open @endif>
                <summary class="cursor-pointer list-none font-semibold text-slate-200">
                    <span class="flex items-center justify-between gap-3">
                        <span>詳細設定</span>
                        <span class="text-xs font-normal text-slate-500">必要な人だけ</span>
                    </span>
                </summary>

                <div class="mt-5 space-y-5 border-t border-slate-800/80 pt-5">
                    <div>
                        <label for="description" class="form-label">説明</label>
                        <textarea id="description" name="description" rows="3" placeholder="目的や完成条件など" class="form-control mt-2">{{ old('description', data_get($prefill ?? [], 'description')) }}</textarea>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="category" class="form-label">カテゴリ</label>
                            <select id="category" name="category" class="form-control mt-2">
                                <option value="">未設定</option>
                                <option value="資格学習" @selected(old('category', data_get($prefill ?? [], 'category')) === '資格学習')>資格学習</option>
                                <option value="個人開発" @selected(old('category', data_get($prefill ?? [], 'category')) === '個人開発')>個人開発</option>
                                <option value="制作活動" @selected(old('category', data_get($prefill ?? [], 'category')) === '制作活動')>制作活動</option>
                                <option value="ゲーム開発" @selected(old('category', data_get($prefill ?? [], 'category')) === 'ゲーム開発')>ゲーム開発</option>
                                <option value="その他" @selected(old('category', data_get($prefill ?? [], 'category')) === 'その他')>その他</option>
                            </select>
                        </div>

                        <div>
                            <label for="start_date" class="form-label">開始日</label>
                            <input id="start_date" type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" class="form-control mt-2">
                        </div>
                    </div>

                    <details class="rounded-xl border border-slate-800 bg-slate-950/35 p-3">
                        <summary class="cursor-pointer text-sm font-semibold text-slate-300">見た目を変更する</summary>
                        <div class="mt-4">
                            @include('plans.partials.visual-picker')
                        </div>
                    </details>

                    <label class="flex items-start gap-3 rounded-xl border border-slate-800 bg-slate-950/35 p-4">
                        <input type="checkbox" name="is_public" value="1" @checked(old('is_public')) class="mt-1">
                        <span>
                            <span class="block font-medium text-slate-200">この計画を公開する</span>
                            <span class="mt-1 block text-xs leading-5 text-slate-500">共有URLを知っている人が閲覧できます。編集はできません。</span>
                        </span>
                    </label>

                    @auth
                        <label class="flex items-start gap-3 rounded-xl border border-violet-400/20 bg-violet-500/5 p-4">
                            <input type="checkbox" name="is_collaborative" value="1" @checked(old('is_collaborative')) class="mt-1">
                            <span>
                                <span class="block font-medium text-slate-200">共同計画として作る</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500">参加者はURLまたは参加コードで参加できます。最初は閲覧のみで、編集権限はあとからあなたが付与します。</span>
                            </span>
                        </label>
                    @else
                        <div class="rounded-xl border border-slate-800 bg-slate-950/35 p-4 text-sm text-slate-400">
                            <strong class="text-slate-200">共同計画はログイン後に使えます。</strong>
                            <p class="mt-1 text-xs leading-5">計画を作ったあとでアカウントへ紐づけてから有効化できます。</p>
                        </div>
                    @endauth
                </div>
            </details>

            <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                <button type="submit" class="btn-primary w-full justify-center py-3 text-base" data-onboarding-target="create-plan-submit">
                    まず始める
                </button>
                <a href="{{ route('home') }}" class="btn-secondary justify-center">キャンセル</a>
            </div>

            <p class="text-center text-xs leading-5 text-slate-500">作成後すぐに、AIとタスク・期限・使える時間を整えられます。</p>
        </form>
    </div>
@endsection
