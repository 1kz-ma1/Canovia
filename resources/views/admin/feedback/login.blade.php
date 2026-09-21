@extends('layouts.app')

@section('title', 'Canovia Admin')

@section('content')
    <div class="mx-auto max-w-lg">
        <section class="page-card p-6 sm:p-8">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-cyan-300">CANOVIA ADMIN</p>
            <h1 class="mt-2 text-2xl font-black text-slate-50">運営画面へログイン</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">認証後、フィードバック管理や計画作成・更新の診断へ移動できます。</p>

            @if (! $passwordConfigured)
                <div class="mt-5 rounded-xl border border-amber-400/30 bg-amber-500/10 p-4 text-sm leading-6 text-amber-100">
                    管理パスワードが未設定です。Renderでは <code>CANOVIA_ADMIN_PASSWORD</code> を設定してください。既存の <code>FEEDBACK_ADMIN_PASSWORD</code> / <code>TEMPLATE_ADMIN_PASSWORD</code> も互換用として利用できます。
                </div>
            @endif

            <form method="POST" action="{{ route('admin.authenticate') }}" class="mt-6 space-y-4">
                @csrf
                <label class="block">
                    <span class="text-sm font-bold text-slate-300">管理パスワード</span>
                    <input type="password" name="password" class="form-control mt-2" required autocomplete="current-password">
                </label>
                @error('password')
                    <p class="text-sm text-rose-300">{{ $message }}</p>
                @enderror
                <button type="submit" class="btn-primary w-full">Canovia Adminを開く</button>
            </form>
        </section>
    </div>
@endsection
