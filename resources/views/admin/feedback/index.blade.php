@extends('layouts.app')

@section('title', 'Feedback Dashboard | Canovia')

@section('content')
    <div class="space-y-6">
        <header>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-sky-300">Feedback Dashboard</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-50">ユーザーの声</h1>
            <p class="mt-2 text-sm text-slate-400">総合評価と、改善に使える具体的なフィードバックを同じ場所で確認します。</p>
        </header>

        <section class="grid gap-4 md:grid-cols-[0.8fr_1.2fr]">
            <article class="page-card p-5">
                <p class="text-xs font-black uppercase tracking-[0.14em] text-slate-400">Overall Rating</p>
                @php
                    $roundedAverage = $averageRating !== null ? (int) round($averageRating) : 0;
                @endphp
                <div class="mt-3 flex items-end gap-3">
                    <strong class="text-4xl font-black text-slate-50">{{ $averageRating !== null ? number_format($averageRating, 2) : '—' }}</strong>
                    <span class="pb-1 text-amber-300" aria-label="5点満点">{{ str_repeat('★', $roundedAverage) }}{{ str_repeat('☆', 5 - $roundedAverage) }}</span>
                </div>
                <p class="mt-2 text-sm text-slate-400">評価 {{ $ratedCount }}件 / 未対応 {{ $newCount }}件</p>
            </article>

            <article class="page-card p-5">
                <p class="text-sm font-bold text-slate-200">評価分布</p>
                <div class="mt-4 space-y-2">
                    @foreach ($distribution as $rating => $count)
                        @php
                            $percent = $ratedCount > 0 ? round(($count / $ratedCount) * 100) : 0;
                        @endphp
                        <div class="feedback-distribution-row">
                            <span>{{ $rating }}★</span>
                            <div class="feedback-distribution-track"><span style="width: {{ $percent }}%"></span></div>
                            <strong>{{ $count }}</strong>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>

        <section class="page-card p-4 sm:p-5">
            <div class="mb-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.feedback.index', array_filter(['rating' => request('rating'), 'type' => request('type'), 'status' => request('status')])) }}"
                   class="{{ ! $showArchived ? 'btn-primary' : 'btn-secondary' }} px-4 py-2 text-sm">通常</a>
                <a href="{{ route('admin.feedback.index', array_filter(['archived' => '1', 'rating' => request('rating'), 'type' => request('type'), 'status' => request('status')])) }}"
                   class="{{ $showArchived ? 'btn-primary' : 'btn-secondary' }} px-4 py-2 text-sm">アーカイブ済み</a>
            </div>
            <form method="GET" action="{{ route('admin.feedback.index') }}" class="grid gap-3 sm:grid-cols-4">
                @if ($showArchived)
                    <input type="hidden" name="archived" value="1">
                @endif
                <select name="rating" class="form-control">
                    <option value="">すべての評価</option>
                    @foreach (range(5, 1) as $rating)
                        <option value="{{ $rating }}" @selected((string) request('rating') === (string) $rating)>{{ $rating }}★</option>
                    @endforeach
                </select>
                <select name="type" class="form-control">
                    <option value="">すべての種類</option>
                    <option value="bug" @selected(request('type') === 'bug')>不具合</option>
                    <option value="request" @selected(request('type') === 'request')>要望</option>
                    <option value="usability" @selected(request('type') === 'usability')>使いづらい</option>
                    <option value="positive" @selected(request('type') === 'positive')>良かった</option>
                </select>
                <select name="status" class="form-control">
                    <option value="">すべての状態</option>
                    <option value="new" @selected(request('status') === 'new')>未対応</option>
                    <option value="reviewing" @selected(request('status') === 'reviewing')>確認中</option>
                    <option value="resolved" @selected(request('status') === 'resolved')>対応済み</option>
                </select>
                <button type="submit" class="btn-secondary">絞り込む</button>
            </form>
        </section>

        <section class="space-y-3">
            @forelse ($feedbacks as $feedback)
                @php
                    $typeLabel = match ($feedback->type) {
                        'bug' => '不具合',
                        'request' => '要望',
                        'positive' => '良かった',
                        default => '使いづらい',
                    };
                    $statusLabel = match ($feedback->status) {
                        'resolved' => '対応済み',
                        'reviewing' => '確認中',
                        default => '未対応',
                    };
                @endphp
                <article class="page-card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($feedback->rating)
                                    <span class="feedback-admin-rating" aria-label="{{ $feedback->rating }}点">{{ str_repeat('★', $feedback->rating) }}{{ str_repeat('☆', 5 - $feedback->rating) }}</span>
                                @else
                                    <span class="text-xs text-slate-500">評価なし</span>
                                @endif
                                <span class="badge badge-slate">{{ $typeLabel }}</span>
                                <span class="badge badge-slate">{{ $statusLabel }}</span>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">{{ $feedback->created_at?->format('Y/m/d H:i') }} ・ {{ $feedback->app_version ?: 'version不明' }}@if($feedback->archived_at) ・ アーカイブ: {{ $feedback->archived_at->format('Y/m/d H:i') }}@endif</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.feedback.status', $feedback) }}" class="flex gap-2">
                                @csrf
                                @method('PATCH')
                                <select name="status" class="form-control min-w-28 py-2 text-xs">
                                    <option value="new" @selected($feedback->status === 'new')>未対応</option>
                                    <option value="reviewing" @selected($feedback->status === 'reviewing')>確認中</option>
                                    <option value="resolved" @selected($feedback->status === 'resolved')>対応済み</option>
                                </select>
                                <button class="btn-secondary px-3 py-2 text-xs" type="submit">更新</button>
                            </form>

                            @if ($feedback->archived_at)
                                <form method="POST" action="{{ route('admin.feedback.restore', $feedback) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn-secondary px-3 py-2 text-xs" type="submit">復元</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.feedback.archive', $feedback) }}" onsubmit="return confirm('このフィードバックをアーカイブしますか？分析対象から除外されます。')">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn-secondary px-3 py-2 text-xs" type="submit">アーカイブ</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <p class="mt-4 whitespace-pre-wrap text-sm leading-7 text-slate-200">{{ $feedback->message }}</p>
                    <div class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                        @if ($feedback->page)<span>Page: {{ $feedback->page }}</span>@endif
                        @if ($feedback->plan)<span>Plan: {{ $feedback->plan->title }}</span>@endif
                        @if ($feedback->task)<span>Task: {{ $feedback->task->title }}</span>@endif
                        @if ($feedback->user)<span>User: {{ $feedback->user->email }}</span>@else<span>Guest</span>@endif
                    </div>
                </article>
            @empty
                <section class="empty-state page-card p-8 text-center">
                    <p class="font-bold text-slate-100">条件に合うフィードバックはありません。</p>
                </section>
            @endforelse
        </section>

        {{ $feedbacks->links() }}
    </div>
@endsection
