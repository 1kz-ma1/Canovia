@extends('layouts.app')

@section('title', '共同計画に参加 | Canovia')

@section('content')
<section class="mx-auto max-w-xl page-card p-6 sm:p-8">
    <p class="pk-v18-eyebrow">CANOVIA / TOGETHER</p>
    <h1 class="mt-2 text-2xl font-bold text-slate-50">共同計画に参加</h1>
    <p class="mt-3 text-sm leading-7 text-slate-300">共有された参加コードを入力してください。参加直後は閲覧者として安全に参加します。</p>

    @if ($errors->any())
        <div class="assistant-notice assistant-notice-error mt-5">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('collaboration.join.code') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block">
            <span class="text-sm font-semibold text-slate-200">参加コード</span>
            <input name="join_code" value="{{ old('join_code') }}" placeholder="CNV-7F3K9Q" autocomplete="off" autocapitalize="characters" class="form-control mt-2 w-full uppercase tracking-[0.12em]" required>
        </label>
        <button type="submit" class="btn-primary w-full justify-center">参加する</button>
    </form>

    <p class="mt-5 text-xs leading-6 text-slate-500">共同計画への参加にはログインが必要です。編集権限は計画オーナーが必要に応じて付与します。</p>
</section>
@endsection
