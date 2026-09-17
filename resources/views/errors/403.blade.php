@extends('layouts.app')
@section('title', 'アクセスできません | Canovia')
@section('content')
<div class="mx-auto max-w-lg">
    <section class="page-card p-6 sm:p-8">
        <p class="text-sm font-semibold text-amber-400">403</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-50">この操作はできません</h1>
        <p class="mt-3 text-sm leading-7 text-slate-400">別のアカウントや、別のブラウザで作ったデータの可能性があります。</p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('home') }}" class="btn-primary">ホームへ戻る</a>
            @guest<a href="{{ route('auth.login.form') }}" class="btn-secondary">ログイン</a>@endguest
        </div>
    </section>
</div>
@endsection
