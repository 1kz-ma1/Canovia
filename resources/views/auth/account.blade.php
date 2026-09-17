@extends('layouts.app')
@section('title', 'アカウント | Canovia')
@section('content')
<div class="mx-auto max-w-lg">
    <section class="page-card p-6 sm:p-8">
        <p class="text-sm font-semibold text-emerald-400">Protected</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-50">{{ $user->name }}</h1>
        <p class="mt-1 text-sm text-slate-400">{{ $user->email }}</p>
        <div class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 p-4 text-sm leading-6 text-emerald-100">
            計画はアカウントに紐づいています。Cookie削除・別端末・PWA再インストール後も、ログインすれば復元できます。
        </div>
        <form method="POST" action="{{ route('auth.logout') }}" class="mt-6" data-clear-offline-state>
            @csrf
            <button type="submit" class="btn-secondary w-full">ログアウト</button>
        </form>
    </section>
</div>
@endsection
