@extends('layouts.app')

@section('title', $plan->title . ' の振り返り | Canovia')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        <header class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-400">Achievement review</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-100 font-heading">{{ $plan->title }}</h1>
                <p class="mt-2 text-sm text-slate-400">{{ $plan->start_date->format('Y-m-d') }} → {{ $completedAt?->format('Y-m-d') ?? '完了' }}</p>
            </div>
            <a href="{{ route('achievements.index') }}" class="btn-secondary">達成した計画へ戻る</a>
        </header>

        <main class="assistant-chat-shell">
            <div class="assistant-message-row assistant-message-left">
                <div class="assistant-avatar">CV</div>
                <div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
                    <p class="assistant-speaker">Canovia サポーター</p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-900">この計画は達成済みです</h2>
                    <p class="mt-3 leading-7 text-slate-700">
                        {{ $praise }}
                    </p>

                    <div class="mt-5 mobile-metric-strip sm:grid sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">取り組み期間</p>
                            <p class="mt-1 font-bold text-slate-900">{{ $durationDays ? $durationDays . '日' : '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">総作業時間</p>
                            <p class="mt-1 font-bold text-slate-900">{{ round($totalMinutes / 60, 1) }}時間</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">完了Task</p>
                            <p class="mt-1 font-bold text-slate-900">{{ $doneTasks }}件</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="text-xs text-slate-500">計画更新</p>
                            <p class="mt-1 font-bold text-slate-900">{{ $adjustmentCount }}回</p>
                        </div>
                    </div>
                </div>
            </div>

            @if ($plan->description)
                <div class="assistant-message-row assistant-message-left">
                    <div class="assistant-avatar">CV</div>
                    <div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
                        <p class="assistant-speaker">計画の最終形</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-700">{{ $plan->description }}</p>
                    </div>
                </div>
            @endif

            <div class="assistant-message-row assistant-message-left">
                <div class="assistant-avatar">CV</div>
                <div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
                    <p class="assistant-speaker">ここまでの流れ</p>
                    <div class="mt-4 space-y-3">
                        @forelse ($timeline as $event)
                            <div class="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-bold text-slate-900">{{ $event['title'] }}</p>
                                    <span class="text-xs text-slate-500">{{ $event['date_label'] }}</span>
                                </div>
                                @if (! empty($event['summary']))
                                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $event['summary'] }}</p>
                                @endif
                                @if (($event['actual_minutes'] ?? null) !== null)
                                    <p class="mt-2 text-xs text-slate-500">実作業 {{ $event['actual_minutes'] }}分</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">詳細な作業履歴はありません。</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </main>
    </div>
@endsection
