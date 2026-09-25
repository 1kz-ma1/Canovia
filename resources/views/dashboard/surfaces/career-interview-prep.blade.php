@php
    $prepEvent = $surface->payload['event'] ?? null;
    $prepApplication = $prepEvent?->application;
@endphp
@if ($prepEvent && $prepApplication)
    <section class="page-card pk-v18-section-card border-fuchsia-300/15 p-4 sm:p-5" data-surface-id="career_interview_prep">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-fuchsia-300">NEXT INTERVIEW</p>
                <h2 class="mt-1 text-base font-black text-slate-100 sm:text-lg">{{ $prepApplication->company_name }}</h2>
                <p class="mt-1 text-sm text-slate-300">{{ $prepApplication->role_title ?: '職種未設定' }} · {{ $prepEvent->stageLabel() }}</p>
            </div>
            <div class="text-right">
                <p class="text-sm font-black text-fuchsia-100">{{ $prepEvent->scheduled_at?->format('m/d H:i') }}</p>
                <p class="mt-1 text-[10px] text-slate-500">{{ $prepEvent->scheduled_at?->diffForHumans() }}</p>
            </div>
        </div>
        <p class="mt-3 text-xs leading-5 text-slate-400">面接日時を過ぎると、振り返りSurfaceへ切り替わります。</p>
        <a href="{{ route('plans.career.index', $item['plan']) }}" class="btn-secondary mt-4 px-3 py-2 text-xs">選考情報を見る →</a>
    </section>
@endif
