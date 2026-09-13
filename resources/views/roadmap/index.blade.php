@extends('layouts.app')

@section('title', 'ロードマップ | Pace Keeper')

@section('content')
    @php
        $previousRoadmapUrl = $previousPlan ? route('roadmap.index', ['plan_id' => $previousPlan->id]) : null;
        $nextRoadmapUrl = $nextPlan ? route('roadmap.index', ['plan_id' => $nextPlan->id]) : null;
    @endphp

    @if ($previousRoadmapUrl)
        <link rel="prefetch" href="{{ $previousRoadmapUrl }}">
    @endif
    @if ($nextRoadmapUrl)
        <link rel="prefetch" href="{{ $nextRoadmapUrl }}">
    @endif

    <div class="space-y-5">
        <header class="roadmap-page-header pk-v18-page-hero pk-v18-roadmap-hero">
            <div class="relative z-10 min-w-0">
                <p class="pk-v18-eyebrow">ROADMAP / SEE THE PATH</p>
                <h1>あなたのペースで、未来へつながる。</h1>
                <p>今いる星と、その先のルートをひと目で。</p>
            </div>
            <div class="pk-v18-roadmap-destination" aria-hidden="true"><i></i><i></i><i></i><span>⚑</span></div>
            <img src="/brand/mascot-guide.webp" alt="" class="pk-v18-roadmap-mascot" aria-hidden="true">

            @if ($plans->isNotEmpty())
                <form method="GET" action="{{ route('roadmap.index') }}" class="relative z-20 hidden min-w-56 md:block">
                    <label class="text-[11px] font-bold text-slate-400" for="roadmap-plan">計画を選ぶ</label>
                    <select id="roadmap-plan" name="plan_id" class="form-control mt-1.5" onchange="this.form.submit()">
                        @foreach ($plans as $item)
                            <option value="{{ $item->id }}" @selected($plan?->id === $item->id)>{{ $item->displayIcon() }} {{ $item->title }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </header>

        @if ($plans->isNotEmpty())
            <nav class="roadmap-plan-tabs" data-roadmap-plan-tabs aria-label="Plan切替">
                @foreach ($plans as $item)
                    <a
                        href="{{ route('roadmap.index', ['plan_id' => $item->id]) }}"
                        class="roadmap-plan-tab {{ $plan?->id === $item->id ? 'is-active' : '' }}"
                        data-plan-accent="{{ $item->accentKey() }}"
                        aria-current="{{ $plan?->id === $item->id ? 'page' : 'false' }}"
                    >
                        <span aria-hidden="true">{{ $item->displayIcon() }}</span>
                        <span class="truncate">{{ $item->title }}</span>
                    </a>
                @endforeach
            </nav>
        @endif

        @if ($plan && $roadmap)
            <div
                class="roadmap-plan-pager"
                data-roadmap-plan-pager
                data-onboarding-target="roadmap-surface"
                tabindex="0"
                data-prev-url="{{ $previousRoadmapUrl }}"
                data-next-url="{{ $nextRoadmapUrl }}"
                aria-live="polite"
            >
                <section class="page-card pk-v18-roadmap-card p-3 sm:p-5 plan-identity-shell roadmap-page-card" data-plan-accent="{{ $plan->accentKey() }}">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                @if ($previousRoadmapUrl)
                                    <a href="{{ $previousRoadmapUrl }}" class="roadmap-plan-arrow" aria-label="前のPlanへ">‹</a>
                                @else
                                    <span class="roadmap-plan-arrow is-disabled" aria-hidden="true">‹</span>
                                @endif

                                <p class="plan-identity-chip min-w-0 text-xs">
                                    <span aria-hidden="true">{{ $plan->displayIcon() }}</span>
                                    <span class="truncate">{{ $plan->title }}</span>
                                </p>

                                @if ($nextRoadmapUrl)
                                    <a href="{{ $nextRoadmapUrl }}" class="roadmap-plan-arrow" aria-label="次のPlanへ">›</a>
                                @else
                                    <span class="roadmap-plan-arrow is-disabled" aria-hidden="true">›</span>
                                @endif
                            </div>

                            @if ($continuity)
                                <p class="mt-1.5 text-xs text-slate-400">前回：{{ $continuity['task_title'] }}{{ $continuity['ended_at'] ? '・'.$continuity['ended_at']->diffForHumans() : '' }}</p>
                            @endif
                        </div>
                        <a href="{{ route('plans.edit', $plan) }}#plan-design" class="btn-secondary shrink-0 px-3 py-2 text-xs">🎨 デザイン</a>
                    </div>

                    @include('plans.partials.roadmap', [
                        'roadmap' => $roadmap,
                        'roadmapPlan' => $plan,
                        'roadmapCanEdit' => true,
                        'roadmapMode' => 'plan',
                        'roadmapRecommendedMinutes' => $recommendation?->recommendedMinutes,
                        'roadmapRecommendationReasons' => $recommendation?->reasons ?? [],
                    ])
                </section>

                <p class="roadmap-swipe-hint md:hidden" aria-hidden="true">← スワイプで切替 →</p>
            </div>
        @else
            <section class="empty-state page-card p-8 text-center">
                <div class="text-4xl" aria-hidden="true">🗺️</div>
                <h2 class="mt-3 text-xl font-bold text-slate-100">まだロードマップがありません</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">計画を作ると、ここに進む道が見えるようになります。</p>
                <a href="{{ route('plans.create') }}" class="btn-primary mt-5">最初の計画を作る</a>
            </section>
        @endif
    </div>
@endsection
