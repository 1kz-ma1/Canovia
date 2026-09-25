@php
    $pendingCaptureCount = (int) ($surface->payload['pending_count'] ?? 0);
@endphp
@if ($pendingCaptureCount > 0)
    <section class="page-card pk-v18-section-card p-4 sm:p-5" data-surface-id="career_capture_inbox">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-amber-300">CAPTURE INBOX</p>
                <h2 class="mt-1 text-base font-black text-slate-100 sm:text-lg">取り込んだ就活情報</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">スクショやURLを先に残したまま、まだ応募先へ整理していない情報があります。</p>
            </div>
            <span class="badge badge-slate">{{ $pendingCaptureCount }}件</span>
        </div>
        <a href="{{ route('plans.career.index', $item['plan']) }}" class="btn-secondary mt-4 px-3 py-2 text-xs">Inboxを見る →</a>
    </section>
@endif
