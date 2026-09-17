@extends('layouts.app')

@section('title', '作業結果 | Canovia')

@section('content')
    <div class="mx-auto max-w-2xl space-y-6">
        <section class="page-card p-8">
            <p class="text-sm font-semibold text-sky-400">Session recorded</p>
            <h1 class="mt-2 text-3xl font-bold text-slate-100">作業時間は記録済みです</h1>
            <p class="mt-3 leading-7 text-slate-400">
                Taskの進捗や次のActionは、共通の「計画を更新」フローから普段使っているAIと一緒に反映します。
            </p>
            @if ($workSession->plan)
                <a
                    href="{{ route('plans.review_assistant.show', ['plan' => $workSession->plan, 'work_session_id' => $workSession->id]) }}"
                    class="btn-primary mt-6 inline-flex"
                >計画更新へ進む</a>
            @else
                <a href="{{ route('home') }}" class="btn-secondary mt-6 inline-flex">ダッシュボードへ戻る</a>
            @endif
        </section>
    </div>
@endsection
