@extends('layouts.app')

@section('title', 'AI Funnel Diagnostics | Canovia')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-cyan-300">AI Funnel Diagnostics</p>
                <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-50">計画作成・更新の診断</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">
                    JSON本文やプロンプト内容は保存せず、どの段階まで進めたか・どこで失敗したかだけを集計します。
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.feedback.index') }}" class="btn-secondary">フィードバック</a>
                @foreach ([7, 30] as $range)
                    <a
                        href="{{ route('admin.telemetry.index', ['days' => $range]) }}"
                        class="{{ $days === $range ? 'btn-primary' : 'btn-secondary' }}"
                    >{{ $range }}日</a>
                @endforeach
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['label' => '初期計画 JSON成功率', 'value' => $generationRate],
                ['label' => '更新プレビュー成功率', 'value' => $previewRate],
                ['label' => '更新確定成功率', 'value' => $applyRate],
            ] as $metric)
                <article class="page-card p-5">
                    <p class="text-xs font-black uppercase tracking-[0.12em] text-slate-400">{{ $metric['label'] }}</p>
                    <p class="mt-3 text-3xl font-black text-slate-50">
                        {{ $metric['value'] === null ? '—' : number_format($metric['value'], 1).'%' }}
                    </p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="page-card p-5">
                <div class="mb-4">
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-sky-300">Plan Generation</p>
                    <h2 class="mt-1 text-xl font-black text-slate-50">初期計画ファネル</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-sm">
                        <thead class="text-xs uppercase tracking-[0.08em] text-slate-500">
                            <tr>
                                <th class="pb-3 pr-4">段階</th>
                                <th class="pb-3 pr-4">到達ユーザー</th>
                                <th class="pb-3">イベント数</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach ($generation as $row)
                                <tr>
                                    <td class="py-3 pr-4 font-semibold text-slate-200">{{ $row['label'] }}</td>
                                    <td class="py-3 pr-4 text-slate-300">{{ $row['actors'] }}</td>
                                    <td class="py-3 text-slate-400">{{ $row['events'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="page-card p-5">
                <div class="mb-4">
                    <p class="text-xs font-black uppercase tracking-[0.14em] text-violet-300">Plan Update</p>
                    <h2 class="mt-1 text-xl font-black text-slate-50">計画更新ファネル</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-sm">
                        <thead class="text-xs uppercase tracking-[0.08em] text-slate-500">
                            <tr>
                                <th class="pb-3 pr-4">段階</th>
                                <th class="pb-3 pr-4">到達ユーザー</th>
                                <th class="pb-3">イベント数</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach ($updates as $row)
                                <tr>
                                    <td class="py-3 pr-4 font-semibold text-slate-200">{{ $row['label'] }}</td>
                                    <td class="py-3 pr-4 text-slate-300">{{ $row['actors'] }}</td>
                                    <td class="py-3 text-slate-400">{{ $row['events'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <article class="page-card p-5">
                <p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-300">Environment</p>
                <h2 class="mt-1 text-xl font-black text-slate-50">利用環境別</h2>
                <div class="mt-4 space-y-3">
                    @foreach ($surfaceSummary as $surface => $stats)
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/35 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <strong class="text-slate-100">{{ strtoupper($surface) }}</strong>
                                <span class="text-xs text-slate-500">{{ $stats['actors'] }} users / {{ $stats['events'] }} events</span>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-xs text-slate-500">初期計画成功</p>
                                    <p class="mt-1 font-bold text-slate-200">{{ $stats['generation_success'] }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-500">計画更新成功</p>
                                    <p class="mt-1 font-bold text-slate-200">{{ $stats['update_success'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="page-card p-5">
                <p class="text-xs font-black uppercase tracking-[0.14em] text-rose-300">Failures</p>
                <h2 class="mt-1 text-xl font-black text-slate-50">失敗理由</h2>

                @if ($failureReasons->isEmpty())
                    <p class="mt-4 text-sm text-slate-500">この期間に記録された失敗はありません。</p>
                @else
                    <div class="mt-4 space-y-2">
                        @foreach ($failureReasons as $reason => $count)
                            <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-950/30 px-4 py-3">
                                <code class="text-xs text-rose-200">{{ $reason }}</code>
                                <strong class="text-slate-100">{{ $count }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif
            </article>
        </section>

        <section class="page-card p-5">
            <div class="mb-4">
                <p class="text-xs font-black uppercase tracking-[0.14em] text-amber-300">Recent failures</p>
                <h2 class="mt-1 text-xl font-black text-slate-50">直近の失敗</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">ユーザー本文・JSON・プロンプト・メールアドレスは表示しません。</p>
            </div>

            @if ($recentFailures->isEmpty())
                <p class="text-sm text-slate-500">失敗ログはありません。</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="text-xs uppercase tracking-[0.08em] text-slate-500">
                            <tr>
                                <th class="pb-3 pr-4">日時</th>
                                <th class="pb-3 pr-4">イベント</th>
                                <th class="pb-3 pr-4">環境</th>
                                <th class="pb-3 pr-4">理由</th>
                                <th class="pb-3">対象フィールド</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach ($recentFailures as $failure)
                                <tr>
                                    <td class="py-3 pr-4 text-slate-400">{{ $failure['occurred_at']?->format('m/d H:i') }}</td>
                                    <td class="py-3 pr-4 text-slate-300">{{ $failure['event_type'] }}</td>
                                    <td class="py-3 pr-4 text-slate-400">{{ $failure['surface'] }} / {{ $failure['device'] }}</td>
                                    <td class="py-3 pr-4"><code class="text-xs text-rose-200">{{ $failure['failure_code'] }}</code></td>
                                    <td class="py-3 text-slate-500">{{ implode(', ', $failure['validation_fields']) ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
