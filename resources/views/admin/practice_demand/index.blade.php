@extends('layouts.app')

@section('title', 'Practice Demand | Canovia Admin')

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        @include('admin.partials.nav')

        <header class="rounded-[1.6rem] border border-cyan-300/15 bg-slate-950/55 p-5 sm:p-7">
            <p class="text-[10px] font-black uppercase tracking-[.18em] text-cyan-300">PRACTICE SUPPLY LOOP</p>
            <div class="mt-2 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-slate-50 sm:text-3xl">演習需要とQuestion Candidate</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">
                        実際に準備された演習から、Question Bankで賄えた量とAI補完が必要だった場面を確認します。
                        AI生成問題は公開せずCandidateとして隔離し、人が確認したものだけDraft Packへ昇格します。
                    </p>
                </div>
                <a href="{{ route('admin.question_packs.index') }}" class="btn-secondary shrink-0">Question Pack管理</a>
            </div>
        </header>

        <section class="page-card p-5 sm:p-6">
            <form method="GET" action="{{ route('admin.practice_demand.index') }}" class="grid gap-3 md:grid-cols-4">
                <label class="text-xs font-bold text-slate-400">
                    集計期間
                    <select name="period" class="form-control mt-2">
                        @foreach (['7' => '7日', '30' => '30日', '90' => '90日', 'all' => '全期間'] as $value => $label)
                            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-bold text-slate-400">
                    Exam Profile
                    <select name="exam_profile" class="form-control mt-2">
                        <option value="">すべて</option>
                        @foreach ($availableProfiles as $profile)
                            <option value="{{ $profile }}" @selected($examProfile === $profile)>{{ $profile }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-bold text-slate-400">
                    Assembly
                    <select name="assembly_mode" class="form-control mt-2">
                        <option value="">すべて</option>
                        @foreach ($availableAssemblyModes as $mode)
                            <option value="{{ $mode }}" @selected($assemblyMode === $mode)>{{ $mode }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary">絞り込む</button>
                    <a href="{{ route('admin.practice_demand.index') }}" class="btn-secondary">解除</a>
                </div>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="page-card p-4">
                <p class="text-[10px] font-bold uppercase tracking-[.12em] text-slate-500">SESSIONS</p>
                <p class="mt-2 text-2xl font-black text-slate-100">{{ number_format($summary['sessions']) }}</p>
            </div>
            <div class="page-card p-4">
                <p class="text-[10px] font-bold uppercase tracking-[.12em] text-slate-500">REQUESTED</p>
                <p class="mt-2 text-2xl font-black text-slate-100">{{ number_format($summary['requested_count']) }}問</p>
            </div>
            <div class="page-card p-4">
                <p class="text-[10px] font-bold uppercase tracking-[.12em] text-emerald-400">BANK SUPPLY</p>
                <p class="mt-2 text-2xl font-black text-emerald-100">{{ number_format($summary['bank_selected_count']) }}問</p>
                <p class="mt-1 text-xs text-slate-500">充足 {{ number_format($summary['bank_fill_rate'], 1) }}%</p>
            </div>
            <div class="page-card p-4">
                <p class="text-[10px] font-bold uppercase tracking-[.12em] text-amber-300">AI GAP</p>
                <p class="mt-2 text-2xl font-black text-amber-100">{{ number_format($summary['generated_requested_count']) }}問</p>
                <p class="mt-1 text-xs text-slate-500">Bankで埋まらなかった枠</p>
            </div>
            <div class="page-card p-4">
                <p class="text-[10px] font-bold uppercase tracking-[.12em] text-cyan-300">GENERATED</p>
                <p class="mt-2 text-2xl font-black text-cyan-100">{{ number_format($summary['generated_count']) }}問</p>
                <p class="mt-1 text-xs text-slate-500">補完成功 {{ number_format($summary['generation_fill_rate'], 1) }}%</p>
            </div>
        </section>

        @if ($analysisCapped)
            <div class="rounded-2xl border border-amber-300/15 bg-amber-300/[0.04] p-4 text-xs leading-6 text-amber-100/80">
                トピック・Profile集計は表示負荷を抑えるため最新5,000件を対象にしています。上部の総数・問数は全対象データから集計しています。
            </div>
        @endif

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="page-card overflow-hidden">
                <div class="border-b border-slate-800 p-5">
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-violet-300">EXAM PROFILE</p>
                    <h2 class="mt-1 text-xl font-black text-slate-50">資格・試験単位の供給状況</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="bg-slate-950/45 text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Profile</th>
                                <th class="px-4 py-3 text-right">Session</th>
                                <th class="px-4 py-3 text-right">要求</th>
                                <th class="px-4 py-3 text-right">Bank</th>
                                <th class="px-4 py-3 text-right">不足</th>
                                <th class="px-4 py-3 text-right">Bank充足</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @forelse ($profileStats as $stat)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-200">{{ $stat['key'] }}</td>
                                    <td class="px-4 py-3 text-right text-slate-400">{{ $stat['sessions'] }}</td>
                                    <td class="px-4 py-3 text-right text-slate-400">{{ $stat['requested_count'] }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-200">{{ $stat['bank_selected_count'] }}</td>
                                    <td class="px-4 py-3 text-right text-amber-200">{{ $stat['generated_requested_count'] }}</td>
                                    <td class="px-4 py-3 text-right text-slate-300">{{ number_format($stat['bank_fill_rate'], 1) }}%</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-slate-500">対象データはまだありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="page-card overflow-hidden">
                <div class="border-b border-slate-800 p-5">
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-amber-300">FOCUS GAP</p>
                    <h2 class="mt-1 text-xl font-black text-slate-50">重点トピックで不足が起きた回数</h2>
                    <p class="mt-2 text-xs leading-5 text-slate-500">
                        1セッションに複数topicがあるため、問数をtopicへ推定配賦せず「不足を伴ったSession数」で表示します。
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-xs">
                        <thead class="bg-slate-950/45 text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Topic</th>
                                <th class="px-4 py-3 text-right">登場</th>
                                <th class="px-4 py-3 text-right">不足あり</th>
                                <th class="px-4 py-3 text-right">不足率</th>
                                <th class="px-4 py-3">最終</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/80">
                            @forelse ($topicStats as $stat)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-slate-200">{{ $stat['topic'] }}</td>
                                    <td class="px-4 py-3 text-right text-slate-400">{{ $stat['sessions'] }}</td>
                                    <td class="px-4 py-3 text-right text-amber-200">{{ $stat['gap_sessions'] }}</td>
                                    <td class="px-4 py-3 text-right text-slate-300">{{ number_format($stat['gap_rate'], 1) }}%</td>
                                    <td class="px-4 py-3 text-slate-500">{{ $stat['latest_at']?->format('m/d H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-6 text-slate-500">focus topic付きの需要はまだありません。</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="page-card p-5 sm:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-cyan-300">QUESTION CANDIDATES</p>
                    <h2 class="mt-1 text-xl font-black text-slate-50">Native AI生成問題の確認キュー</h2>
                    <p class="mt-2 text-xs leading-5 text-slate-500">
                        CandidateはQuestion Bankではありません。正答・解説・learning metadataを人が確認してからDraft Packへ昇格します。
                    </p>
                </div>
                <form method="GET" action="{{ route('admin.practice_demand.index') }}" class="flex flex-wrap items-end gap-2">
                    <input type="hidden" name="period" value="{{ $period }}">
                    @if ($examProfile !== '')<input type="hidden" name="exam_profile" value="{{ $examProfile }}">@endif
                    @if ($assemblyMode !== '')<input type="hidden" name="assembly_mode" value="{{ $assemblyMode }}">@endif
                    <label class="text-xs font-bold text-slate-400">
                        状態
                        <select name="candidate_status" class="form-control mt-2 min-w-[150px]">
                            <option value="all" @selected($candidateStatus === 'all')>すべて</option>
                            @foreach (\App\Models\PracticeQuestionCandidate::STATUSES as $status)
                                <option value="{{ $status }}" @selected($candidateStatus === $status)>
                                    {{ $status }} ({{ $candidateCounts->get($status, 0) }})
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn-secondary">表示</button>
                </form>
            </div>

            <div class="mt-5 grid gap-3">
                @forelse ($candidates as $candidate)
                    <a href="{{ route('admin.practice_demand.candidates.show', $candidate) }}" class="rounded-2xl border border-slate-800 bg-slate-950/35 p-4 transition hover:border-cyan-300/30">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="badge {{ $candidate->status === 'pending' ? 'badge-yellow' : ($candidate->status === 'promoted' ? 'badge-green' : 'badge-slate') }}">{{ $candidate->status }}</span>
                                    @if ($candidate->exam_profile_key)
                                        <span class="badge badge-slate">{{ $candidate->exam_profile_key }}</span>
                                    @endif
                                    <span class="text-[11px] text-slate-500">生成 {{ $candidate->generation_count }}回</span>
                                </div>
                                <p class="mt-3 line-clamp-2 text-sm font-semibold leading-6 text-slate-200">{{ data_get($candidate->question_payload, 'prompt') }}</p>
                            </div>
                            <span class="text-lg text-slate-600">→</span>
                        </div>
                        <p class="mt-3 text-[11px] text-slate-500">
                            {{ $candidate->model ?: 'model不明' }}
                            · 最終 {{ $candidate->last_seen_at?->format('Y-m-d H:i') ?: '不明' }}
                            @if ($candidate->promotedPack) · {{ $candidate->promotedPack->title }}へ昇格済み @endif
                        </p>
                    </a>
                @empty
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/35 p-5 text-sm text-slate-500">
                        この条件のCandidateはありません。
                    </div>
                @endforelse
            </div>

            <div class="mt-5">{{ $candidates->links() }}</div>
        </section>

        <section class="page-card overflow-hidden">
            <div class="border-b border-slate-800 p-5">
                <p class="text-xs font-bold uppercase tracking-[.14em] text-slate-500">RECENT DEMAND</p>
                <h2 class="mt-1 text-xl font-black text-slate-50">最近の演習需要</h2>
            </div>
            <div class="divide-y divide-slate-800/80">
                @forelse ($demands as $demand)
                    <article class="p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="badge badge-slate">{{ $demand->assembly_mode }}</span>
                                    @if ($demand->exam_profile_key)<span class="badge badge-slate">{{ $demand->exam_profile_key }}</span>@endif
                                </div>
                                <h3 class="mt-2 font-bold text-slate-200">{{ data_get($demand->metadata, 'plan_title', 'Plan') }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ data_get($demand->metadata, 'task_title', 'Task') }}</p>
                            </div>
                            <p class="text-xs text-slate-500">{{ $demand->created_at?->format('Y-m-d H:i') }}</p>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
                            <span class="badge badge-slate">要求 {{ $demand->requested_count }}</span>
                            <span class="badge badge-green">Bank {{ $demand->bank_selected_count }}</span>
                            <span class="badge badge-yellow">不足 {{ $demand->generated_requested_count }}</span>
                            <span class="badge badge-slate">生成 {{ $demand->generated_count }}</span>
                            @foreach (collect($demand->focus_topics ?? [])->take(6) as $topic)
                                <span class="badge badge-slate">{{ $topic }}</span>
                            @endforeach
                        </div>
                    </article>
                @empty
                    <div class="p-6 text-sm text-slate-500">まだ演習需要は記録されていません。</div>
                @endforelse
            </div>
            <div class="p-5">{{ $demands->links() }}</div>
        </section>
    </div>
@endsection
