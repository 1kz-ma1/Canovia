@php
    $interviewTasks = collect($surface->payload['tasks'] ?? []);
@endphp
@if ($interviewTasks->isNotEmpty())
    <section class="page-card pk-v18-section-card border-fuchsia-300/15 p-4 sm:p-5" data-surface-id="career_interview_focus">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-fuchsia-300">INTERVIEW FOCUS</p>
                <h2 class="mt-1 text-base font-black text-slate-100 sm:text-lg">今は面接・選考対策を前に</h2>
                <p class="mt-1 text-xs leading-5 text-slate-400">面接系Taskがあるため、このカードを就活Planで一時的に優先表示しています。</p>
            </div>
            <span class="badge badge-slate">{{ $interviewTasks->count() }}件</span>
        </div>
        <div class="mt-4 space-y-2">
            @foreach ($interviewTasks as $interviewTask)
                <div class="rounded-xl border border-fuchsia-300/10 bg-fuchsia-300/[0.03] px-3 py-3">
                    <p class="text-sm font-bold text-slate-100">{{ $interviewTask->title }}</p>
                    @if ($interviewTask->next_action_note)
                        <p class="mt-1 text-xs leading-5 text-fuchsia-100/80">{{ $interviewTask->next_action_note }}</p>
                    @endif
                    <p class="mt-1 text-[10px] text-slate-500">進捗 {{ (int) $interviewTask->progress_percent }}% · 残り目安 {{ (int) ($interviewTask->remaining_minutes ?? 0) }}分</p>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-[11px] text-slate-500">面接Taskがなくなれば、このSurfaceは自動でHomeから消えます。</p>
    </section>
@endif
