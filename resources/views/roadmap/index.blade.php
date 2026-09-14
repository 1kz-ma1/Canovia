@extends('layouts.app')

@section('title', 'ロードマップ | PaceKeeper')

@section('content')
    @php
        $previousRoadmapUrl = $previousPlan ? route('roadmap.index', ['plan_id' => $previousPlan->id]) : null;
        $nextRoadmapUrl = $nextPlan ? route('roadmap.index', ['plan_id' => $nextPlan->id]) : null;
    @endphp

    @if ($previousRoadmapUrl)<link rel="prefetch" href="{{ $previousRoadmapUrl }}">@endif
    @if ($nextRoadmapUrl)<link rel="prefetch" href="{{ $nextRoadmapUrl }}">@endif

    <div class="pk-v19-roadmap-page">
        <header class="pk-v19-roadmap-hero">
            <div class="pk-v19-roadmap-hero-copy">
                <p class="pk-v18-eyebrow">PACEKEEPER / ROADMAP</p>
                <h1>ロードマップ</h1>
                <p>小さな一歩が、<br>大きな未来につながる。</p>
            </div>
            <div class="pk-v19-roadmap-planet" aria-hidden="true"></div>
            <img src="/brand/mascot-guide.webp" alt="" class="pk-v19-roadmap-guide" aria-hidden="true">
            <p class="pk-v19-roadmap-guide-copy" aria-hidden="true">一歩ずつ進んで<br>理想の自分に<br>近づこう！ ✦</p>
        </header>

        @if ($plans->isNotEmpty())
            <nav class="pk-v19-plan-carousel" data-roadmap-plan-tabs aria-label="計画を切り替える">
                @foreach ($plans as $item)
                    @php
                        $totalTasks = $item->tasks->count();
                        $doneTasks = $item->tasks->where('status', 'done')->count();
                        $percent = $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0;
                    @endphp
                    <a
                        href="{{ route('roadmap.index', ['plan_id' => $item->id]) }}"
                        class="pk-v19-plan-card {{ $plan?->id === $item->id ? 'is-active' : '' }}"
                        data-plan-accent="{{ $item->accentKey() }}"
                        aria-current="{{ $plan?->id === $item->id ? 'page' : 'false' }}"
                    >
                        <span class="pk-v19-plan-card-icon" aria-hidden="true">{{ $item->displayIcon() }}</span>
                        <span class="pk-v19-plan-card-copy">
                            <strong>{{ $item->title }}</strong>
                            <small>{{ $doneTasks }} / {{ $totalTasks }}</small>
                            <i><b style="width: {{ $percent }}%"></b></i>
                        </span>
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
                <section class="pk-v19-roadmap-surface plan-identity-shell" data-plan-accent="{{ $plan->accentKey() }}">
                    @include('plans.partials.roadmap', [
                        'roadmap' => $roadmap,
                        'roadmapPlan' => $plan,
                        'roadmapCanEdit' => $canEdit,
                        'roadmapMode' => 'plan',
                        'roadmapRecommendedMinutes' => $recommendation?->recommendedMinutes,
                        'roadmapRecommendationReasons' => $recommendation?->reasons ?? [],
                    ])
                </section>
                <p class="roadmap-swipe-hint md:hidden" aria-hidden="true">← スワイプで計画を切替 →</p>
            </div>
        @else
            <section class="empty-state page-card p-8 text-center">
                <div class="text-4xl" aria-hidden="true">🗺️</div>
                <h2 class="mt-3 text-xl font-bold text-slate-100">まだロードマップがありません</h2>
                <p class="mt-2 text-sm leading-6 text-slate-400">計画を作ると、ここに未来へ続く道が見えるようになります。</p>
                <a href="{{ route('plans.create') }}" class="btn-primary mt-5">最初の計画を作る</a>
            </section>
        @endif

        @if ($plan)
            <blockquote class="pk-v19-roadmap-quote">
                <span aria-hidden="true">“</span>
                <p>今の努力が、きっとどこかでつながってる。</p>
                <small>A BRIGHTER<br>TOMORROW.</small>
            </blockquote>
        @endif
    </div>
@endsection
