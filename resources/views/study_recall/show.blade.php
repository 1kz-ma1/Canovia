@extends('layouts.app')

@section('title', 'Recall学習 | Canovia')

@section('content')
    @php
        $ratingLabels = [
            'again' => ['label' => 'もう一度', 'hint' => '10分後', 'class' => 'border-rose-300/25 bg-rose-300/[0.05] text-rose-100'],
            'hard' => ['label' => '難しい', 'hint' => '短め', 'class' => 'border-amber-300/25 bg-amber-300/[0.05] text-amber-100'],
            'good' => ['label' => '思い出せた', 'hint' => '標準', 'class' => 'border-cyan-300/25 bg-cyan-300/[0.05] text-cyan-100'],
            'easy' => ['label' => '余裕', 'hint' => '長め', 'class' => 'border-emerald-300/25 bg-emerald-300/[0.05] text-emerald-100'],
        ];
    @endphp

    <div class="mx-auto max-w-5xl space-y-5">
        <section class="page-card border-emerald-300/20 p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-300">STUDY ACTIVITY / RECALL</p>
                    <h1 class="mt-2 text-2xl font-black text-slate-50">思い出す練習</h1>
                    <p class="mt-2 text-sm text-slate-400">{{ $plan->displayIcon() }} {{ $plan->title }} / {{ $task->title }}</p>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">答えを読む前に思い出し、感触に応じて次に確認する間隔をCanoviaが調整します。学習時間だけではTask進捗を上げません。</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('plans.tasks.study_activity.show', [$plan, $task]) }}" class="btn-secondary">学習方法へ</a>
                    <a href="{{ route('plans.show', $plan) }}" class="btn-secondary">Planへ戻る</a>
                </div>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-5">
                @foreach ([
                    ['label' => 'カード', 'value' => $stats['total']],
                    ['label' => '今やる', 'value' => $stats['due']],
                    ['label' => '未学習', 'value' => $stats['new']],
                    ['label' => '定着候補', 'value' => $stats['mastered']],
                    ['label' => '今日の確認', 'value' => $stats['reviewed_today']],
                ] as $stat)
                    <div class="rounded-xl border border-white/8 bg-slate-950/25 p-3 text-center">
                        <p class="text-[10px] text-slate-500">{{ $stat['label'] }}</p>
                        <strong class="mt-1 block text-xl text-slate-100">{{ $stat['value'] }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        @if (session('success'))
            <div class="rounded-2xl border border-emerald-300/20 bg-emerald-300/[0.06] px-4 py-3 text-sm text-emerald-100">{{ session('success') }}</div>
        @endif
        @if (session('status'))
            <div class="rounded-2xl border border-slate-700 bg-slate-900/70 px-4 py-3 text-sm text-slate-300">{{ session('status') }}</div>
        @endif

        @if ($currentItem)
            <section class="page-card border-cyan-300/20 p-5 sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.14em] text-cyan-300">RECALL NOW</p>
                        <p class="mt-1 text-xs text-slate-500">まず答えを見ずに思い出してください。</p>
                    </div>
                    <span class="badge badge-slate">復習 {{ (int) $currentItem->repetitions }}回</span>
                </div>

                <div class="mt-6 rounded-2xl border border-white/10 bg-white/[0.03] p-5 sm:p-7">
                    <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">FRONT</p>
                    <h2 class="mt-3 whitespace-pre-line text-xl font-black leading-8 text-slate-50">{{ $currentItem->prompt }}</h2>
                </div>

                <details class="mt-4 rounded-2xl border border-emerald-300/15 bg-emerald-300/[0.035] p-4">
                    <summary class="cursor-pointer text-sm font-black text-emerald-100">答えを表示</summary>
                    <div class="mt-4 border-t border-white/8 pt-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-500">BACK</p>
                        <p class="mt-2 whitespace-pre-line text-base leading-7 text-slate-100">{{ $currentItem->answer }}</p>
                        @if ($currentItem->note)
                            <p class="mt-3 text-xs leading-5 text-slate-400">{{ $currentItem->note }}</p>
                        @endif

                        <p class="mt-5 text-xs font-bold text-slate-400">思い出せた感触を選択</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-4">
                            @foreach ($ratingLabels as $rating => $meta)
                                <form method="POST" action="{{ route('plans.tasks.study_recall.items.review', [$plan, $task, $currentItem]) }}" data-mutation-once>
                                    @csrf
                                    <input type="hidden" name="rating" value="{{ $rating }}">
                                    <input type="hidden" name="review_request_id" value="{{ (string) IlluminateSupportStr::uuid() }}">
                                    <button type="submit" class="w-full rounded-xl border p-3 text-left transition hover:bg-white/[0.06] {{ $meta['class'] }}">
                                        <strong class="block text-sm">{{ $meta['label'] }}</strong>
                                        <span class="mt-1 block text-[10px] opacity-70">{{ $meta['hint'] }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </details>
            </section>
        @elseif ($stats['total'] > 0)
            <section class="page-card border-emerald-300/20 p-5 sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-300">RECALL COMPLETE</p>
                <h2 class="mt-2 text-xl font-black text-slate-50">今すぐ確認するカードはありません</h2>
                <p class="mt-2 text-sm leading-6 text-slate-300">次回の復習時期まで間隔を空けます。覚えているカードを無意味に繰り返すより、忘れかけた頃に思い出すことを優先します。</p>
            </section>
        @endif

        <section class="page-card p-5 sm:p-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-violet-300">ADD CARDS</p>
                <h2 class="mt-1 text-lg font-black text-slate-50">Recallカードを追加</h2>
                <p class="mt-2 text-xs leading-5 text-slate-500">1行につき「表 | 裏」。タブ区切りも使えます。一度に100件まで。</p>
            </div>
            <form method="POST" action="{{ route('plans.tasks.study_recall.items.store', [$plan, $task]) }}" class="mt-4">
                @csrf
                <textarea name="cards_text" rows="6" class="input-field w-full" placeholder="abandon | 放棄する&#10;accurate | 正確な&#10;maintain | 維持する">{{ old('cards_text') }}</textarea>
                @error('cards_text')
                    <p class="mt-2 text-xs text-rose-300">{{ $message }}</p>
                @enderror
                <button type="submit" class="btn-primary mt-3">カードを追加</button>
            </form>
        </section>

        @if ($items->isNotEmpty())
            <details class="page-card p-4 sm:p-5">
                <summary class="cursor-pointer text-sm font-black text-slate-200">カード一覧・管理</summary>
                <div class="mt-4 grid gap-2">
                    @foreach ($items as $item)
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/8 bg-white/[0.025] p-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-100">{{ $item->prompt }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $item->answer }}</p>
                                <p class="mt-1 text-[10px] text-slate-600">
                                    {{ $item->isMastered() ? '定着候補' : ($item->repetitions === 0 ? '未学習' : '学習中') }}
                                    · 次回 {{ $item->due_at ? $item->due_at->diffForHumans() : '今' }}
                                </p>
                            </div>
                            <form method="POST" action="{{ route('plans.tasks.study_recall.items.destroy', [$plan, $task, $item]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-secondary px-3 py-2 text-xs">削除</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        @if ($recentReviews->isNotEmpty())
            <details class="page-card p-4 sm:p-5">
                <summary class="cursor-pointer text-sm font-black text-slate-200">最近のRecall履歴</summary>
                <div class="mt-4 space-y-2">
                    @foreach ($recentReviews as $review)
                        <div class="rounded-xl border border-white/8 bg-white/[0.02] px-3 py-2 text-xs text-slate-400">
                            <span class="font-bold text-slate-200">{{ $review->item?->prompt ?? '削除済みカード' }}</span>
                            · {{ $ratingLabels[$review->rating]['label'] ?? $review->rating }}
                            · {{ $review->interval_after_days > 0 ? $review->interval_after_days.'日後' : '短時間後' }}
                            · {{ $review->reviewed_at?->diffForHumans() }}
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
@endsection
