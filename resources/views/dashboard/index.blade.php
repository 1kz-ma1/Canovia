@extends('layouts.app')

@section('title', 'ホーム | Pace Keeper')

@section('content')
    @php
        $state = $dashboard['state'];
        $baseline = $dashboard['baseline'];
        $recommendation = $dashboard['recommendation'];
        $activeSession = $dashboard['active_work_session'];
        $todayRemaining = max(0, $dashboard['total_daily_required_minutes'] - $dashboard['today_minutes']);
        $calendarWeek = $dashboard['calendar_week'] ?? null;
    @endphp

    <div id="behaviorDashboard" class="space-y-7" data-event-url="{{ route('behavior_events.store') }}" data-navigation-url="{{ route('navigation.index') }}" data-work-started="{{ $activeSession ? 1 : 0 }}" data-onboarding-new-user="{{ $dashboard['plan_tabs']->isEmpty() ? '1' : '0' }}">
        <header class="pk-cosmic-page-heading pk-home-heading flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div class="relative z-10 max-w-xl">
                <p class="pk-cosmic-kicker">PACEKEEPER / HOME</p>
                <h1 class="text-3xl font-black tracking-tight text-slate-50 font-heading">いまの全体像</h1>
                <p class="pk-cosmic-subcopy">小さな一歩が、やがて大きな未来につながる。</p>
            </div>
            <img src="/brand/mascot-guide.webp" alt="" class="pk-page-mascot pk-page-mascot-home" aria-hidden="true">
            <div class="relative z-10 flex flex-wrap gap-2">
                <a href="{{ route('plans.create') }}" class="btn-primary" data-onboarding-target="create-plan">＋ 新しい計画</a>
                <form method="POST" action="{{ route('chat.start', 'review') }}">
                    @csrf
                    <button type="submit" class="btn-secondary">計画を更新</button>
                </form>
                <a href="{{ route('calendar.index') }}" class="btn-secondary">カレンダー</a>
                <a href="{{ route('my_plans.index') }}" class="btn-secondary">計画一覧</a>
            </div>
        </header>

        @if (session('success'))
            <div class="assistant-notice assistant-notice-success">{{ session('success') }}</div>
        @endif
        @if (session('status'))
            <div class="assistant-notice assistant-notice-info">{{ session('status') }}</div>
        @endif

        @if ($activeSession)
            <section class="page-card p-5 ring-1 ring-emerald-400/25">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-300">{{ $activeSession->status === 'paused' ? '一時停止中' : '作業中' }}</p>
                        <h2 class="mt-1 text-xl font-black text-slate-50">{{ $activeSession->task?->title ?? '作業中のタスク' }}</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ $activeSession->plan?->displayIcon() }} {{ $activeSession->plan?->title }}</p>
                    </div>
                    <a href="{{ route('work_sessions.active', $activeSession) }}" class="btn-primary">作業へ戻る</a>
                </div>
            </section>
        @endif

        @if (! empty($dashboard['continuity']))
            @php
                $continuity = $dashboard['continuity'];
            @endphp
            <section class="continuity-card plan-identity-shell" data-plan-accent="{{ $continuity['plan_accent'] ?? 'sky' }}">
                <div class="min-w-0">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-sky-300">前回の続き</p>
                    <p class="mt-2 plan-identity-chip text-xs"><span aria-hidden="true">{{ $continuity['plan_icon'] ?? '🧭' }}</span>{{ $continuity['plan_title'] }}</p>
                    <h2 class="mt-1 text-lg font-black text-slate-50">{{ $continuity['task_title'] }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-300">{{ $continuity['next_action_note'] ?: '前回の続きから、そのまま始められます。' }}</p>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 sm:mt-0">
                    @if ($continuity['is_active'])
                        <a href="{{ route('work_sessions.active', $continuity['session_id']) }}" class="btn-primary">作業へ戻る</a>
                    @elseif ($continuity['can_resume_task'])
                        <form method="POST" action="{{ route('work_sessions.start') }}" data-work-start-form>
                            @csrf
                            <input type="hidden" name="task_id" value="{{ $continuity['task_id'] }}">
                            <input type="hidden" name="source" value="dashboard-resume">
                            <button type="submit" class="btn-primary">続きから開始</button>
                        </form>
                    @endif
                    <a href="{{ route('roadmap.index', ['plan_id' => $continuity['plan_id']]) }}" class="btn-secondary">地図で見る</a>
                </div>
            </section>
        @endif

        @if (($dashboard['pending_plan_updates'] ?? collect())->isNotEmpty())
            <details class="page-card p-4">
                <summary class="cursor-pointer font-bold text-amber-200">まだ計画に反映していない作業が{{ $dashboard['pending_plan_updates']->count() }}件あります</summary>
                <div class="mt-3 space-y-2">
                    @foreach ($dashboard['pending_plan_updates'] as $pendingSession)
                        @if ($pendingSession->plan)
                            <a href="{{ route('plans.review_assistant.show', ['plan' => $pendingSession->plan, 'work_session_id' => $pendingSession->id]) }}" class="flex items-center justify-between gap-3 rounded-xl border border-amber-300/15 bg-slate-950/20 px-4 py-3">
                                <span class="min-w-0"><span class="block truncate font-semibold text-slate-100">{{ $pendingSession->task?->title ?? $pendingSession->plan->title }}</span><span class="mt-1 block text-xs text-slate-400">{{ max(1, (int) ceil(($pendingSession->actual_seconds ?? 0) / 60)) }}分・{{ $pendingSession->ended_at?->format('m/d H:i') }}</span></span>
                                <span class="text-sm font-semibold text-amber-200">反映 →</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </details>
        @endif

        <nav class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900/75 p-1" aria-label="ダッシュボード表示">
            <div class="flex min-w-max gap-1" role="tablist">
                <button type="button" class="dashboard-tab nav-link nav-link-active" data-dashboard-tab="overall" role="tab" aria-selected="true">全体</button>
                @foreach ($dashboard['plan_tabs'] as $item)
                    <button type="button" class="dashboard-tab nav-link plan-identity-shell" data-plan-accent="{{ $item['plan']->accentKey() }}" data-dashboard-tab="plan-{{ $item['plan']->id }}" data-plan-id="{{ $item['plan']->id }}" role="tab" aria-selected="false"><span aria-hidden="true">{{ $item['plan']->displayIcon() }}</span> {{ $item['plan']->title }}</button>
                @endforeach
            </div>
        </nav>

        <section data-dashboard-panel="overall" class="space-y-6">
            @if ($dashboard['plan_tabs']->isEmpty())
                <section class="empty-state page-card p-8 text-center">
                    <div class="text-4xl" aria-hidden="true">🧭</div>
                    <h2 class="mt-3 text-xl font-black text-slate-100">まず計画をひとつ作りましょう</h2>
                    <p class="mt-2 text-sm text-slate-400">ざっくり決めるだけでも大丈夫です。使いながら少しずつ整えていけます。</p>
                    <a href="{{ route('plans.create') }}" class="btn-primary mt-5">計画を作る</a>
                </section>
            @else
                <section class="page-card p-5">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-black text-slate-100">進行中の計画</h2>
                        </div>
                        <p class="text-sm text-slate-400">全体の残り 約{{ round($dashboard['remaining_minutes'] / 60, 1) }}時間</p>
                    </div>
                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($dashboard['plan_tabs'] as $item)
                            <button type="button" class="home-plan-card plan-identity-shell text-left" data-plan-accent="{{ $item['plan']->accentKey() }}" data-open-dashboard-tab="plan-{{ $item['plan']->id }}">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="plan-identity-icon" aria-hidden="true">{{ $item['plan']->displayIcon() }}</span>
                                    <span class="badge badge-slate">{{ $item['progress']['status'] }}</span>
                                </div>
                                <h3 class="mt-3 line-clamp-2 font-black text-slate-100">{{ $item['plan']->title }}</h3>
                                <div class="mt-3 flex items-end justify-between gap-3">
                                    <span class="text-2xl font-black text-slate-50">{{ $item['progress']['weighted_progress_percent'] }}%</span>
                                    <span class="text-xs text-slate-400">期限 {{ $item['plan']->deadline->format('m/d') }}</span>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </section>

                @if ($calendarWeek)
                    <section class="page-card p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-xl font-black text-slate-100">今週の見通し</h2>
                            </div>
                            <a href="{{ route('calendar.index') }}" class="btn-secondary px-3 py-2 text-xs">カレンダーを見る</a>
                        </div>
                        <div class="home-week-strip mt-4">
                            @foreach ($calendarWeek['days'] as $day)
                                <a href="{{ route('calendar.index', ['selected' => $day['date']->format('Y-m-d'), 'date' => $day['date']->format('Y-m-d')]) }}" class="home-week-day {{ $day['is_today'] ? 'is-today' : '' }}">
                                    <span>{{ $day['date']->isoFormat('ddd') }}</span>
                                    <strong>{{ $day['date']->day }}</strong>
                                    <small>{{ $day['actual_minutes'] }}/{{ $day['available_minutes'] }}分</small>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($recommendation)
                    <section class="today-compact-card plan-identity-shell" data-plan-accent="{{ $recommendation->plan->accentKey() }}">
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-sky-300">今日のおすすめ</p>
                            <p class="mt-1 plan-identity-chip text-xs"><span aria-hidden="true">{{ $recommendation->plan->displayIcon() }}</span>{{ $recommendation->plan->title }}</p>
                            <h2 class="mt-1 truncate font-black text-slate-100">{{ $recommendation->task->title }}</h2>
                            <p class="mt-1 text-xs text-slate-400">約{{ $recommendation->recommendedMinutes }}分</p>
                        </div>
                        <a href="{{ route('navigation.index') }}" class="btn-secondary shrink-0">今日へ</a>
                    </section>
                @endif

                <section class="page-card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-black text-slate-100">最近の動き</h2>
                        <a href="{{ route('timeline.index') }}" class="text-xs font-bold text-sky-300">すべて見る →</a>
                    </div>
                    <div class="mt-4 space-y-3">
                        @forelse ($dashboard['recent_activity'] ?? [] as $activity)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="min-w-0 truncate text-slate-300"><span aria-hidden="true">{{ $activity['plan']->displayIcon() }}</span> {{ $activity['log']->task?->title ?? $activity['log']->task_title_snapshot ?? $activity['plan']->title }}</span>
                                <span class="shrink-0 text-xs text-slate-500">{{ $activity['log']->worked_on?->format('m/d') }}・{{ $activity['log']->actual_minutes }}分</span>
                            </div>
                        @empty
                            <p class="text-sm text-slate-400">まだ作業記録はありません。</p>
                        @endforelse
                    </div>
                </section>

                <details class="page-card p-5">
                    <summary class="cursor-pointer font-bold text-slate-200">もう少し見る</summary>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="metric-card"><p class="text-xs text-slate-500">今日の実績</p><p class="mt-1 font-black text-slate-100">{{ $dashboard['today_minutes'] }}分</p></div>
                        <div class="metric-card"><p class="text-xs text-slate-500">今日の目安残り</p><p class="mt-1 font-black text-slate-100">{{ $todayRemaining }}分</p></div>
                        <div class="metric-card"><p class="text-xs text-slate-500">連続</p><p class="mt-1 font-black text-slate-100">{{ $dashboard['streak_days'] }}日</p></div>
                        <div class="metric-card"><p class="text-xs text-slate-500">作業リズム</p><p class="mt-1 font-black text-slate-100">{{ $dashboard['analysis_ready'] ? $state->state->label() : '学習中' }}</p></div>
                    </div>
                </details>

                <div class="grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('calendar.index') }}" class="secondary-surface-card"><span aria-hidden="true">📅</span><strong>カレンダー</strong><small>時間の見通し</small></a>
                    @auth
                        <a href="{{ route('auth.account') }}" class="secondary-surface-card"><span aria-hidden="true">⚙️</span><strong>アカウント</strong><small>データ保護・設定</small></a>
                    @else
                        <a href="{{ route('auth.register.form') }}" class="secondary-surface-card"><span aria-hidden="true">🔐</span><strong>データを保護</strong><small>端末変更に備える</small></a>
                    @endauth
                </div>
            @endif
        </section>

        @foreach ($dashboard['plan_tabs'] as $item)
            @php
                $planRecommendation = $item['recommendation'];
                $previousSession = $item['previous_session'];
                $roadmapNodes = collect($item['roadmap']['nodes'] ?? []);
                $nextMilestone = $roadmapNodes->first(fn ($node) => ! in_array($node['status'] ?? null, ['done', 'cancelled'], true) && ! ($node['is_current'] ?? false));
            @endphp
            <section data-dashboard-panel="plan-{{ $item['plan']->id }}" class="hidden space-y-6">
                <section class="page-card p-5 plan-identity-shell" data-plan-accent="{{ $item['plan']->accentKey() }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="plan-identity-icon" aria-hidden="true">{{ $item['plan']->displayIcon() }}</span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-400">{{ $item['progress']['status'] }}・進捗 {{ $item['progress']['weighted_progress_percent'] }}%</p>
                                <h2 class="mt-1 text-2xl font-black text-slate-100">{{ $item['plan']->title }}</h2>
                                <p class="mt-2 text-sm text-slate-400">期限 {{ $item['plan']->deadline->format('Y/m/d') }}・残り約{{ round($item['progress']['remaining_minutes'] / 60, 1) }}時間</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('plans.edit', $item['plan']) }}#plan-design" class="btn-secondary px-3 py-2 text-xs">🎨 デザイン</a>
                            <a href="{{ route('plans.show', $item['plan']) }}" class="btn-secondary px-3 py-2 text-xs">詳細</a>
                        </div>
                    </div>
                </section>

                @if ($previousSession?->task)
                    <section class="continuity-card plan-identity-shell" data-plan-accent="{{ $item['plan']->accentKey() }}" data-task-view data-task-id="{{ $previousSession->task->id }}" data-plan-id="{{ $item['plan']->id }}">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-sky-300">前回の続き</p>
                            <h3 class="mt-1 text-lg font-black text-slate-100">{{ $previousSession->task->title }}</h3>
                            <p class="mt-2 text-sm text-slate-400">{{ $previousSession->ended_at?->diffForHumans() }}・実作業 {{ max(1, (int) ceil(($previousSession->actual_seconds ?? 0) / 60)) }}分</p>
                            @if ($previousSession->task->next_action_note)
                                <p class="mt-2 text-sm text-sky-300">次回ここから：{{ $previousSession->task->next_action_note }}</p>
                            @endif
                        </div>
                        @if (! in_array($previousSession->task->status, ['done', 'cancelled'], true))
                            <form method="POST" action="{{ route('work_sessions.start') }}" data-work-start-form>
                                @csrf
                                <input type="hidden" name="task_id" value="{{ $previousSession->task->id }}">
                                <input type="hidden" name="source" value="dashboard-plan-resume">
                                <button class="btn-primary">続きをやる</button>
                            </form>
                        @endif
                    </section>
                @endif

                <section class="page-card p-4 sm:p-6 plan-identity-shell" data-plan-accent="{{ $item['plan']->accentKey() }}">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-sky-300">ロードマップ</p>
                            <h2 class="mt-1 text-xl font-black text-slate-100">今ここから、この先へ</h2>
                        </div>
                        <a href="{{ route('roadmap.index', ['plan_id' => $item['plan']->id]) }}" class="text-xs font-bold text-sky-300">大きく見る →</a>
                    </div>
                    @include('plans.partials.roadmap', [
                        'roadmap' => $item['roadmap'],
                        'roadmapPlan' => $item['plan'],
                        'roadmapCanEdit' => true,
                        'roadmapMode' => 'dashboard',
                        'roadmapRecommendedMinutes' => $planRecommendation?->recommendedMinutes,
                        'roadmapRecommendationReasons' => $planRecommendation?->reasons ?? [],
                    ])
                </section>

                <section class="page-card p-5">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-sky-300">次の目標</p>
                    @if ($nextMilestone)
                        <h2 class="mt-1 text-lg font-black text-slate-100">{{ $nextMilestone['title'] }}</h2>
                        <p class="mt-2 text-sm text-slate-400">あと {{ $nextMilestone['remaining_minutes'] }}分ほどです。</p>
                    @else
                        <h2 class="mt-1 text-lg font-black text-slate-100">ゴールが見えてきました</h2>
                        <p class="mt-2 text-sm text-slate-400">残りを確認して、ゴールまで進めましょう。</p>
                    @endif
                </section>

                @if ($planRecommendation)
                    <section class="today-compact-card plan-identity-shell" data-plan-accent="{{ $item['plan']->accentKey() }}">
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase tracking-[0.16em] text-sky-300">今日ここから</p>
                            <h2 class="mt-1 truncate font-black text-slate-100">{{ $planRecommendation->task->title }}</h2>
                            <p class="mt-1 text-xs text-slate-400">約{{ $planRecommendation->recommendedMinutes }}分</p>
                        </div>
                        <a href="{{ route('navigation.index', ['plan_id' => $item['plan']->id]) }}" class="btn-secondary shrink-0">今日へ</a>
                    </section>
                @endif

                <section class="page-card p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-black text-slate-100">最近の活動</h2>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('plans.review_assistant.show', $item['plan']) }}" class="btn-primary px-3 py-2 text-xs">計画を更新</a>
                            <a href="{{ route('timeline.index') }}" class="btn-secondary px-3 py-2 text-xs">タイムライン</a>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2 text-sm text-slate-400">
                        @forelse ($item['recent_logs'] as $log)
                            <p>{{ $log->worked_on?->format('m/d') }}・{{ $log->task?->title ?? $log->task_title_snapshot ?? '計画全体' }}・{{ $log->actual_minutes }}分</p>
                        @empty
                            <p>まだ記録はありません。</p>
                        @endforelse
                    </div>
                </section>
            </section>
        @endforeach

        @if (app()->isLocal() || config('app.debug'))
            <div class="text-right"><a href="{{ route('dashboard.tools') }}" class="text-sm text-slate-500 hover:text-slate-300">開発ツール</a></div>
        @endif
    </div>
@endsection

@section('offline_snapshot')
@php
    $offlineCurrent = $recommendation ? [
        'task_id' => $recommendation->task->id,
        'title' => $recommendation->task->title,
        'status' => $recommendation->task->status,
        'status_label' => $recommendation->task->status === 'doing' ? '進行中' : '未着手',
        'progress_percent' => $recommendation->task->progress_percent,
        'remaining_minutes' => $recommendation->task->remaining_minutes,
        'next_action_note' => $recommendation->task->next_action_note,
        'is_current' => true,
    ] : null;
    $offlineSnapshot = [
        'type' => 'dashboard',
        'captured_at' => now()->toIso8601String(),
        'csrf_token' => csrf_token(),
        'plan' => $recommendation ? ['id' => $recommendation->plan->id, 'title' => $recommendation->plan->title] : null,
        'current' => $offlineCurrent,
        'roadmap' => $offlineCurrent ? [$offlineCurrent] : [],
        'continuity' => $dashboard['continuity'] ?? null,
    ];
@endphp
<script type="application/json" id="pacekeeper-offline-snapshot">{!! json_encode($offlineSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection
