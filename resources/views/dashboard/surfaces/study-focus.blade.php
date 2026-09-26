@php
    $score = $surface->payload['score'] ?? null;
    $weaknesses = collect($surface->payload['weaknesses'] ?? []);
    $studyNextAction = $surface->payload['next_action'] ?? null;
@endphp
<section class="page-card pk-v18-section-card p-4 sm:p-5" data-surface-id="study_focus">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-sky-300">STUDY FOCUS</p>
            <h2 class="mt-1 text-base font-black text-slate-100 sm:text-lg">学習で今見るもの</h2>
        </div>
        @if ($score !== null)
            <span class="badge badge-slate">直近 {{ (int) $score }}%</span>
        @endif
    </div>

    @if ($weaknesses->isNotEmpty())
        <div class="mt-4">
            <p class="text-[11px] font-bold text-slate-400">直近の弱点</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($weaknesses as $weakness)
                    <span class="rounded-full border border-sky-300/15 bg-sky-300/[0.04] px-3 py-1 text-[11px] text-sky-100">{{ is_array($weakness) ? ($weakness['label'] ?? $weakness['topic'] ?? '要確認') : $weakness }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <p class="mt-3 text-sm leading-6 text-slate-300">{{ $studyNextAction ?: ($hubCurrentTask?->next_action_note ?: '演習結果とCurrent Taskを使って、次に確認する範囲を絞ります。') }}</p>

    @if ($hubCurrentTask && ($primaryExecutionTool['id'] ?? null) === 'study_activity')
        <a href="{{ route('plans.tasks.study_activity.show', [$item['plan'], $hubCurrentTask]) }}" class="btn-secondary mt-4 px-3 py-2 text-xs">{{ data_get($primaryExecutionTool, 'activity.short_label', '学習方法') }}を開く →</a>
    @elseif ($hubCurrentTask && ($primaryExecutionTool['id'] ?? null) === 'ai_practice')
        <a href="{{ route('plans.tasks.study_practice.show', [$item['plan'], $hubCurrentTask]) }}" class="btn-secondary mt-4 px-3 py-2 text-xs">AI演習を開く →</a>
    @endif
</section>
