@extends('layouts.app')

@section('title', $plan->title . ' | Pace Keeper')

@section('content')
    @php
        $status = $progress['status'] ?? '予定通り';

        $progressColorClass = match ($status) {
            '順調' => 'progress-green',
            '予定通り' => 'progress-blue',
            '遅れ気味' => 'progress-amber',
            '期限切れ' => 'progress-red',
            default => 'progress-slate',
        };

        $statusClass = match ($status) {
            '順調' => 'status-green',
            '予定通り' => 'status-blue',
            '遅れ気味' => 'status-amber',
            '期限切れ' => 'status-red',
            default => 'status-slate',
        };
    @endphp
    <section class="mb-8">
        
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            {{ $plan->title }}
        </h1>

        <p class="mt-3 max-w-3xl leading-7 text-slate-600">
            {{ $plan->description ?? '説明はまだ設定されていません。' }}
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
            <span class="rounded-full bg-green-50 px-3 py-1 text-sm font-medium text-green-700 ring-1 ring-green-200">
                公開計画
            </span>

            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600">
                {{ $plan->category ?? '未設定' }}
            </span>
        </div>
    </section>

    <section class="mb-8 rounded-2xl border border-blue-200 bg-blue-50 p-5">
        <h2 class="font-bold text-blue-900">閲覧専用ページ</h2>
        <p class="mt-2 text-sm leading-6 text-blue-800">
            このページは公開された計画の閲覧専用ページです。
            編集や作業ログの追加はできません。
        </p>
    </section>

    <section class="mb-8 mobile-metric-strip md:grid md:grid-cols-2 lg:grid-cols-4">
        <div class="info-card p-5">
            <p class="text-sm text-slate-500">期間</p>
            <p class="mt-2 font-bold text-slate-900">
                {{ $plan->start_date }} 〜 {{ $plan->deadline?->format('Y-m-d') ?? '期限未設定' }}
            </p>
        </div>

        <div class="info-card p-5">
            <p class="text-sm text-slate-500">進捗率</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">
                {{ $progress['weighted_progress_percent'] }}%
            </p>
        </div>

        <div class="info-card p-5">
            <p class="text-sm text-slate-500">合計想定時間</p>
            <p class="mt-2 text-2xl font-bold text-slate-900">
                {{ round($progress['total_estimated_minutes'] / 60, 1) }}時間
            </p>
        </div>

        <div class="info-card p-5">
            <p class="text-sm text-slate-500">状態</p>
            <p class="mt-3">
                <span class="status-pill {{ $statusClass }}">{{ $status }}</span>
            </p>
        </div>
    </section>

    <section class="mb-8 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        <h2 class="mb-4 text-2xl font-bold text-slate-900">進捗情報</h2>

        <div class="mb-5">
            <div class="mb-1 flex justify-between text-sm">
                <span class="text-slate-600">現在の進捗率</span>
                <span class="font-semibold text-slate-900">{{ $progress['weighted_progress_percent'] }}%</span>
            </div>

            <div class="progress-track h-3">
                <div
                    class="progress-bar {{ $progressColorClass }}"
                    style="width: {{ min(100, max(0, $progress['weighted_progress_percent'])) }}%;"
                ></div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="metric-card">
                <p class="text-sm text-slate-500">残り日数</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ $progress['remaining_days'] === null ? '—' : $progress['remaining_days'] . '日' }}</p>
            </div>

            <div class="metric-card">
                <p class="text-sm text-slate-500">合計実績時間</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ round($progress['total_actual_minutes'] / 60, 1) }}時間</p>
            </div>

            <div class="metric-card">
                <p class="text-sm text-slate-500">期待進捗率</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ $progress['expected_progress_percent'] === null ? '—' : $progress['expected_progress_percent'] . '%' }}</p>
            </div>

            <div class="metric-card">
                <p class="text-sm text-slate-500">必要時間 / 日</p>
                <p class="mt-1 text-xl font-bold text-slate-900">{{ $progress['remaining_days'] === null ? '—' : $progress['daily_required_minutes'] . '分' }}</p>
            </div>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 text-2xl font-bold text-slate-900">タスク一覧</h2>

            @if ($plan->tasks->isEmpty())
                <p class="rounded-xl border border-dashed border-slate-300 p-5 text-slate-600">
                    タスクはまだ登録されていません。
                </p>
            @else
                <div class="space-y-4">
                    @foreach ($plan->tasks as $task)
                        @php
                            $taskProgressColorClass = match ($task->status) {
                                'done' => 'progress-green',
                                'doing' => 'progress-blue',
                                'todo' => 'progress-slate',
                                'cancelled' => 'progress-red',
                                default => 'progress-slate',
                            };
                        @endphp

                        <article class="rounded-xl border border-slate-200 p-4 {{ $task->status === 'cancelled' ? 'opacity-70' : '' }}">
                            <h3 class="font-bold text-slate-900">{{ $task->title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">{{ $task->description ?? '説明なし' }}</p>

                            <div class="mt-4 progress-track">
                                <div
                                    class="progress-bar {{ $taskProgressColorClass }}"
                                    style="width: {{ min(100, max(0, $task->progress_percent)) }}%;"
                                ></div>
                            </div>

                            <p class="mt-2 text-sm text-slate-600">
                                進捗率：{{ $task->progress_percent }}% / 想定時間：{{ round($task->estimated_minutes / 60, 1) }}時間
                            </p>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 text-2xl font-bold text-slate-900">作業ログ一覧</h2>

            @if ($plan->workLogs->isEmpty())
                <p class="rounded-xl border border-dashed border-slate-300 p-5 text-slate-600">
                    作業ログはまだ登録されていません。
                </p>
            @else
                <div class="space-y-4">
                    @foreach ($plan->workLogs->sortByDesc('worked_on') as $workLog)
                        <article class="rounded-xl border border-slate-200 p-4">
                            <p class="text-sm font-semibold text-slate-500">{{ $workLog->worked_on }}</p>

                            <h3 class="mt-1 font-bold text-slate-900">
                                {{ $workLog->task?->title ?? '計画全体の作業' }}
                            </h3>

                            <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                <div class="rounded-lg bg-slate-50 p-3">
                                    <p class="text-slate-500">作業時間</p>
                                    <p class="font-semibold text-slate-900">{{ $workLog->actual_minutes }}分</p>
                                </div>

                                <div class="rounded-lg bg-slate-50 p-3">
                                    <p class="text-slate-500">記録後の現在地</p>
                                    <p class="font-semibold text-slate-900">{{ $workLog->progress_after_percent !== null ? $workLog->progress_after_percent . '%' : '未評価' }}</p>
                                </div>
                            </div>

                            @if ($workLog->memo)
                                <p class="mt-3 text-sm leading-6 text-slate-600">{{ $workLog->memo }}</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="mt-8">
        <a href="{{ route('home') }}" class="text-sm font-medium text-slate-700 hover:underline">
            PaceKeeperへ
        </a>
    </div>
@endsection
