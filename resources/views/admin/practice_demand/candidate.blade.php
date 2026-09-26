@extends('layouts.app')

@section('title', 'Question Candidate | Canovia Admin')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        @include('admin.partials.nav')

        <header class="rounded-[1.6rem] border border-cyan-300/15 bg-slate-950/55 p-5 sm:p-7">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[.18em] text-cyan-300">QUESTION CANDIDATE #{{ $candidate->id }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-black text-slate-50">AI生成問題をレビュー</h1>
                        <span class="badge {{ $candidate->status === 'pending' ? 'badge-yellow' : ($candidate->status === 'promoted' ? 'badge-green' : 'badge-slate') }}">{{ $candidate->status }}</span>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-slate-400">
                        生成結果は候補にすぎません。問題文・選択肢・正答・解説・分類を確認し、利用価値があるものだけDraft Packへ昇格してください。
                    </p>
                </div>
                <a href="{{ route('admin.practice_demand.index') }}" class="btn-secondary shrink-0">需要一覧へ戻る</a>
            </div>
        </header>

        <section class="page-card p-5 sm:p-6">
            <div class="flex flex-wrap gap-2">
                @if ($candidate->exam_profile_key)<span class="badge badge-slate">{{ $candidate->exam_profile_key }}</span>@endif
                <span class="badge badge-slate">{{ $candidate->provider }}</span>
                <span class="badge badge-slate">生成 {{ $candidate->generation_count }}回</span>
                @if ($candidate->model)<span class="badge badge-slate">{{ $candidate->model }}</span>@endif
            </div>

            <h2 class="mt-5 text-lg font-black text-slate-50">問題文</h2>
            <div class="mt-3 rounded-2xl border border-slate-800 bg-slate-950/45 p-4 text-sm leading-7 text-slate-200">
                {{ data_get($candidate->question_payload, 'prompt') }}
            </div>

            <div class="mt-5 grid gap-3">
                @foreach (data_get($candidate->question_payload, 'response_fields', []) as $field)
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/30 p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="text-sm text-slate-200">{{ data_get($field, 'label', '回答') }}</strong>
                            <span class="badge badge-slate">{{ data_get($field, 'type', 'unknown') }}</span>
                            <span class="text-[11px] text-slate-500">id: {{ data_get($field, 'id') }}</span>
                        </div>
                        @if (collect(data_get($field, 'choices', []))->isNotEmpty())
                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach (data_get($field, 'choices', []) as $choice)
                                    <div class="rounded-xl border border-slate-800 bg-slate-900/45 px-3 py-2 text-xs text-slate-300">
                                        <strong>{{ data_get($choice, 'id') }}</strong> · {{ data_get($choice, 'label') }}
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="page-card p-5 sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[.14em] text-violet-300">REVIEW HINTS</p>
                <h2 class="mt-1 text-lg font-black text-slate-50">生成時の文脈</h2>
                <dl class="mt-4 space-y-3 text-xs">
                    <div>
                        <dt class="text-slate-500">Strategy</dt>
                        <dd class="mt-1 font-semibold text-slate-300">{{ data_get($candidate->review_hints, 'strategy_key', '—') }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Focus topics</dt>
                        <dd class="mt-2 flex flex-wrap gap-2">
                            @forelse (data_get($candidate->review_hints, 'focus_topics', []) as $topic)
                                <span class="badge badge-slate">{{ $topic }}</span>
                            @empty
                                <span class="text-slate-500">なし</span>
                            @endforelse
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">最終検出</dt>
                        <dd class="mt-1 text-slate-300">{{ $candidate->last_seen_at?->format('Y-m-d H:i') ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="page-card p-5 sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[.14em] text-cyan-300">PROVENANCE</p>
                <h2 class="mt-1 text-lg font-black text-slate-50">資産化の状態</h2>
                <dl class="mt-4 space-y-3 text-xs">
                    <div>
                        <dt class="text-slate-500">Fingerprint</dt>
                        <dd class="mt-1 break-all font-mono text-slate-400">{{ $candidate->fingerprint }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Latest demand</dt>
                        <dd class="mt-1 text-slate-300">#{{ $candidate->latest_practice_question_demand_id ?: '—' }}</dd>
                    </div>
                    @if ($candidate->promotedPack)
                        <div>
                            <dt class="text-slate-500">昇格先</dt>
                            <dd class="mt-1 font-semibold text-emerald-200">
                                {{ $candidate->promotedPack->title }}
                                @if ($candidate->promotedQuestion) · {{ $candidate->promotedQuestion->external_key }} @endif
                            </dd>
                        </div>
                    @endif
                    @if (data_get($candidate->review_data, 'review_note'))
                        <div>
                            <dt class="text-slate-500">Review note</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-slate-300">{{ data_get($candidate->review_data, 'review_note') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </section>

        @if ($candidate->status === \App\Models\PracticeQuestionCandidate::STATUS_PENDING)
            <section class="page-card p-5 sm:p-6">
                <p class="text-xs font-bold uppercase tracking-[.14em] text-emerald-300">PROMOTE TO DRAFT</p>
                <h2 class="mt-1 text-xl font-black text-slate-50">確認済み問題としてDraft Packへ追加</h2>
                <p class="mt-2 text-xs leading-6 text-slate-500">
                    AI生成時点では正答情報をブラウザへ持たせていないため、grading_ruleはここで人が確認して入力します。
                    learning_metadataの初期値は生成時のfocusを補助情報として入れているだけなので、実際の問題内容に合わせて修正してください。
                </p>

                @if ($draftPacks->isEmpty())
                    <div class="mt-5 rounded-2xl border border-amber-300/15 bg-amber-300/[0.04] p-4 text-sm text-amber-100/80">
                        昇格先のDraft Packがありません。先にQuestion Pack管理でDraftを作成してください。
                    </div>
                @else
                    <form method="POST" action="{{ route('admin.practice_demand.candidates.promote', $candidate) }}" class="mt-5 space-y-4">
                        @csrf

                        <label class="block text-xs font-bold text-slate-400">
                            昇格先Draft Pack
                            <select name="question_pack_id" class="form-control mt-2" required>
                                @foreach ($draftPacks as $pack)
                                    <option value="{{ $pack->id }}" @selected((string) old('question_pack_id') === (string) $pack->id)>
                                        {{ $pack->title }} · {{ $pack->slug }} · {{ $pack->questions_count }}問
                                    </option>
                                @endforeach
                            </select>
                            @error('question_pack_id')<span class="mt-2 block text-sm text-rose-300">{{ $message }}</span>@enderror
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block text-xs font-bold text-slate-400">
                                external_key
                                <input name="external_key" value="{{ old('external_key', 'ai-candidate-'.$candidate->id) }}" class="form-control mt-2">
                                @error('external_key')<span class="mt-2 block text-sm text-rose-300">{{ $message }}</span>@enderror
                            </label>
                            <label class="block text-xs font-bold text-slate-400">
                                難易度 1〜5
                                <input type="number" min="1" max="5" name="difficulty" value="{{ old('difficulty', 3) }}" class="form-control mt-2" required>
                                @error('difficulty')<span class="mt-2 block text-sm text-rose-300">{{ $message }}</span>@enderror
                            </label>
                        </div>

                        <label class="block text-xs font-bold text-slate-400">
                            grading_rule JSON
                            <textarea name="grading_rule_json" class="form-control mt-2 min-h-44 font-mono text-xs" spellcheck="false">{{ old('grading_rule_json', $gradingRuleTemplate) }}</textarea>
                            @error('grading_rule_json')<span class="mt-2 block text-sm text-rose-300">{{ $message }}</span>@enderror
                        </label>

                        <label class="block text-xs font-bold text-slate-400">
                            learning_metadata JSON
                            <textarea name="learning_metadata_json" class="form-control mt-2 min-h-48 font-mono text-xs" spellcheck="false">{{ old('learning_metadata_json', $learningMetadataTemplate) }}</textarea>
                            @error('learning_metadata_json')<span class="mt-2 block text-sm text-rose-300">{{ $message }}</span>@enderror
                        </label>

                        <label class="block text-xs font-bold text-slate-400">
                            解説
                            <textarea name="explanation" class="form-control mt-2 min-h-32" placeholder="正答の理由、誤答しやすいポイント、復習時に必要な説明">{{ old('explanation') }}</textarea>
                            @error('explanation')<span class="mt-2 block text-sm text-rose-300">{{ $message }}</span>@enderror
                        </label>

                        <label class="block text-xs font-bold text-slate-400">
                            source_reference
                            <input name="source_reference" value="{{ old('source_reference', 'Canovia Native AI candidate #'.$candidate->id) }}" class="form-control mt-2">
                            @error('source_reference')<span class="mt-2 block text-sm text-rose-300">{{ $message }}</span>@enderror
                        </label>

                        <label class="block text-xs font-bold text-slate-400">
                            Review note
                            <textarea name="review_note" class="form-control mt-2 min-h-24" placeholder="確認した点や修正理由を任意で記録">{{ old('review_note') }}</textarea>
                            @error('review_note')<span class="mt-2 block text-sm text-rose-300">{{ $message }}</span>@enderror
                        </label>

                        @error('candidate')<p class="text-sm font-semibold text-rose-300">{{ $message }}</p>@enderror

                        <button type="submit" class="btn-primary">確認済みとしてDraftへ昇格</button>
                    </form>
                @endif
            </section>

            <section class="rounded-2xl border border-rose-300/15 bg-rose-300/[0.03] p-5 sm:p-6">
                <h2 class="font-black text-rose-100">今回は採用しない</h2>
                <form method="POST" action="{{ route('admin.practice_demand.candidates.reject', $candidate) }}" class="mt-4">
                    @csrf
                    <textarea name="review_note" class="form-control min-h-24" placeholder="見送る理由を記録" required>{{ old('review_note') }}</textarea>
                    <button type="submit" class="btn-secondary mt-3">Candidateを見送る</button>
                </form>
            </section>
        @elseif ($candidate->status === \App\Models\PracticeQuestionCandidate::STATUS_REJECTED)
            <section class="page-card p-5 sm:p-6">
                <h2 class="font-black text-slate-100">見送り中のCandidate</h2>
                <p class="mt-2 text-sm text-slate-500">再評価したい場合だけ確認待ちへ戻します。</p>
                <form method="POST" action="{{ route('admin.practice_demand.candidates.reopen', $candidate) }}" class="mt-4">
                    @csrf
                    <button type="submit" class="btn-secondary">確認待ちへ戻す</button>
                </form>
            </section>
        @endif
    </div>
@endsection
