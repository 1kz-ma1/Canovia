@extends('layouts.app')

@section('title', 'ダッシュボード | Pace Keeper')

@section('content')
    <section class="mb-10 rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
        <p class="mb-3 text-sm font-semibold text-sky-600">Dashboard</p>
        <h1 class="mb-4 text-4xl font-bold tracking-tight text-slate-900">状況を把握する</h1>
        <p class="max-w-3xl leading-7 text-slate-600">
            取り組み中タスク、今日必要な作業時間、計画の進捗、最近のログをまとめて確認できます。
            登録や計画変更はチャットから始めると、必要な情報だけを順番に入力できます。
        </p>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="{{ route('chat.index') }}" class="btn-primary">チャットで操作する</a>
            <a href="{{ route('my_plans.index') }}" class="btn-secondary">すべての計画を見る</a>
        </div>
    </section>

    @if (session('success'))
        <div class="assistant-notice assistant-notice-success mb-6">
            {{ session('success') }}
        </div>
    @endif

    @if (session('status'))
        <div class="assistant-notice assistant-notice-success mb-6">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="assistant-notice assistant-notice-error mb-6">
            <p class="font-bold">AI JSONの入力内容を確認してください。</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="mb-10 rounded-2xl bg-slate-950 p-6 text-white shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-semibold text-sky-300">Universal AI import</p>
                <h2 class="mt-2 text-2xl font-bold">AI JSONをまとめて読み込む</h2>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300">
                    JSON内の対象計画と操作目的を検証し、タスク追加・進捗更新・計画再編をここから確認できます。
                    対象計画や操作目的が省略されている場合は、反映前にユーザーが選択できます。
                </p>
            </div>

            <details class="w-full lg:max-w-xl" @if ($errors->has('operations_json') || $dashboardJsonProposal || $dashboardJsonPending) open @endif>
                <summary class="btn-primary cursor-pointer list-none text-center">
                    AI JSONを入力
                </summary>

                <div class="mt-4 rounded-xl bg-white p-4 text-slate-900">
                    <form method="POST" action="{{ route('dashboard.ai_json.preview') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="dashboard_operations_json" class="mb-2 block text-sm font-semibold">
                                外部AIのJSON
                            </label>
                            <textarea
                                id="dashboard_operations_json"
                                name="operations_json"
                                rows="14"
                                class="form-control font-mono text-xs"
                                required
                                placeholder='{"schema_version":"2.0","flow":"plan_update","target_plan":{"id":5,"title":"Catalyst 完成","category":"ゲーム開発"},"summary":"計画全体を再編","operations":[{"type":"update_plan","description":"変更後の完全な概要","reason":"最新方針へ更新"},{"type":"keep_task","task_id":31,"reason":"成果を継続利用するため"}]}'
                            >{{ old('operations_json') }}</textarea>
                        </div>

                        <div class="assistant-notice assistant-notice-info">
                            <p class="font-semibold">共通JSON 2.0</p>
                            <p class="mt-1 text-sm leading-6">
                                <code>action</code>は <code>append_tasks</code>、<code>update_progress</code>、
                                <code>restructure_plan</code>のいずれかです。
                                <code>target_plan.id</code>を優先し、IDがない場合だけ計画名の完全一致で識別します。
                                対象情報がない旧JSONは、読み込み後に計画を選択できます。
                            </p>
                        </div>

                        <button type="submit" class="btn-primary w-full">
                            対象計画と操作目的を検証
                        </button>
                    </form>
                </div>
            </details>
        </div>

        @if ($dashboardJsonPending)
            @php
                $needsPlanSelection = $dashboardJsonPending['needs_plan_selection'] ?? ! $dashboardJsonPlan;
                $needsActionSelection = $dashboardJsonPending['needs_action_selection'] ?? empty($dashboardJsonPending['action']);
                $pendingAction = $dashboardJsonPending['action'] ?? null;
            @endphp

            <div class="mt-6 rounded-2xl bg-amber-50 p-5 text-slate-900 ring-1 ring-amber-200">
                <p class="text-sm font-semibold text-amber-700">不足している識別情報を確認してください</p>
                <h3 class="mt-1 text-xl font-bold">AI JSONの適用先と操作目的</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    JSONに不足している情報だけを補完し、その後に既存データへの影響を検証します。
                    JSON内に明示された対象情報と矛盾する選択は受け付けません。
                </p>
                <p class="mt-2 text-sm text-slate-500">{{ $dashboardJsonPending['summary'] ?? '' }}</p>

                <form method="POST" action="{{ route('dashboard.ai_json.resolve_legacy') }}" class="mt-5 space-y-5">
                    @csrf

                    @if ($needsPlanSelection)
                        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
                            <label for="dashboard_pending_plan_id" class="block font-bold">対象計画</label>
                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                このJSONには計画ID・計画名がないため、適用先を選択してください。
                            </p>

                            @if ($dashboardJsonPlans->isEmpty())
                                <div class="assistant-notice assistant-notice-error mt-3">
                                    このブラウザから編集できる計画がありません。先に計画を作成してください。
                                </div>
                            @else
                                <select
                                    id="dashboard_pending_plan_id"
                                    name="plan_id"
                                    class="form-control mt-3"
                                    required
                                >
                                    <option value="">計画を選択</option>
                                    @foreach ($dashboardJsonPlans as $candidatePlan)
                                        <option value="{{ $candidatePlan->id }}" @selected((int) old('plan_id') === $candidatePlan->id)>
                                            #{{ $candidatePlan->id }} {{ $candidatePlan->title }}
                                            （{{ $candidatePlan->category ?: 'カテゴリ未設定' }}）
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                    @elseif ($dashboardJsonPlan)
                        <input type="hidden" name="plan_id" value="{{ $dashboardJsonPlan->id }}">
                        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
                            <p class="text-sm font-semibold text-slate-500">識別済みの対象計画</p>
                            <p class="mt-1 font-bold">#{{ $dashboardJsonPlan->id }} {{ $dashboardJsonPlan->title }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $dashboardJsonPlan->category ?: 'カテゴリ未設定' }}</p>
                        </div>
                    @endif

                    @if ($needsActionSelection)
                        <fieldset>
                            <legend class="font-bold">操作目的</legend>
                            <p class="mt-1 text-sm leading-6 text-slate-600">
                                JSONの内容に合う目的を選択してください。選択後も、構造不足や未処理タスクがあれば反映を止めます。
                            </p>

                            <div class="mt-3 grid gap-3 lg:grid-cols-3">
                                <label class="cursor-pointer rounded-xl bg-white p-4 ring-1 ring-slate-200 has-[:checked]:ring-2 has-[:checked]:ring-sky-500">
                                    <input type="radio" name="action" value="append_tasks" class="mr-2" required @checked(old('action') === 'append_tasks')>
                                    <span class="font-bold">タスク追加</span>
                                    <span class="mt-2 block text-sm leading-6 text-slate-600">既存概要・既存タスクを維持し、新しいタスクだけ追加します。</span>
                                </label>

                                <label class="cursor-pointer rounded-xl bg-white p-4 ring-1 ring-slate-200 has-[:checked]:ring-2 has-[:checked]:ring-sky-500">
                                    <input type="radio" name="action" value="update_progress" class="mr-2" required @checked(old('action') === 'update_progress')>
                                    <span class="font-bold">進捗更新</span>
                                    <span class="mt-2 block text-sm leading-6 text-slate-600">作業ログ、進捗率、状態、想定時間などを更新します。</span>
                                </label>

                                <label class="cursor-pointer rounded-xl bg-white p-4 ring-1 ring-slate-200 has-[:checked]:ring-2 has-[:checked]:ring-sky-500">
                                    <input type="radio" name="action" value="restructure_plan" class="mr-2" required @checked(old('action') === 'restructure_plan')>
                                    <span class="font-bold">計画再編</span>
                                    <span class="mt-2 block text-sm leading-6 text-slate-600">概要と既存タスクの扱いを含め、計画全体を見直します。</span>
                                </label>
                            </div>
                        </fieldset>
                    @else
                        <input type="hidden" name="action" value="{{ $pendingAction }}">
                        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
                            <p class="text-sm font-semibold text-slate-500">JSONから識別した操作目的</p>
                            <p class="mt-1 font-bold">{{ $dashboardJsonPending['action_label'] ?? $pendingAction }}</p>
                        </div>
                    @endif

                    @if (! $dashboardJsonPlans->isEmpty() || ! $needsPlanSelection)
                        <button type="submit" class="btn-primary w-full md:w-auto">
                            選択内容で安全性を検証
                        </button>
                    @endif
                </form>

                <form method="POST" action="{{ route('dashboard.ai_json.reset') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-slate-600 hover:text-slate-900">読み込みを中止</button>
                </form>
            </div>
        @endif

        @if ($dashboardJsonProposal && $dashboardJsonPlan)
            @php
                $dashboardOperationLabels = [
                    'update_plan' => '計画更新',
                    'create_work_log' => '作業ログ追加',
                    'create_task' => 'タスク追加',
                    'update_task' => 'タスク更新',
                    'keep_task' => 'タスク維持',
                    'cancel_task' => 'タスク中止',
                ];
                $dashboardAnalysis = $dashboardJsonProposal['analysis'] ?? [];
                $dashboardCounts = $dashboardAnalysis['operation_counts'] ?? [];
                $dashboardCanApply = $dashboardJsonProposal['can_apply'] ?? true;
                $dashboardAtomic = $dashboardAnalysis['atomic_apply'] ?? false;
            @endphp

            <div class="mt-6 rounded-2xl bg-white p-5 text-slate-900">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-sky-600">検出した操作</p>
                        <h3 class="mt-1 text-xl font-bold">{{ $dashboardJsonProposal['action_label'] ?? 'AI JSON操作' }}</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            対象：#{{ $dashboardJsonPlan->id }} {{ $dashboardJsonPlan->title }}
                            ・カテゴリ：{{ $dashboardJsonPlan->category ?: '未設定' }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('dashboard.ai_json.reset') }}">
                        @csrf
                        <button type="submit" class="btn-secondary">読み込みを取り消す</button>
                    </form>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="metric-card"><p class="text-xs text-slate-500">計画更新</p><p class="mt-1 text-lg font-bold">{{ $dashboardCounts['update_plan'] ?? 0 }}件</p></div>
                    <div class="metric-card"><p class="text-xs text-slate-500">既存タスクの維持・更新・中止</p><p class="mt-1 text-lg font-bold">{{ ($dashboardCounts['keep_task'] ?? 0) + ($dashboardCounts['update_task'] ?? 0) + ($dashboardCounts['cancel_task'] ?? 0) }}件</p></div>
                    <div class="metric-card"><p class="text-xs text-slate-500">新規タスク</p><p class="mt-1 text-lg font-bold">{{ $dashboardCounts['create_task'] ?? 0 }}件</p></div>
                </div>

                @foreach (($dashboardAnalysis['normalization_notes'] ?? []) as $note)
                    <div class="assistant-notice assistant-notice-info mt-4">
                        <span class="font-bold">自動調整：</span>{{ $note }}
                    </div>
                @endforeach

                @foreach (($dashboardAnalysis['warnings'] ?? []) as $warning)
                    <div class="assistant-notice assistant-notice-warning mt-4">{{ $warning }}</div>
                @endforeach

                @if (! $dashboardCanApply)
                    <div class="assistant-notice assistant-notice-error mt-4">
                        <p class="font-bold">このJSONはまだ反映できません。</p>
                        <p class="mt-1 text-sm leading-6">実際に不足している項目だけを、対象ごとに表示しています。</p>

                        @if (! empty($dashboardAnalysis['blocking_issue_groups']))
                            <div class="mt-3 space-y-3">
                                @foreach ($dashboardAnalysis['blocking_issue_groups'] as $group)
                                    <div class="rounded-xl bg-white/70 p-3 ring-1 ring-red-200">
                                        <p class="font-bold text-red-900">{{ $group['title'] }}</p>
                                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-800">
                                            @foreach (($group['items'] ?? []) as $issue)
                                                <li>{{ $issue }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                                @foreach (($dashboardAnalysis['blocking_issues'] ?? []) as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <p class="mt-3 text-sm leading-6">
                            上記の項目だけを外部AIへ伝えて修正してください。すでに正しい<code>schema_version</code>や<code>action</code>を作り直す必要はありません。
                        </p>
                    </div>
                @elseif ($dashboardAtomic)
                    <div class="assistant-notice assistant-notice-info mt-4">
                        計画再編は概要・既存タスク・新規タスクの整合性を保つため、全操作を一括反映します。
                    </div>
                @else
                    <div class="assistant-notice assistant-notice-warning mt-4">
                        AIのJSONはまだ反映されていません。対象計画と変更内容を確認し、必要な項目だけ選択してください。
                    </div>
                @endif

                <p class="mt-4 leading-7 text-slate-600">{{ $dashboardJsonProposal['summary'] }}</p>

                <details class="mt-5 rounded-2xl border border-slate-300 bg-slate-50 p-4">
                    <summary class="cursor-pointer font-bold text-slate-800">変更前のRoadmap</summary>
                    <div class="mt-4 rounded-2xl bg-slate-950 p-4 text-slate-100 opacity-85">
                        @include('plans.partials.roadmap', [
                            'roadmap' => $dashboardJsonProposal['roadmap_before'] ?? ['nodes' => []],
                            'roadmapPlan' => $dashboardJsonPlan,
                            'roadmapCanEdit' => false,
                            'roadmapMode' => 'preview',
                        ])
                    </div>
                </details>

                <div class="mt-5 rounded-2xl border border-emerald-400/20 bg-slate-950 p-4 text-slate-100">
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-300">Roadmap Preview</p>
                    <h4 class="mt-1 text-lg font-bold">反映後の道筋</h4>
                    <p class="mt-1 text-sm text-slate-400">Taskの変更はRoadmap上で確認し、補助情報だけ詳細欄にまとめます。</p>
                    <div class="mt-4">
                        @include('plans.partials.roadmap', [
                            'roadmap' => $dashboardJsonProposal['roadmap_preview'] ?? ['nodes' => []],
                            'roadmapPlan' => $dashboardJsonPlan,
                            'roadmapCanEdit' => false,
                            'roadmapMode' => 'preview',
                        ])
                    </div>
                </div>

                <form method="POST" action="{{ route('plans.review_assistant.apply', $dashboardJsonPlan) }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="proposal_token" value="{{ $dashboardJsonProposal['token'] }}">
                    <input type="hidden" name="return_to" value="dashboard">

                    @if ($dashboardAtomic)
                        @foreach ($dashboardJsonProposal['operations'] as $index => $operation)
                            <input type="hidden" name="selected_operations[]" value="{{ $index }}">
                        @endforeach
                    @else
                        <details class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <summary class="cursor-pointer font-bold text-slate-900">変更の詳細・反映項目</summary>
                            <div class="mt-4 space-y-3">
                                @foreach ($dashboardJsonProposal['operations'] as $index => $operation)
                                    @php $isDashboardDanger = $operation['type'] === 'cancel_task'; @endphp
                                    <label class="assistant-operation-card {{ $isDashboardDanger ? 'assistant-operation-danger' : '' }}">
                                        <input type="checkbox" name="selected_operations[]" value="{{ $index }}" checked class="mt-1">
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span class="badge {{ $isDashboardDanger ? 'badge-red' : 'badge-slate' }}">{{ $dashboardOperationLabels[$operation['type']] ?? $operation['type'] }}</span>
                                                <span class="font-bold text-slate-900">{{ $operation['display'] }}</span>
                                            </span>
                                            @if (! empty($operation['reason']))<span class="mt-2 block text-sm leading-6 text-slate-600">理由：{{ $operation['reason'] }}</span>@endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    @if ($dashboardCanApply)
                        <button type="submit" class="btn-primary w-full md:w-auto">
                            {{ $dashboardAtomic ? 'このRoadmapへ一括更新' : '選択した変更を対象計画へ反映' }}
                        </button>
                    @endif
                </form>
            </div>
        @endif
    </section>

    <section class="mb-10">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="mb-1 text-sm font-semibold text-sky-600">Current focus</p>
                <h2 class="text-2xl font-bold text-slate-900">取り組み中タスク</h2>
                <p class="mt-1 text-sm text-slate-500">現在作業対象に設定しているタスクを表示しています。</p>
            </div>

            <a href="{{ route('my_plans.index') }}" class="action-link">
                計画一覧から選ぶ
            </a>
        </div>

        @if ($inProgressTasks->isEmpty())
            <div class="empty-state">
                <p class="font-bold text-slate-900">現在取り組み中のタスクはありません。</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    計画詳細画面からタスクを「取り組み中」に設定すると、ここに表示されます。
                </p>
                <a href="{{ route('my_plans.index') }}" class="btn-secondary mt-4">
                    取り組み中タスクを追加
                </a>
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($inProgressTasks as $item)
                    @php
                        $plan = $item['plan'];
                        $task = $item['task'];
                        $estimatedHours = round($task->estimated_minutes / 60, 1);
                    @endphp

                    <article class="page-card p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-sky-600">{{ $plan->title }}</p>
                                <h3 class="mt-1 text-lg font-bold text-slate-900">{{ $task->title }}</h3>
                            </div>

                            <span class="status-pill status-blue">取り組み中</span>
                        </div>

                        @if ($task->description)
                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                {{ \Illuminate\Support\Str::limit($task->description, 100) }}
                            </p>
                        @endif

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="metric-card">
                                <p class="text-xs text-slate-500">進捗率</p>
                                <p class="mt-1 text-lg font-bold text-slate-900">{{ $task->progress_percent }}%</p>
                            </div>

                            <div class="metric-card">
                                <p class="text-xs text-slate-500">想定時間</p>
                                <p class="mt-1 text-lg font-bold text-slate-900">
                                    {{ $estimatedHours >= 1 ? $estimatedHours . '時間' : $task->estimated_minutes . '分' }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 progress-track">
                            <div
                                class="progress-bar progress-blue"
                                style="width: {{ min(100, max(0, $task->progress_percent)) }}%;"
                            ></div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-3">
                            <form method="POST" action="{{ route('chat.start', 'work_log') }}">
                                @csrf
                                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                <input type="hidden" name="task_id" value="{{ $task->id }}">
                                <button type="submit" class="btn-primary px-3 py-2 text-sm">チャットで実績を記録</button>
                            </form>

                            <a href="{{ route('plans.show', $plan) }}" class="btn-secondary px-3 py-2 text-sm">
                                計画詳細を見る
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="mb-10">
        <div class="mb-4">
            <p class="mb-1 text-sm font-semibold text-emerald-600">Daily workload</p>
            <h2 class="text-2xl font-bold text-slate-900">今日の必要作業時間</h2>
            <p class="mt-1 text-sm text-slate-500">
                各計画の残り作業量と期限から、1日あたりに必要な作業時間を集計しています。
            </p>
        </div>

        @if ($totalDailyRequiredMinutes <= 0)
            <div class="empty-state">
                <p class="font-bold text-slate-900">今日必要な作業時間はまだありません。</p>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    計画とタスクを登録すると、1日に必要な作業時間がここに表示されます。
                </p>
                <a href="{{ route('chat.index') }}" class="btn-primary mt-4">チャットで計画を作成</a>
            </div>
        @else
            <div class="dashboard-summary mb-4">
                <p class="text-sm font-semibold text-slate-500">全計画の合計</p>
                <div class="mt-2 flex flex-wrap items-end gap-x-3 gap-y-1">
                    <p class="text-4xl font-bold text-slate-900">{{ $totalDailyRequiredMinutes }}分</p>
                    <p class="pb-1 text-sm text-slate-500">
                        1日あたり約{{ round($totalDailyRequiredMinutes / 60, 1) }}時間
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                @foreach ($dailyRequiredPlans as $item)
                    @php
                        $plan = $item['plan'];
                        $progress = $item['progress'];
                        $sharePercent = $item['share_percent'];
                    @endphp

                    <article class="info-card p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">{{ $plan->title }}</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $progress['remaining_days'] === null ? '期限未設定' : '残り' . $progress['remaining_days'] . '日' }}・進捗{{ $progress['weighted_progress_percent'] }}%
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-xl font-bold text-slate-900">
                                    {{ $progress['remaining_days'] === null ? '期限設定後に算出' : $progress['daily_required_minutes'] . '分 / 日' }}
                                </p>
                                <p class="mt-1 text-sm font-semibold text-emerald-600">
                                    全体の{{ $sharePercent }}%
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 allocation-track" aria-label="全体に占める割合 {{ $sharePercent }}%">
                            <div class="allocation-bar" style="width: {{ min(100, max(0, $sharePercent)) }}%;"></div>
                        </div>

                        <a href="{{ route('plans.show', $plan) }}" class="action-link mt-4">
                            計画詳細を見る
                        </a>
                    </article>
                @endforeach
            </div>

            <p class="mt-3 text-xs text-slate-500">
                期限切れ・完了済み・必要時間が0分の計画は合計から除外しています。
            </p>
        @endif
    </section>

    <section class="mb-10">
        <div class="mb-4 flex items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">現在進行中の計画</h2>
                <p class="mt-1 text-sm text-slate-500">このブラウザで作成した計画を表示しています。</p>
            </div>

            <a href="{{ route('my_plans.index') }}" class="text-sm font-medium text-slate-700 hover:text-slate-950">
                自分の計画一覧へ
            </a>
        </div>

        @if ($myPlans->isEmpty())
            <div class="empty-state">
                このブラウザで作成した計画はまだありません。
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
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

                    <article class="page-card p-5">
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <h3 class="text-lg font-bold text-slate-900">
                                {{ $plan->title }}
                            </h3>

                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">
                                {{ $plan->category ?? '未設定' }}
                            </span>
                        </div>

                        <p class="mb-3 text-sm text-slate-500">
                            {{ $plan->start_date }} 〜 {{ $plan->deadline?->format('Y-m-d') ?? '期限未設定' }}
                        </p>

                        <div class="mb-3">
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-slate-600">進捗率</span>
                                <span class="font-semibold text-slate-900">{{ $progress['weighted_progress_percent'] }}%</span>
                            </div>

                            <div class="progress-track">
                                <div
                                    class="progress-bar {{ $progressColorClass }}"
                                    style="width: {{ min(100, max(0, $progress['weighted_progress_percent'])) }}%;"
                                ></div>
                            </div>
                        </div>

                        <div class="mb-4 flex items-center justify-between text-sm">
                            <span class="text-slate-500">状態</span>
                            <span class="status-pill {{ $statusClass }}">{{ $status }}</span>
                        </div>

                        <a href="{{ route('plans.show', $plan) }}" class="action-link">
                            詳細を見る
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="mb-10">
        <h2 class="mb-4 text-2xl font-bold text-slate-900">最近の作業ログ</h2>

        @if ($recentWorkLogs->isEmpty())
            <div class="empty-state">
                まだ作業ログは登録されていません。
            </div>
        @else
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($recentWorkLogs as $workLog)
                    <article class="info-card p-5">
                        <p class="mb-2 text-sm font-semibold text-slate-500">
                            {{ $workLog->worked_on }}
                        </p>

                        <h3 class="mb-2 font-bold text-slate-900">
                            <a href="{{ route('plans.show', $workLog->plan) }}" class="hover:underline">
                                {{ $workLog->plan?->title ?? '不明な計画' }}
                            </a>
                        </h3>

                        <p class="text-sm text-slate-600">
                            タスク：{{ $workLog->task?->title ?? '計画全体の作業' }}
                        </p>

                        <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
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
                            <p class="mt-3 text-sm leading-6 text-slate-600">
                                {{ \Illuminate\Support\Str::limit($workLog->memo, 60) }}
                            </p>
                        @endif

                        <p class="mt-3 text-xs text-slate-400">
                            記録日時：{{ $workLog->created_at->format('Y-m-d H:i') }}
                        </p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="adaptive-entry-card">
        <div>
            <p class="text-sm font-semibold text-sky-600">Next action</p>
            <h2 class="mt-1 text-2xl font-bold text-slate-900">次の操作はチャットから</h2>
            <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-600">
                作業記録、タスク追加、計画作成、外部AIを使った計画見直しを一つの入口にまとめています。
            </p>
        </div>
        <a href="{{ route('chat.index') }}" class="btn-primary">チャットを開く</a>
    </section>
@endsection
