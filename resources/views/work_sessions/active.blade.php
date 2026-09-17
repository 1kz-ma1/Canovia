@extends('layouts.app')

@section('title', '作業中 | Canovia')

@section('content')
    <div class="flex min-h-[100dvh] w-full items-center justify-center px-4 py-6 md:px-6">
        <div class="w-full max-w-2xl">
            @if (session('status'))
                <div class="assistant-notice assistant-notice-info mb-4" data-auto-toast>{{ session('status') }}</div>
            @endif

            <section class="page-card p-6 text-center md:p-8">
                <div class="mx-auto inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-bold {{ $workSession->status === 'paused' ? 'border-amber-400/30 bg-amber-500/10 text-amber-300' : 'border-emerald-400/30 bg-emerald-500/10 text-emerald-300' }}">
                    <span class="h-2 w-2 rounded-full {{ $workSession->status === 'paused' ? 'bg-amber-400' : 'bg-emerald-400' }}"></span>
                    {{ $workSession->status === 'paused' ? '一時停止中' : '作業中' }}
                </div>

                <h1 class="mx-auto mt-5 max-w-xl text-2xl font-bold text-slate-100 md:text-3xl">{{ $workSession->task?->title ?? 'Taskは削除されました' }}</h1>
                <p class="mt-2 text-sm text-slate-400">{{ $workSession->plan?->title }}{{ $workSession->intended_minutes ? '・目安 ' . $workSession->intended_minutes . '分' : '' }}</p>

                <div class="my-8 md:my-10">
                    <p
                        class="text-[clamp(4rem,22vw,7rem)] font-black leading-none tracking-tight tabular-nums text-slate-50"
                        data-work-timer
                        data-onboarding-target="work-timer"
                        data-started-at="{{ $workSession->started_at?->toIso8601String() }}"
                        data-paused-at="{{ $workSession->paused_at?->toIso8601String() }}"
                        data-paused-seconds="{{ $workSession->paused_seconds ?? 0 }}"
                        data-session-status="{{ $workSession->status }}"
                        data-initial-active-seconds="{{ $activeSeconds }}"
                    >00:00</p>
                    <p class="mt-3 text-xs leading-5 text-slate-500">一時停止中の時間は実作業時間に含まれません。</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    @if ($workSession->status === 'paused')
                        <form method="POST" action="{{ route('work_sessions.resume', $workSession) }}">
                            @csrf
                            <button class="btn-primary w-full justify-center py-3 text-base">再開する</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('work_sessions.pause', $workSession) }}">
                            @csrf
                            <button class="btn-secondary w-full justify-center py-3 text-base">一時停止</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('work_sessions.complete', $workSession) }}">
                        @csrf
                        <button class="btn-primary w-full justify-center py-3 text-base">記録して終了</button>
                    </form>
                </div>

                <details class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/35 p-3 text-left">
                    <summary class="cursor-pointer text-center text-sm font-semibold text-slate-400">その他</summary>
                    <form method="POST" action="{{ route('work_sessions.interrupt', $workSession) }}" class="mt-3">
                        @csrf
                        <button class="btn-secondary w-full justify-center">中断して終了</button>
                    </form>
                </details>
            </section>

            <p class="mt-4 text-center text-xs leading-5 text-slate-500">終了後に作業時間を保存し、必要ならAIで計画へ反映できます。</p>
        </div>
    </div>
@endsection
