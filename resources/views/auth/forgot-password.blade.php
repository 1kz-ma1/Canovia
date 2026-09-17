@extends('layouts.app')

@section('title', 'パスワード再設定 | Canovia')

@section('content')
<div class="mx-auto max-w-md">
    <section class="page-card p-6 sm:p-8">
        <p class="text-sm font-semibold text-sky-400">Account recovery</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-50">パスワードを再設定</h1>
        <p class="mt-3 text-sm leading-7 text-slate-400">登録したメールアドレスへ再設定リンクを送ります。</p>

        @if ($errors->any())
            <div class="assistant-notice assistant-notice-error mt-5">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block">
                <span class="text-sm font-semibold text-slate-300">メールアドレス</span>
                <input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required class="form-control mt-2">
            </label>
            <button type="submit" class="btn-primary w-full">再設定リンクを送る</button>
        </form>

        <p class="mt-6 text-center text-sm text-slate-400"><a href="{{ route('auth.login.form') }}" class="font-semibold text-sky-300 hover:text-sky-200">ログインへ戻る</a></p>
    </section>
</div>
@endsection
