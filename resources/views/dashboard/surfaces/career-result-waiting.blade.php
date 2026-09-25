@php
    $waitingEvents = collect($surface->payload['events'] ?? []);
@endphp
@if ($waitingEvents->isNotEmpty())
    <section class="page-card pk-v18-section-card p-4 sm:p-5" data-surface-id="career_result_waiting">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-400">RESULT WAITING</p>
                <h2 class="mt-1 text-base font-black text-slate-100 sm:text-lg">結果待ち</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">振り返りは完了済み。結果を待つ間は、次の応募や面接準備を止める必要はありません。</p>
            </div>
            <span class="badge badge-slate">{{ $waitingEvents->count() }}件</span>
        </div>
        <div class="mt-3 space-y-2">
            @foreach ($waitingEvents as $waitingEvent)
                <div class="rounded-xl border border-white/8 bg-slate-950/20 px-3 py-2 text-sm text-slate-300">
                    {{ $waitingEvent->application?->company_name ?? '応募先' }} · {{ $waitingEvent->stageLabel() }}
                </div>
            @endforeach
        </div>
        <a href="{{ route('plans.career.index', $item['plan']) }}" class="mt-3 inline-block text-xs font-bold text-sky-300">選考状況を見る →</a>
    </section>
@endif
