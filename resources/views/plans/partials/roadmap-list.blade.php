@php
    $roadmapMode = $roadmapMode ?? 'plan';
    $roadmapCanEdit = $roadmapCanEdit ?? false;
    $roadmapCanManage = $roadmapCanManage ?? false;
    $roadmapPlan = $roadmapPlan ?? $plan ?? null;
    $roadmapPreview = $roadmapMode === 'preview';
    $roadmapRecommendedMinutes = $roadmapRecommendedMinutes ?? null;
    $roadmapRecommendationReasons = $roadmapRecommendationReasons ?? [];
    $changeLabels = [
        'created' => '追加',
        'updated' => '具体化',
        'cancelled' => '中止',
        'kept' => '維持',
        'reordered' => '順序変更',
    ];

    $roadmapNodes = collect($roadmap['nodes'] ?? []);
    $collapsedCompleted = collect();
    if (! $roadmapPreview) {
        $pastCompleted = $roadmapNodes->filter(fn ($node) => (bool) ($node['is_past_completed'] ?? false));
        if ($pastCompleted->count() >= 3) {
            $collapsedCompleted = $pastCompleted->values();
            $collapsedKeys = $collapsedCompleted->pluck('key')->all();
            $roadmapNodes = $roadmapNodes->reject(fn ($node) => in_array($node['key'] ?? null, $collapsedKeys, true))->values();
        }
    }
@endphp

@if ($collapsedCompleted->isNotEmpty())
    <details class="roadmap-completed-group mb-3">
        <summary>
            <span>✓ 完了したタスク {{ $collapsedCompleted->count() }}件</span>
            <span class="roadmap-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="roadmap-completed-list">
            @foreach ($collapsedCompleted as $completedNode)
                <div class="roadmap-completed-item">
                    <span class="text-emerald-300">✓</span>
                    <span class="min-w-0 truncate">{{ $completedNode['title'] }}</span>
                </div>
            @endforeach
        </div>
    </details>
@endif

<div class="living-roadmap" data-living-roadmap>
    @forelse ($roadmapNodes as $index => $node)
        @php
            $isCurrent = (bool) ($node['is_current'] ?? false);
            $isDone = ($node['status'] ?? null) === 'done';
            $isCancelled = ($node['status'] ?? null) === 'cancelled';
            $isChanged = ($node['change_type'] ?? 'unchanged') !== 'unchanged';
        @endphp
        <div class="roadmap-row {{ $isCurrent ? 'is-current' : '' }} {{ $isDone ? 'is-done' : '' }} {{ $isCancelled ? 'is-cancelled' : '' }} {{ $isChanged ? 'is-changed' : '' }}">
            <div class="roadmap-rail" aria-hidden="true">
                <span class="roadmap-dot">
                    @if ($isDone)
                        ✓
                    @elseif ($isCancelled)
                        ×
                    @elseif ($isCurrent)
                        ◎
                    @else
                        ○
                    @endif
                </span>
                @if (! $loop->last)<span class="roadmap-line"></span>@endif
            </div>

            @if ($isCurrent)
                <article class="roadmap-current-card">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="roadmap-now-badge">今ここ</span>
                        @if ($node['is_last_worked'] ?? false)<span class="badge badge-slate">前回の文脈</span>@endif
                        @if ($node['is_lineage_child'] ?? false)<span class="badge badge-green">実績から具体化</span>@endif
                        @if ($node['is_lineage_source'] ?? false)<span class="badge badge-green">分解後の残り</span>@endif
                        @if ($roadmapPreview && $isChanged)<span class="roadmap-change-badge">{{ $changeLabels[$node['change_type']] ?? '変更' }}</span>@endif
                    </div>
                    <h3 class="mt-3 text-xl font-bold text-slate-50">{{ $node['title'] }}</h3>
                    @if (! empty($node['description']))
                        <div class="mt-2">
                            @include('layouts.partials.collapsible-text', [
                                'text' => $node['description'],
                                'toneClass' => 'text-sm leading-7 text-slate-300',
                                'emptyText' => '',
                            ])
                        </div>
                    @endif

                    @if (! empty($node['lineage_children']))
                        <div class="mt-4 rounded-xl border border-emerald-400/20 bg-emerald-500/10 px-3 py-3 text-sm leading-6 text-emerald-100">
                            実績をもとに分解済み：{{ collect($node['lineage_children'])->pluck('title')->implode(' / ') }}
                        </div>
                    @endif

                    @if (! empty($node['next_action_note']))
                        <div class="roadmap-resume-note mt-4">
                            <p class="text-xs font-bold uppercase tracking-[0.14em] text-sky-300">次回ここから</p>
                            <p class="mt-1 text-sm leading-6 text-slate-100">{{ $node['next_action_note'] }}</p>
                        </div>
                    @endif

                    @if (! empty($node['resources']))
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($node['resources'] as $resource)
                                <a href="{{ $resource['url'] }}" target="_blank" rel="noopener noreferrer" class="rounded-full border border-cyan-300/20 bg-cyan-300/[0.08] px-3 py-1 text-xs font-semibold text-cyan-100">📎 {{ $resource['title'] }}</a>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        @if (! $roadmapPreview && $roadmapRecommendedMinutes)<span class="badge badge-green">今回 {{ $roadmapRecommendedMinutes }}分</span>@endif
                        <span class="badge badge-slate">残り {{ $node['remaining_minutes'] }}分</span>
                        <span class="badge badge-slate">進捗 {{ $node['progress_percent'] }}%</span>
                        @if (! empty($node['prerequisite_title']))<span class="badge badge-slate">前提 {{ $node['prerequisite_title'] }}</span>@endif
                    </div>

                    @if (! $roadmapPreview && ! empty($roadmapRecommendationReasons))
                        <ul class="mt-4 space-y-1 text-xs leading-5 text-slate-400">
                            @foreach (array_slice($roadmapRecommendationReasons, 0, 2) as $reason)
                                <li>・{{ $reason }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($roadmapCanManage && ! $roadmapPreview && $roadmapPlan)
                        <a href="{{ route('plans.review_assistant.show', $roadmapPlan) }}" class="btn-secondary mt-5 w-full sm:w-auto">計画を更新</a>
                    @endif

                    @if ($roadmapCanEdit && ! $roadmapPreview && ($node['startable'] ?? false) && ! empty($node['task_id']))
                        <form method="POST" action="{{ route('work_sessions.start') }}" class="mt-5" data-work-start-form>
                            @csrf
                            <input type="hidden" name="task_id" value="{{ $node['task_id'] }}">
                            <input type="hidden" name="source" value="roadmap">
                            @if ($roadmapRecommendedMinutes)<input type="hidden" name="intended_minutes" value="{{ $roadmapRecommendedMinutes }}">@endif
                            <button type="submit" class="btn-primary w-full sm:w-auto">{{ ($node['is_last_worked'] ?? false) ? '続きから開始' : 'このタスクを開始' }}</button>
                        </form>
                    @endif
                </article>
            @else
                <details class="roadmap-node-card group" {{ $roadmapPreview && $isChanged ? 'open' : '' }}>
                    <summary class="roadmap-node-summary">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate font-semibold {{ $isCancelled ? 'line-through text-slate-500' : 'text-slate-100' }}">{{ $node['title'] }}</span>
                                @if ($node['is_lineage_child'] ?? false)<span class="badge badge-green">分解</span>@endif
                                @if ($node['is_lineage_source'] ?? false)<span class="badge badge-green">分解元</span>@endif
                                @if ($roadmapPreview && $isChanged)<span class="roadmap-change-badge">{{ $changeLabels[$node['change_type']] ?? '変更' }}</span>@endif
                            </div>
                            @if ($node['is_far_future'] ?? false)
                                <p class="mt-1 text-xs text-slate-500">あとで取り組むタスク · タップで詳細</p>
                            @else
                                <p class="mt-1 text-xs text-slate-500">{{ $node['status_label'] }} · {{ $node['progress_percent'] }}% · 残り{{ $node['remaining_minutes'] }}分</p>
                            @endif
                        </div>
                        <span class="roadmap-chevron" aria-hidden="true">⌄</span>
                    </summary>
                    <div class="roadmap-node-detail">
                        @if (! empty($node['description']))
                            @include('layouts.partials.collapsible-text', [
                                'text' => $node['description'],
                                'toneClass' => 'text-sm leading-6 text-slate-300',
                                'emptyText' => '',
                            ])
                        @endif
                        @if (! empty($node['next_action_note']))
                            <p class="mt-3 rounded-xl bg-sky-500/10 px-3 py-2 text-sm text-sky-100">次回：{{ $node['next_action_note'] }}</p>
                        @endif
                        @if (! empty($node['source_task_titles']))
                            <p class="mt-3 text-xs text-emerald-300">これまでの流れ：「{{ implode('」「', $node['source_task_titles']) }}」から具体化</p>
                        @endif
                        @if (! empty($node['lineage_children']))
                            <p class="mt-2 text-xs text-emerald-300">ここから分かれたタスク：{{ collect($node['lineage_children'])->pluck('title')->implode(' / ') }}</p>
                        @endif
                        @if (! empty($node['resources']))
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($node['resources'] as $resource)
                                    <a href="{{ $resource['url'] }}" target="_blank" rel="noopener noreferrer" class="rounded-full border border-cyan-300/20 bg-cyan-300/[0.08] px-3 py-1 text-xs font-semibold text-cyan-100">📎 {{ $resource['title'] }}</a>
                                @endforeach
                            </div>
                        @endif
                        @if ($roadmapCanEdit && ! $roadmapPreview && ($node['startable'] ?? false) && ! empty($node['task_id']))
                            <form method="POST" action="{{ route('work_sessions.start') }}" class="mt-4" data-work-start-form>
                                @csrf
                                <input type="hidden" name="task_id" value="{{ $node['task_id'] }}">
                                <input type="hidden" name="source" value="roadmap">
                                <button type="submit" class="btn-secondary w-full sm:w-auto">このタスクを開始</button>
                            </form>
                        @endif
                    </div>
                </details>
            @endif
        </div>
    @empty
        <div class="empty-state">
            <p class="font-bold text-slate-100">ロードマップに表示するタスクがまだありません。</p>
            <p class="mt-2 text-sm leading-6 text-slate-400">最初から完璧に決めなくて大丈夫です。まず始めて、あとから整えていけます。</p>
            @if ($roadmapCanEdit && $roadmapPlan)
                <a href="{{ route('plans.ai_task_assistant.show', $roadmapPlan) }}" class="btn-primary mt-4">最初のロードマップを作る</a>
            @endif
        </div>
    @endforelse
</div>
