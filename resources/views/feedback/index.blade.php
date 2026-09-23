@extends('layouts.app')

@section('title', 'Canovia Future | Canovia')

@section('content')
    <div class="mx-auto max-w-5xl space-y-8">
        <header class="space-y-4">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-sky-300">CANOVIA FUTURE</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-50 sm:text-4xl">次のCanoviaを、一緒に選ぶ</h1>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-400 sm:text-base">
                    Canoviaが考えている次の候補へ、欲しいと思ったものをSupportできます。
                    Support数は需要を確かめるための材料で、到達したら必ず実装されるという意味ではありません。
                </p>
            </div>
            <div class="rounded-2xl border border-sky-400/20 bg-sky-400/5 p-4 text-sm leading-6 text-slate-300">
                <strong class="text-sky-200">500 Support</strong> は「Roadmap入り・正式検討を始める目安」です。
                最終的な優先順位は、Canovia全体の体験・安全性・実装コスト・他のFeedbackも合わせて判断します。
            </div>
        </header>

        <section class="grid gap-4 md:grid-cols-2">
            @forelse ($features as $item)
                @php
                    /** @var \App\Models\RoadmapFeature $feature */
                    $feature = $item['model'];
                @endphp
                <article class="page-card flex h-full flex-col p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <span class="inline-flex rounded-full border border-slate-700 bg-slate-900/80 px-2.5 py-1 text-[10px] font-black tracking-[0.12em] text-slate-300">
                                {{ $feature->status->label() }}
                            </span>
                            <h2 class="mt-3 text-lg font-black text-slate-50">{{ $feature->title }}</h2>
                        </div>
                        @if ($feature->status->value === 'released')
                            <span class="text-xs font-black text-emerald-300">RELEASED</span>
                        @endif
                    </div>

                    @if ($feature->description)
                        <p class="mt-3 flex-1 text-sm leading-6 text-slate-400">{{ $feature->description }}</p>
                    @endif

                    <div class="mt-5">
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <strong class="text-slate-100">{{ number_format($item['votes']) }} / {{ number_format($feature->threshold) }} Support</strong>
                            <span class="text-slate-500">{{ $item['progress'] }}%</span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-800" aria-label="Support progress">
                            <div class="h-full rounded-full bg-sky-400" style="width: {{ $item['progress'] }}%"></div>
                        </div>
                    </div>

                    <div class="mt-5">
                        @if (! $feature->voting_enabled)
                            <button type="button" class="btn-secondary w-full justify-center" disabled>Support受付を停止中</button>
                        @elseif ($item['supported'])
                            <form method="POST" action="{{ route('feedback.future.unsupport', $feature) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-secondary w-full justify-center">Support済み・取り消す</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('feedback.future.support', $feature) }}">
                                @csrf
                                <button type="submit" class="btn-primary w-full justify-center">この機能をSupport</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="page-card p-6 md:col-span-2">
                    <p class="text-sm text-slate-400">現在Supportを受け付けている候補はありません。</p>
                </div>
            @endforelse
        </section>

        <section class="page-card p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">OTHER FEEDBACK</p>
                    <h2 class="mt-2 text-xl font-black text-slate-50">その他のフィードバック</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-400">候補にない要望、使いづらさ、不具合、良かった点はこれまで通り自由に送れます。</p>
                </div>
                <button type="button" class="btn-secondary shrink-0 justify-center" data-feedback-open>自由記述で送る</button>
            </div>
        </section>
    </div>
@endsection
