@extends('layouts.app')

@section('title', 'Feedback Admin | Canovia')

@section('content')
    <div class="mx-auto max-w-lg">
        <section class="page-card p-6 sm:p-8">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-sky-300">Feedback Admin</p>
            <h1 class="mt-2 text-2xl font-black text-slate-50">フィードバック管理</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">Canoviaに届いた評価・不具合・要望を確認する運営用画面です。</p>

            @if (! $passwordConfigured)
                <div class="mt-5 rounded-xl border border-amber-400/30 bg-amber-500/10 p-4 text-sm leading-6 text-amber-100">
                    管理パスワードが未設定です。Renderでは <code>FEEDBACK_ADMIN_PASSWORD</code> を設定してください。既存の <code>TEMPLATE_ADMIN_PASSWORD</code> があればそれも利用できます。
                </div>
            @endif

            <form method="POST" action="{{ route('admin.feedback.authenticate') }}" class="mt-6 space-y-4">
                @csrf
                <label class="block">
                    <span class="text-sm font-bold text-slate-300">管理パスワード</span>
                    <input type="password" name="password" class="form-control mt-2" required autocomplete="current-password">
                </label>
                @error('password')
                    <p class="text-sm text-rose-300">{{ $message }}</p>
                @enderror
                <button type="submit" class="btn-primary w-full">管理画面を開く</button>
            </form>
        </section>
    </div>
@endsection
