<section class="page-card p-5" data-surface-id="recent_activity">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-black text-slate-100">最近の活動</h2>
        <div class="flex flex-wrap gap-2">
            @if ($planCanEdit)
                <a href="{{ route('plans.review_assistant.show', $item['plan']) }}" class="btn-secondary px-3 py-2 text-xs">計画を更新</a>
            @endif
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
