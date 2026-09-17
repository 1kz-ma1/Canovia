@extends('layouts.app')

@section('title', '新しいパスワード | Canovia')

@section('content')
<div class="mx-auto max-w-md">
    <section class="page-card p-6 sm:p-8">
        <p class="text-sm font-semibold text-emerald-400">Account recovery</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-50">新しいパスワードを設定</h1>

        @if ($errors->any())
            <div class="assistant-notice assistant-notice-error mt-5">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="block">
                <span class="text-sm font-semibold text-slate-300">メールアドレス</span>
                <input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required class="form-control mt-2">
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-slate-300">新しいパスワード（8文字以上）</span>
                <input type="password" name="password" autocomplete="new-password" required class="form-control mt-2">
            </label>
            <label class="block">
                <span class="text-sm font-semibold text-slate-300">パスワード確認</span>
                <input type="password" name="password_confirmation" autocomplete="new-password" required class="form-control mt-2">
            </label>
            <button type="submit" class="btn-primary w-full">パスワードを更新</button>
        </form>
    </section>
</div>
@endsection
