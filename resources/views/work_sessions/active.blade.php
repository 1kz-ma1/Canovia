@extends('layouts.app')

@section('title', '作業中 | Canovia')

@section('content')
    <div
        class="flex min-h-[100dvh] w-full items-center justify-center px-4 py-6 md:px-6"
        data-work-session-safety
        data-session-id="{{ $workSession->id }}"
        data-session-status="{{ $workSession->status }}"
        data-intended-minutes="{{ $workSession->intended_minutes ?? 0 }}"
        data-paused-seconds="{{ $workSession->paused_seconds ?? 0 }}"
    >
        <div class="w-full max-w-2xl">
            @if (session('status'))
                <div class="assistant-notice assistant-notice-info mb-4" data-auto-toast>{{ session('status') }}</div>
            @endif
            @error('timer')
                <div class="assistant-notice mb-4 border-amber-400/30 bg-amber-500/10 text-amber-100">{{ $message }}</div>
            @enderror

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
                    <p class="mt-3 text-xs leading-5 text-slate-500">一時停止と、復帰時に「休憩」と確認した時間は実作業時間に含まれません。</p>
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

                    <form method="POST" action="{{ route('work_sessions.complete', $workSession) }}" data-work-finish-form data-work-complete-form data-loading-skip>
                        @csrf
                        <input type="hidden" name="timer_action" value="normal" data-timer-action>
                        <input type="hidden" name="away_started_at" value="" data-timer-away-started>
                        <input type="hidden" name="additional_paused_seconds" value="0" data-timer-extra-paused>
                        <input type="hidden" name="manual_minutes" value="" data-timer-manual-minutes>
                        <input type="hidden" name="duration_confirmed" value="0" data-timer-duration-confirmed>
                        <button class="btn-primary w-full justify-center py-3 text-base">記録して終了</button>
                    </form>
                </div>

                <details class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/35 p-3 text-left">
                    <summary class="cursor-pointer text-center text-sm font-semibold text-slate-400">その他</summary>
                    <form method="POST" action="{{ route('work_sessions.interrupt', $workSession) }}" class="mt-3" data-work-finish-form data-loading-skip>
                        @csrf
                        <input type="hidden" name="timer_action" value="normal" data-timer-action>
                        <input type="hidden" name="away_started_at" value="" data-timer-away-started>
                        <input type="hidden" name="additional_paused_seconds" value="0" data-timer-extra-paused>
                        <input type="hidden" name="manual_minutes" value="" data-timer-manual-minutes>
                        <input type="hidden" name="duration_confirmed" value="0" data-timer-duration-confirmed>
                        <button class="btn-secondary w-full justify-center">中断して終了</button>
                    </form>
                </details>
            </section>

            <p class="mt-4 text-center text-xs leading-5 text-slate-500">終了後に作業時間を保存し、必要ならAIで計画へ反映できます。</p>
        </div>

        <dialog class="ui-settings-dialog" data-timer-away-dialog aria-labelledby="timer-away-title">
            <div class="ui-settings-card max-w-lg">
                <div>
                    <p class="pk-v18-card-kicker">TIMER CHECK</p>
                    <h2 id="timer-away-title" class="mt-1 text-xl font-bold text-slate-50">離れていた時間を確認します</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-300" data-timer-away-label>しばらくCanoviaを離れていました。</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">画面を閉じただけでは作業終了と決めつけません。実際の状況を選んでください。</p>
                </div>

                <div class="mt-5 grid gap-2">
                    <button type="button" class="btn-primary w-full justify-center" data-timer-away-action="continued">作業を続けていた</button>
                    <button type="button" class="btn-secondary w-full justify-center" data-timer-away-action="break">休憩していた・今から再開</button>
                    <button type="button" class="btn-secondary w-full justify-center" data-timer-away-action="end">離れた時点で作業を終えていた</button>
                    <button type="button" class="btn-secondary w-full justify-center" data-timer-away-action="manual">実際の作業時間を入力する</button>
                </div>

                <div class="mt-4 hidden rounded-2xl border border-slate-700 bg-slate-950/45 p-4" data-timer-manual-wrap>
                    <label class="block text-left">
                        <span class="form-label">実際の作業時間（分）</span>
                        <input type="number" min="1" max="480" step="1" class="form-control mt-2" data-timer-manual-input>
                    </label>
                    <button type="button" class="btn-primary mt-3 w-full justify-center" data-timer-manual-apply>この時間で記録して終了</button>
                </div>

                <button type="button" class="mt-4 w-full text-center text-xs font-semibold text-slate-500 hover:text-slate-300" data-timer-away-later>今は判断せずタイマーへ戻る</button>
            </div>
        </dialog>

        <dialog class="ui-settings-dialog" data-timer-long-dialog aria-labelledby="timer-long-title">
            <div class="ui-settings-card max-w-lg">
                <div>
                    <p class="pk-v18-card-kicker">LONG SESSION CHECK</p>
                    <h2 id="timer-long-title" class="mt-1 text-xl font-bold text-slate-50">長時間の記録を確認します</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-300" data-timer-long-label>通常より長い作業時間です。</p>
                    <p class="mt-1 text-xs leading-5 text-slate-500">寝落ちや終了忘れをそのまま実績にしないための確認です。</p>
                </div>

                <div class="mt-5 grid gap-2">
                    <button type="button" class="btn-primary w-full justify-center" data-timer-long-record>この時間で記録する</button>
                    <button type="button" class="btn-secondary w-full justify-center" data-timer-long-manual>実際の作業時間に修正する</button>
                    <button type="button" class="btn-secondary w-full justify-center" data-timer-long-cancel>タイマーへ戻る</button>
                </div>

                <div class="mt-4 hidden rounded-2xl border border-slate-700 bg-slate-950/45 p-4" data-timer-long-manual-wrap>
                    <label class="block text-left">
                        <span class="form-label">実際の作業時間（分）</span>
                        <input type="number" min="1" max="480" step="1" class="form-control mt-2" data-timer-long-manual-input>
                    </label>
                    <button type="button" class="btn-primary mt-3 w-full justify-center" data-timer-long-manual-apply>修正して記録</button>
                </div>
            </div>
        </dialog>
    </div>
@endsection
