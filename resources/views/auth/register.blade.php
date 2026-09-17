@extends('layouts.app')

@section('title', 'データを保護 | Canovia')

@section('content')
<div class="mx-auto max-w-md">
    <section class="page-card p-6 sm:p-8">
        <p class="text-sm font-semibold text-emerald-400">任意のアカウント</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-50">今のCanoviaをアカウントに保存</h1>
        <p class="mt-3 text-sm leading-7 text-slate-400">登録は任意です。現在このブラウザで作った計画もそのまま引き継ぎ、登録完了後はログインした状態から続けられます。</p>
        <div class="mt-4 rounded-2xl border border-amber-400/20 bg-amber-500/10 p-3 text-xs leading-6 text-amber-100">Canoviaは現在β版です。重要な個人情報や機密情報は計画本文に入力しないでください。</div>

        <form method="POST" action="{{ route('auth.register') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span class="text-sm font-semibold text-slate-300">表示名</span>
                <input type="text" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="80" class="form-control mt-2">
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-slate-300">メールアドレス</span>
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required class="form-control mt-2">
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-slate-300">パスワード（8文字以上）</span>
                <input type="password" name="password" autocomplete="new-password" required class="form-control mt-2">
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-slate-300">パスワード確認</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" required class="form-control mt-2">
            </label>
            <button type="submit" class="btn-primary w-full">登録してそのまま続ける</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400">登録済みなら <a href="{{ route('auth.login.form') }}" class="font-semibold text-sky-300 hover:text-sky-200">ログイン</a></p>
    </section>
</div>
@endsection
