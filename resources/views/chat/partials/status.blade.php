<div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
    <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">現在の計画状況です</h2>
    <div class="mt-5 grid gap-4 md:grid-cols-2">
        @forelse ($planItems as $item)
            <article class="chat-inline-card"><div class="flex items-start justify-between gap-3"><div><p class="font-bold text-slate-100">{{ $item['plan']->title }}</p><p class="mt-1 text-sm text-slate-400">{{ $item['progress']['status'] }}</p></div><p class="text-2xl font-bold text-sky-400">{{ $item['progress']['weighted_progress_percent'] }}%</p></div><div class="mt-3 text-sm text-slate-400">{{ $item['progress']['remaining_days'] === null ? '期限未設定・ペース判定は保留中' : '残り ' . $item['progress']['remaining_days'] . '日・1日 ' . $item['progress']['daily_required_minutes'] . '分' }}</div><a href="{{ route('plans.show', $item['plan']) }}" class="action-link mt-3 inline-flex">詳細を見る</a></article>
        @empty
            <p class="text-slate-400">表示できる計画がありません。</p>
        @endforelse
    </div>
    @if ($inProgressTasks->isNotEmpty())<h3 class="mt-6 font-bold text-slate-100">進行中Task</h3><div class="mt-3 space-y-2">@foreach ($inProgressTasks as $item)<div class="chat-inline-card"><p class="text-xs text-sky-400">{{ $item['plan']->title }}</p><p class="mt-1 font-semibold text-slate-100">{{ $item['task']->title }}</p><p class="mt-1 text-sm text-slate-400">進捗 {{ $item['task']->progress_percent }}%</p></div>@endforeach</div>@endif
</div></div>
