@php
    $pipeline = collect($surface->payload['pipeline'] ?? []);
    $pipelineSource = $surface->payload['source'] ?? 'tasks';
    $visibleStages = $pipeline->filter(fn ($stage) => ($stage['total'] ?? 0) > 0 || in_array($stage['key'] ?? '', ['discovery', 'application', 'interview', 'offer'], true));
@endphp
<section class="page-card pk-v18-section-card p-4 sm:p-5" data-surface-id="career_pipeline">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-fuchsia-300">CAREER PIPELINE</p>
            <h2 class="mt-1 text-base font-black text-slate-100 sm:text-lg">応募・選考の流れ</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                {{ $pipelineSource === 'applications' ? '応募先データから現在の選考段階を整理しています。' : 'まだ応募先データが少ないため、Taskから就活の現在地を整理しています。' }}
            </p>
        </div>
        <span class="badge badge-slate">{{ $pipelineSource === 'applications' ? '応募データ' : 'Taskから自動整理' }}</span>
    </div>

    <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($visibleStages as $stage)
            <div class="rounded-2xl border border-white/8 bg-white/[0.025] p-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-black text-slate-200">{{ $stage['label'] }}</p>
                    <span class="text-sm font-black text-slate-100">{{ $stage['total'] }}</span>
                </div>

                @if ($pipelineSource === 'applications')
                    @if (collect($stage['applications'] ?? [])->isNotEmpty())
                        <div class="mt-2 space-y-1.5">
                            @foreach (collect($stage['applications'])->take(3) as $application)
                                <p class="truncate text-[11px] text-slate-400">{{ $application->company_name }}{{ $application->role_title ? ' · '.$application->role_title : '' }}</p>
                            @endforeach
                        </div>
                    @elseif (($stage['total'] ?? 0) === 0)
                        <p class="mt-2 text-[10px] text-slate-600">まだなし</p>
                    @endif
                @else
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-800">
                        <div class="h-full rounded-full bg-slate-300/60" style="width: {{ ($stage['total'] ?? 0) > 0 ? min(100, round((($stage['done'] ?? 0) / $stage['total']) * 100)) : 0 }}%"></div>
                    </div>
                    @if (($stage['active'] ?? 0) > 0)
                        <p class="mt-2 text-[10px] text-fuchsia-200/80">進行中 {{ $stage['active'] }}件</p>
                    @elseif (($stage['total'] ?? 0) === 0)
                        <p class="mt-2 text-[10px] text-slate-600">まだTaskなし</p>
                    @else
                        <p class="mt-2 text-[10px] text-slate-500">この段階は完了</p>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
        <p class="text-[11px] leading-5 text-slate-500">応募先や選考予定が増えるほど、次に力を入れる段階が見えやすくなります。</p>
        <a href="{{ route('plans.career.index', $item['plan']) }}" class="text-xs font-bold text-fuchsia-300">Careerを開く →</a>
    </div>
</section>
