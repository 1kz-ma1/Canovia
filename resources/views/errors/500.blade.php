@extends('layouts.app')
@section('title', '一時的なエラー | Canovia')
@section('content')
<div class="mx-auto max-w-lg">
    <section class="page-card p-6 sm:p-8">
        <p class="text-sm font-semibold text-red-400">500</p>
        <h1 class="mt-2 text-2xl font-bold text-slate-50">一時的に処理できませんでした</h1>
        <p class="mt-3 text-sm leading-7 text-slate-400">入力内容を繰り返し送信せず、ホームへ戻って状態を確認してください。</p>
        <a href="{{ route('home') }}" class="btn-primary mt-6">ホームへ戻る</a>
    </section>
</div>
@endsection
