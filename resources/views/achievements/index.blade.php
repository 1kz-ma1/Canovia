@extends('layouts.app')

@section('title', '達成した計画 | Canovia')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <header>
            <p class="text-sm font-semibold text-emerald-400">Achievements</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-100 font-heading">達成した計画</h1>
            <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300">
                結果だけでなく、どんな進み方をして最後まで到達したかも振り返れます。
            </p>
        </header>

        @if ($completedPlans->isEmpty())
            <section class="page-card p-8 text-center">
                <p class="text-lg font-bold text-slate-900">まだ達成済みの計画はありません</p>
                <p class="mt-2 text-sm text-slate-600">Planを完了すると、ここに成果と振り返りが残ります。</p>
                <a href="{{ route('home') }}" class="btn-primary mt-5 inline-flex">今の計画を見る</a>
            </section>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($completedPlans as $item)
                    <a href="{{ route('achievements.show', $item['plan']) }}" class="page-card block p-6 transition hover:-translate-y-0.5 hover:shadow-lg">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Completed</p>
                                <h2 class="mt-2 text-xl font-bold text-slate-900">{{ $item['plan']->title }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ $item['plan']->category ?: 'カテゴリ未設定' }}</p>
                            </div>
                            <span class="badge badge-slate">100%</span>
                        </div>

                        <div class="mt-5 grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <p class="text-xs text-slate-500">完了Task</p>
                                <p class="mt-1 font-bold text-slate-900">{{ $item['completed_tasks'] }}件</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">実作業</p>
                                <p class="mt-1 font-bold text-slate-900">{{ round($item['actual_minutes'] / 60, 1) }}時間</p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-500">計画更新</p>
                                <p class="mt-1 font-bold text-slate-900">{{ $item['adjustment_count'] }}回</p>
                            </div>
                        </div>

                        <p class="mt-5 text-sm font-semibold text-sky-600">振り返る →</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
