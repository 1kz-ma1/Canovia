@extends('layouts.app')

@section('title', '面接振り返り | '.$application->company_name.' | Canovia')

@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    <header class="page-card p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-fuchsia-300">INTERVIEW REVIEW</p>
                <h1 class="mt-1 text-xl font-black text-slate-100">{{ $application->company_name }}</h1>
                <p class="mt-1 text-sm text-slate-400">{{ $application->role_title ?: '職種未設定' }} · {{ $event->stageLabel() }} · {{ $event->scheduled_at?->format('Y/m/d H:i') }}</p>
            </div>
            <a href="{{ route('plans.career.index', $plan) }}" class="btn-secondary px-3 py-2 text-xs">Careerへ戻る</a>
        </div>

        <div class="mt-4 rounded-2xl border border-fuchsia-300/15 bg-fuchsia-300/[0.035] p-4">
            <p class="text-sm font-black text-fuchsia-100">合否が出る前の感覚を残す</p>
            <p class="mt-1 text-xs leading-5 text-slate-400">
                面接を試験で終わらせず、次へ使える経験にします。結果がまだ分からない段階では、落ちた理由などを断定せず「起きたこと」と「自分の仮説」を分けて残します。
            </p>
        </div>
    </header>

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-300/20 bg-emerald-300/[0.05] px-4 py-3 text-sm text-emerald-100">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-300/20 bg-rose-300/[0.05] px-4 py-3 text-sm text-rose-100">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('plans.career.interview_reviews.store', [$plan, $event]) }}" class="page-card p-5">
        @csrf

        <div class="space-y-5">
            @foreach ($questions as $index => $question)
                <div>
                    <div class="flex items-start gap-3">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full border border-fuchsia-300/20 bg-fuchsia-300/[0.04] text-[11px] font-black text-fuchsia-200">{{ $index + 1 }}</span>
                        <label for="review-{{ $question['key'] }}" class="pt-1 text-sm font-bold leading-6 text-slate-200">{{ $question['prompt'] }}</label>
                    </div>
                    <textarea
                        id="review-{{ $question['key'] }}"
                        name="answers[{{ $question['key'] }}]"
                        rows="3"
                        @disabled(! $canEdit)
                        class="mt-2 w-full rounded-2xl border border-slate-700 bg-slate-950/45 px-3 py-3 text-sm leading-6 text-slate-100"
                        placeholder="短くてもOK"
                    >{{ old('answers.'.$question['key'], $answers->get($question['key'])) }}</textarea>
                </div>
            @endforeach
        </div>

        @if ($canEdit)
            <div class="mt-6 flex flex-wrap gap-2 border-t border-white/8 pt-4">
                @if ($review?->status !== 'completed')
                    <button type="submit" name="action" value="save" class="btn-secondary px-4 py-2 text-xs">途中保存</button>
                @endif
                <button type="submit" name="action" value="complete" class="btn-primary px-4 py-2 text-xs">
                    {{ $review?->status === 'completed' ? '振り返りを更新' : '振り返りを完了' }}
                </button>
            </div>
        @endif
    </form>

    @if ($review?->status === 'completed')
        <section class="page-card p-5">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-300">NEXT INTERVIEW</p>
            <h2 class="mt-1 text-base font-black text-slate-100">次へ持っていくこと</h2>
            <p class="mt-3 text-sm leading-6 text-slate-300">{{ $review->insights['next_focus'] ?? '次回の改善点はまだ未設定です。' }}</p>
            <p class="mt-2 text-[11px] text-slate-500">この企業は現在「結果待ち」として扱われます。</p>
        </section>
    @endif
</div>
@endsection
