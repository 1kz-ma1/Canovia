<section class="page-card pk-v18-section-card p-4 sm:p-5" data-surface-id="task_list">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-sky-300">TASKS</p>
            <h2 class="mt-1 text-base font-black text-slate-100 sm:text-lg">このあと</h2>
        </div>
        <a href="{{ route('plans.show', $item['plan']) }}" class="text-xs font-bold text-sky-300">全Taskを見る →</a>
    </div>

    <div class="mt-4 space-y-2">
        @forelse ($hubTasks as $hubTask)
            <div class="rounded-xl border {{ $hubCurrentTask && (int) $hubTask->id === (int) $hubCurrentTask->id ? 'border-cyan-300/20 bg-cyan-300/[0.035]' : 'border-white/8 bg-white/[0.025]' }} px-3 py-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-100">
                            <span class="mr-1 text-slate-600">○</span>
                            {{ $hubTask->title }}
                        </p>
                        <p class="mt-1 text-[11px] text-slate-500">進捗 {{ (int) $hubTask->progress_percent }}% · 残り目安 {{ (int) ($hubTask->remaining_minutes ?? 0) }}分</p>
                    </div>
                    <span class="shrink-0 text-xs font-bold text-slate-300">{{ (int) $hubTask->progress_percent }}%</span>
                </div>
            </div>
        @empty
            <p class="text-sm text-slate-500">未完了Taskはありません。</p>
        @endforelse
    </div>

    @if ($activeTaskCount > $hubTasks->count())
        <p class="mt-3 text-center text-[11px] text-slate-500">ほか {{ $activeTaskCount - $hubTasks->count() }}件はPlan詳細で確認できます。</p>
    @endif
</section>
