@extends('layouts.app')

@section('title', '自分の計画一覧 | Pace Keeper')

@section('content')
    <section class="mb-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="mb-2 text-sm font-semibold text-slate-500">My Plans</p>

                <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                    自分の計画一覧
                </h1>

                <p class="mt-3 max-w-3xl leading-7 text-slate-600">
                    このブラウザで作成した計画を表示しています。
                    Cookie を削除した場合や別の端末では、編集権限付きの計画として表示されません。
                </p>
            </div>

            <a href="{{ route('plans.create') }}"
               class="btn-primary">
                新しい計画を作成する
            </a>
        </div>
    </section>

    @if ($myPlans->isEmpty())
        <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <h2 class="break-words text-xl font-bold text-slate-900">
                まだ計画がありません
            </h2>

            <p class="mt-3 text-slate-600">
                まずは目標だけで大丈夫です。最初の計画を作成してみましょう。
            </p>

            <div class="mt-6">
                <a href="{{ route('plans.create') }}"
                   class="btn-primary">
                    計画を作成する
                </a>
            </div>
        </section>
    @else
        <section class="grid gap-4">
            @foreach ($myPlans as $item)
                @php
                    $plan = $item['plan'];
                    $progress = $item['progress'];
                    $status = $progress['status'] ?? '予定通り';

                    $progressColorClass = match ($status) {
                        '順調' => 'progress-green',
                        '予定通り' => 'progress-blue',
                        '遅れ気味' => 'progress-amber',
                        '期限切れ' => 'progress-red',
                        '期限未設定' => 'progress-slate',
                        default => 'progress-slate',
                    };

                    $statusClass = match ($status) {
                        '順調' => 'status-green',
                        '予定通り' => 'status-blue',
                        '遅れ気味' => 'status-amber',
                        '期限切れ' => 'status-red',
                        '期限未設定' => 'status-slate',
                        default => 'status-slate',
                    };
                @endphp

                <article class="page-card pk-v20-plan-list-card p-4 sm:p-6">
                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <h2 class="break-words text-xl font-bold text-slate-900">
                                    {{ $plan->title }}
                                </h2>

                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">
                                    {{ $plan->category ?? '未設定' }}
                                </span>

                                @if ($plan->is_public)
                                    <span class="rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-200">
                                        公開
                                    </span>
                                @else
                                    <span class="rounded-full bg-slate-50 px-2 py-1 text-xs font-medium text-slate-500 ring-1 ring-slate-200">
                                        非公開
                                    </span>
                                @endif
                            </div>

                            <p class="text-sm text-slate-500">
                                期間：{{ $plan->start_date }} 〜 {{ $plan->deadline?->format('Y-m-d') ?? '期限未設定' }}
                            </p>
                        </div>

                        <div class="flex w-full flex-wrap gap-2 sm:w-auto sm:shrink-0">
                            <a href="{{ route('plans.show', $plan) }}" class="btn-secondary px-3 py-2 text-sm">
                                詳細
                            </a>

                            <a href="{{ route('plans.edit', $plan) }}" class="btn-primary px-3 py-2 text-sm">
                                編集
                            </a>
                        </div>
                    </div>

                    <div class="pk-v20-plan-metrics mt-5 grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-4">
                        <div class="metric-card">
                            <p class="text-sm text-slate-500">進捗率</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">
                                {{ $progress['weighted_progress_percent'] }}%
                            </p>
                        </div>

                        <div class="metric-card">
                            <p class="text-sm text-slate-500">必要時間 / 日</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">
                                {{ $progress['remaining_days'] === null ? '—' : $progress['daily_required_minutes'] . '分' }}
                            </p>
                        </div>

                        <div class="metric-card">
                            <p class="text-sm text-slate-500">残り日数</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900">
                                {{ $progress['remaining_days'] === null ? '—' : $progress['remaining_days'] . '日' }}
                            </p>
                        </div>

                        <div class="metric-card">
                            <p class="text-sm text-slate-500">状態</p>
                            <p class="mt-2">
                                <span class="status-pill {{ $statusClass }}">{{ $status }}</span>
                            </p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="text-slate-600">進捗</span>
                            <span class="font-semibold text-slate-900">
                                {{ $progress['weighted_progress_percent'] }}%
                            </span>
                        </div>

                        <div class="progress-track">
                            <div
                                class="progress-bar {{ $progressColorClass }}"
                                style="width: {{ min(100, max(0, $progress['weighted_progress_percent'])) }}%;"
                            ></div>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>
    @endif
@endsection