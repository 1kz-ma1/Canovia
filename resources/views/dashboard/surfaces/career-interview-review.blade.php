@php
    $reviewEvent = $surface->payload['event'] ?? null;
    $reviewApplication = $reviewEvent?->application;
@endphp
@if ($reviewEvent && $reviewApplication)
    <section class="page-card pk-v18-section-card border-fuchsia-300/20 p-4 sm:p-5" data-surface-id="career_interview_review">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-fuchsia-300">INTERVIEW REVIEW</p>
                <h2 class="mt-1 text-base font-black text-slate-100 sm:text-lg">面接の記憶が新しいうちに</h2>
                <p class="mt-1 text-sm font-bold text-fuchsia-100">{{ $reviewApplication->company_name }} · {{ $reviewEvent->stageLabel() }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-400">合否とは別に、今回うまくいったこと・詰まったことを次の面接へ残します。</p>
            </div>
            <span class="badge badge-slate">{{ $reviewEvent->scheduled_at?->format('m/d H:i') }}</span>
        </div>
        <a href="{{ route('plans.career.interview_reviews.show', [$item['plan'], $reviewEvent]) }}" class="btn-primary mt-4 px-3 py-2 text-xs">面接を振り返る →</a>
    </section>
@endif
