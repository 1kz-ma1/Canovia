@php
    $roadmapMode = $roadmapMode ?? 'plan';
    $roadmapCanEdit = $roadmapCanEdit ?? false;
    $roadmapPlan = $roadmapPlan ?? $plan ?? null;
    $roadmapPreview = $roadmapMode === 'preview';
    $roadmapRecommendedMinutes = $roadmapRecommendedMinutes ?? null;
    $roadmapNodes = collect($roadmap['nodes'] ?? [])->values();
    $nodeCount = $roadmapNodes->count();
    $gap = 126;
    $stageHeight = max(610, 190 + max(1, $nodeCount) * $gap);
    $xPattern = [24, 45, 57, 42, 55, 67, 50, 71];
    $points = [];
    foreach ($roadmapNodes as $index => $node) {
        $x = $xPattern[$index % count($xPattern)];
        $y = $stageHeight - 82 - ($index * $gap);
        $points[] = ['x' => $x, 'y' => $y];
    }
    $path = '';
    foreach ($points as $index => $point) {
        $x = $point['x'] * 10;
        $y = $point['y'];
        if ($index === 0) {
            $path = "M {$x} {$y}";
            continue;
        }
        $prev = $points[$index - 1];
        $prevX = $prev['x'] * 10;
        $prevY = $prev['y'];
        $midY = ($prevY + $y) / 2;
        $path .= " C {$prevX} {$midY}, {$x} {$midY}, {$x} {$y}";
    }
    $currentIndex = $roadmapNodes->search(fn ($node) => (bool) ($node['is_current'] ?? false));
    $currentPoint = $currentIndex !== false ? ($points[$currentIndex] ?? null) : null;
@endphp

<div class="pk-v19-orbit-stage" data-roadmap-map style="--orbit-height: {{ $stageHeight }}px; height: {{ $stageHeight }}px;">
    <div class="pk-v19-orbit-stars" aria-hidden="true"></div>
    <div class="pk-v19-orbit-horizon" aria-hidden="true"></div>

    @if ($path !== '')
        <svg class="pk-v19-orbit-path" viewBox="0 0 1000 {{ $stageHeight }}" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="pk-roadmap-route-{{ $roadmapPlan?->id ?? 'preview' }}" x1="0" y1="1" x2="1" y2="0">
                    <stop offset="0" stop-color="#54d9ff"/>
                    <stop offset="0.48" stop-color="#7188ff"/>
                    <stop offset="0.78" stop-color="#9c77ff"/>
                    <stop offset="1" stop-color="#ffc978"/>
                </linearGradient>
            </defs>
            <path d="{{ $path }}" stroke="url(#pk-roadmap-route-{{ $roadmapPlan?->id ?? 'preview' }})" vector-effect="non-scaling-stroke"/>
        </svg>
    @endif

    @if ($currentPoint && ! $roadmapPreview)
        <div class="pk-v19-next-guide" style="--guide-y: {{ max(100, $currentPoint['y'] - 18) }}px;">
            <p>次の一歩は<br>ここだよ！ ✦</p>
            <img src="/brand/mascot-guide.webp" alt="" aria-hidden="true">
        </div>
    @endif

    @forelse ($roadmapNodes as $index => $node)
        @php
            $point = $points[$index];
            $isCurrent = (bool) ($node['is_current'] ?? false);
            $isDone = ($node['status'] ?? null) === 'done';
            $isCancelled = ($node['status'] ?? null) === 'cancelled';
            $isGoal = $loop->last;
            $isLocked = ! $isDone && ! $isCurrent && ! ($node['startable'] ?? false) && ! $roadmapPreview;
            $labelSide = $point['x'] >= 57 ? 'left' : 'right';
            $phaseClass = $isDone ? 'is-done' : ($isCurrent ? 'is-current' : ($isLocked ? 'is-locked' : 'is-future'));
            $planetGlyph = $isDone ? '✓' : ($isCurrent ? '▤' : ($isLocked ? '⌕' : ($isGoal ? '⚑' : ($index + 1))));
        @endphp
        <details
            class="pk-v19-orbit-node {{ $phaseClass }} {{ $isGoal ? 'is-goal' : '' }} {{ $isCancelled ? 'is-cancelled' : '' }}"
            data-map-stop
            style="--node-x: {{ $point['x'] }}%; --node-y: {{ $point['y'] }}px;"
            @if ($roadmapPreview && (($node['change_type'] ?? 'unchanged') !== 'unchanged')) open @endif
        >
            <summary>
                <span class="pk-v19-planet" aria-hidden="true"><i>{{ $planetGlyph }}</i></span>
                <span class="pk-v19-node-label is-{{ $labelSide }}">
                    <strong>{{ $index + 1 }}. {{ $node['title'] }}</strong>
                    <small>
                        @if ($isDone)
                            完了したステップ
                        @elseif ($isCurrent)
                            {{ $node['next_action_note'] ?: ($node['description'] ?: 'ここから進めよう') }}
                        @elseif ($isGoal)
                            ゴールへ向かうステップ
                        @elseif ($isLocked)
                            前のステップを終えると進めます
                        @else
                            {{ $node['description'] ?: '次につながるステップ' }}
                        @endif
                    </small>
                    @if ($isCurrent)<em>次の一歩 ›</em>@endif
                </span>
                @if ($isGoal)<span class="pk-v19-goal-flag" aria-hidden="true">⚑</span>@endif
            </summary>

            <div class="pk-v19-node-detail is-{{ $labelSide }}">
                <div class="pk-v19-node-detail-meta">
                    @if ($isCurrent)<span>今ここ</span>@endif
                    <span>{{ $node['status_label'] }}</span>
                    @if ($node['is_last_worked'] ?? false)<span>前回の続き</span>@endif
                </div>
                <h3>{{ $node['title'] }}</h3>
                @if (! empty($node['next_action_note']))
                    <p>{{ $node['next_action_note'] }}</p>
                @elseif (! empty($node['description']))
                    <p>{{ $node['description'] }}</p>
                @endif
                <div class="pk-v19-node-detail-stats">
                    @if ($isCurrent && $roadmapRecommendedMinutes)<span>今回 {{ $roadmapRecommendedMinutes }}分</span>@endif
                    <span>残り {{ $node['remaining_minutes'] }}分</span>
                    <span>{{ $node['progress_percent'] }}%</span>
                </div>
                @if ($roadmapCanEdit && ! $roadmapPreview && ($node['startable'] ?? false) && ! empty($node['task_id']))
                    <form method="POST" action="{{ route('work_sessions.start') }}" data-work-start-form>
                        @csrf
                        <input type="hidden" name="task_id" value="{{ $node['task_id'] }}">
                        <input type="hidden" name="source" value="roadmap">
                        @if ($isCurrent && $roadmapRecommendedMinutes)<input type="hidden" name="intended_minutes" value="{{ $roadmapRecommendedMinutes }}">@endif
                        <button type="submit" class="{{ $isCurrent ? 'btn-primary' : 'btn-secondary' }} w-full">{{ ($node['is_last_worked'] ?? false) ? '続きから開始' : 'このタスクを開始' }}</button>
                    </form>
                @endif
            </div>
        </details>
    @empty
        <div class="empty-state relative z-10">
            <p class="font-bold text-slate-100">まだ道がありません。</p>
            <p class="mt-2 text-sm leading-6 text-slate-400">タスクを登録すると、未来へ続く軌道がここに描かれます。</p>
            @if ($roadmapCanEdit && $roadmapPlan)
                <a href="{{ route('plans.ai_task_assistant.show', $roadmapPlan) }}" class="btn-primary mt-4">最初の道を作る</a>
            @endif
        </div>
    @endforelse
</div>
