@extends('layouts.app')

@section('title', '共同計画に参加 | Canovia')

@section('content')
<div class="mx-auto max-w-2xl py-6 sm:py-10">
    <section class="glass-card rounded-3xl border border-cyan-300/15 bg-slate-950/70 p-5 shadow-2xl sm:p-7">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-cyan-300">COLLABORATIVE PLAN INVITE</p>
        <h1 class="mt-2 text-2xl font-black text-slate-50">共同計画に招待されています</h1>
        <p class="mt-3 text-sm leading-7 text-slate-300">
            この計画へ参加するにはCanoviaアカウントが必要です。参加は無料で、参加直後は閲覧者として追加されます。
        </p>

        <div class="mt-6 rounded-2xl border border-white/10 bg-white/5 p-4">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">PLAN</p>
            <h2 class="mt-1 text-lg font-bold text-slate-50">{{ $plan->title }}</h2>
            @if ($plan->user)
                <p class="mt-2 text-sm text-slate-400">オーナー：{{ $plan->user->name }}</p>
            @endif
        </div>

        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <a href="{{ route('auth.login.form') }}" class="btn-primary w-full justify-center">ログインして参加</a>
            <a href="{{ route('auth.register.form') }}" class="btn-secondary w-full justify-center">無料でアカウント作成</a>
        </div>

        <p class="mt-5 text-xs leading-6 text-slate-500">
            認証後はこの招待へ自動で戻ります。編集権限はオーナーが必要に応じて付与できます。
        </p>
    </section>
</div>
@endsection
