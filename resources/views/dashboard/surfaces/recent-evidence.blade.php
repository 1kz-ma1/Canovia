@if ($recentEvidence->isNotEmpty())
    <section class="page-card pk-v18-section-card p-4 sm:p-5" data-surface-id="recent_evidence">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-emerald-300">RECENT EVIDENCE</p>
                <h2 class="mt-1 text-base font-black text-slate-100 sm:text-lg">Canoviaが確認できた事実</h2>
            </div>
            <span class="text-[10px] text-slate-500">進捗率とは別に保持</span>
        </div>
        <div class="mt-4 space-y-2">
            @foreach ($recentEvidence as $evidence)
                <div class="rounded-xl border border-white/8 bg-slate-950/25 px-3 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-bold text-slate-200">{{ $evidence['type_label'] }}</p>
                        <span class="text-[10px] text-slate-500">{{ $evidence['source_label'] }} · {{ $evidence['occurred_at']?->diffForHumans() }}</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-5 text-slate-400">{{ $evidence['summary'] }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
