@extends('layouts.app')
@section('title', '見つかりません | Canovia')
@section('content')
<div class="mx-auto max-w-lg">
    <section class="page-card p-6 sm:p-8">
        <p class="text-sm font-semibold text-sky-400">404</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-50">このページは見つかりません</h1>
        <p class="mt-3 text-sm leading-7 text-slate-400">URLが古いか、現在の端末・アカウントからは表示できないPlanかもしれません。</p>
        <a href="{{ route('home') }}" class="btn-primary mt-6">今のCanoviaへ戻る</a>
    </section>
</div>
@endsection
