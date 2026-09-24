@php
    $artifactCount = (int) ($surface->payload['artifact_count'] ?? 0);
    $latestArtifactEvidence = $surface->payload['latest_artifact'] ?? null;
@endphp
<section class="page-card pk-v18-section-card p-4 sm:p-5" data-surface-id="delivery_focus">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-violet-300">DELIVERY FOCUS</p>
            <h2 class="mt-1 text-base font-black text-slate-100 sm:text-lg">成果物の現在地</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">開発・制作Planでは、時間より成果物の変化を前に出します。</p>
        </div>
        <span class="badge badge-slate">{{ $artifactCount }}件</span>
    </div>

    @if ($latestArtifactEvidence)
        <div class="mt-4 rounded-xl border border-violet-300/10 bg-violet-300/[0.03] px-3 py-3">
            <p class="text-xs font-bold text-violet-100">最新Evidence</p>
            <p class="mt-1 text-sm leading-6 text-slate-300">{{ $latestArtifactEvidence->summary() }}</p>
        </div>
    @endif

    <a href="{{ route('plans.artifacts.index', $item['plan']) }}" class="btn-secondary mt-4 px-3 py-2 text-xs">制作ファイルを見る →</a>
</section>
