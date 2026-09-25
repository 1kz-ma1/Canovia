@extends('layouts.app')

@section('title', 'Career | '.$plan->title.' | Canovia')

@section('content')
<div class="mx-auto max-w-6xl space-y-5">
    <header class="page-card p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-fuchsia-300">CAREER WORKSPACE</p>
                <h1 class="mt-1 text-xl font-black text-slate-100 sm:text-2xl">{{ $plan->title }}</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-400">
                    応募管理のために入力するのではなく、求人や応募画面をそのまま残して、選考の現在地へつなげます。
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('home', ['plan' => $plan->id]) }}" class="btn-secondary px-3 py-2 text-xs">Home</a>
                <a href="{{ route('plans.show', $plan) }}" class="btn-secondary px-3 py-2 text-xs">Plan詳細</a>
            </div>
        </div>
    </header>

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-300/20 bg-emerald-300/[0.05] px-4 py-3 text-sm text-emerald-100">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-300/20 bg-rose-300/[0.05] px-4 py-3 text-sm text-rose-100">
            {{ $errors->first() }}
        </div>
    @endif

    <section class="page-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-fuchsia-300">CAPTURE INBOX</p>
                <h2 class="mt-1 text-lg font-black text-slate-100">まずは投げるだけ</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">スクショかURLだけ保存できます。企業名や選考状況を毎回入力する必要はありません。スクショは非公開で保存します。</p>
            </div>
            <span class="badge badge-slate">未整理 {{ $captures->where('status', 'pending')->count() }}件</span>
        </div>

        @if ($canEdit)
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                <form method="POST" action="{{ route('plans.career.captures.store', $plan) }}" enctype="multipart/form-data" class="rounded-2xl border border-white/8 bg-white/[0.025] p-4">
                    @csrf
                    <input type="hidden" name="source_type" value="screenshot">
                    <p class="text-sm font-black text-slate-100">スクリーンショット</p>
                    <p class="mt-1 text-[11px] leading-5 text-slate-500">求人、応募完了、面接案内など。将来AI解析へそのまま渡せる形で保存します。</p>
                    <input type="file" name="screenshot" accept="image/jpeg,image/png,image/webp" required class="mt-3 block w-full text-xs text-slate-300">
                    <textarea name="note" rows="2" class="mt-3 w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-slate-100" placeholder="任意メモ。空欄でもOK"></textarea>
                    <button type="submit" class="btn-primary mt-3 px-3 py-2 text-xs">スクショを追加</button>
                </form>

                <form method="POST" action="{{ route('plans.career.captures.store', $plan) }}" class="rounded-2xl border border-white/8 bg-white/[0.025] p-4">
                    @csrf
                    <input type="hidden" name="source_type" value="url">
                    <p class="text-sm font-black text-slate-100">求人・採用ページURL</p>
                    <p class="mt-1 text-[11px] leading-5 text-slate-500">URLだけ先に残しておけば、あとからCompany/Applicationへ接続できます。</p>
                    <input type="url" name="source_url" required class="mt-3 w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-slate-100" placeholder="https://...">
                    <textarea name="note" rows="2" class="mt-3 w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-slate-100" placeholder="任意メモ。空欄でもOK"></textarea>
                    <button type="submit" class="btn-primary mt-3 px-3 py-2 text-xs">URLを追加</button>
                </form>
            </div>
        @endif

        <div class="mt-5 space-y-3">
            @forelse ($captures as $capture)
                <article class="rounded-2xl border {{ $capture->status === 'pending' ? 'border-fuchsia-300/15 bg-fuchsia-300/[0.025]' : 'border-white/8 bg-white/[0.02]' }} p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge badge-slate">{{ $capture->sourceLabel() }}</span>
                                <span class="text-[10px] text-slate-500">{{ $capture->captured_at?->diffForHumans() }}</span>
                                @if ($capture->status === 'linked')
                                    <span class="text-[10px] font-bold text-emerald-300">応募先へ接続済み</span>
                                @elseif ($capture->status === 'pending')
                                    <span class="text-[10px] font-bold text-fuchsia-300">未整理</span>
                                @endif
                            </div>

                            @if ($capture->screenshot_mime)
                                <a href="{{ route('plans.career.captures.screenshot', [$plan, $capture]) }}" target="_blank" class="mt-3 block max-w-md overflow-hidden rounded-xl border border-white/8 bg-slate-950/40">
                                    <img src="{{ route('plans.career.captures.screenshot', [$plan, $capture]) }}" alt="Career capture" class="max-h-56 w-full object-contain">
                                </a>
                            @endif

                            @if ($capture->source_url)
                                <a href="{{ $capture->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 block break-all text-sm font-bold text-sky-300">{{ $capture->source_url }}</a>
                            @endif

                            @if ($capture->raw_text)
                                <p class="mt-2 text-sm leading-6 text-slate-300">{{ $capture->raw_text }}</p>
                            @endif

                            @if ($capture->application)
                                <p class="mt-2 text-xs text-slate-400">→ {{ $capture->application->company_name }}{{ $capture->application->role_title ? ' / '.$capture->application->role_title : '' }}</p>
                            @endif
                        </div>

                        @if ($canEdit)
                            <form method="POST" action="{{ route('plans.career.captures.destroy', [$plan, $capture]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-slate-500 hover:text-rose-300">削除</button>
                            </form>
                        @endif
                    </div>

                    @if ($canEdit && $capture->status === 'pending' && $applications->isNotEmpty())
                        <form method="POST" action="{{ route('plans.career.captures.link', [$plan, $capture]) }}" class="mt-3 flex flex-wrap gap-2">
                            @csrf
                            <select name="career_application_id" class="min-w-0 flex-1 rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-xs text-slate-100">
                                @foreach ($applications as $application)
                                    <option value="{{ $application->id }}">{{ $application->company_name }}{{ $application->role_title ? ' / '.$application->role_title : '' }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn-secondary px-3 py-2 text-xs">既存の応募先へ接続</button>
                        </form>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-700 p-5 text-center text-sm text-slate-500">まだCaptureはありません。求人を見つけたらスクショかURLを投げるところから始められます。</div>
            @endforelse
        </div>
    </section>

    <section class="page-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-sky-300">APPLICATIONS</p>
                <h2 class="mt-1 text-lg font-black text-slate-100">応募・選考</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">将来はメール・スクショ解析から自動生成する領域です。現時点は必要なときだけ最小入力で補えます。</p>
            </div>
            <span class="badge badge-slate">{{ $applications->count() }}件</span>
        </div>

        <div class="mt-4 space-y-3">
            @forelse ($applications as $application)
                <article class="rounded-2xl border border-white/8 bg-white/[0.025] p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-black text-slate-100">{{ $application->company_name }}</h3>
                                <span class="badge badge-slate">{{ $application->stageLabel() }}</span>
                            </div>
                            @if ($application->role_title)
                                <p class="mt-1 text-sm text-slate-400">{{ $application->role_title }}</p>
                            @endif
                        </div>
                        @if ($application->company_website)
                            <a href="{{ $application->company_website }}" target="_blank" rel="noopener noreferrer" class="text-xs font-bold text-sky-300">企業ページ ↗</a>
                        @endif
                    </div>

                    @if ($application->selectionEvents->isNotEmpty())
                        <div class="mt-4 space-y-2">
                            @foreach ($application->selectionEvents as $event)
                                <div class="rounded-xl border border-white/8 bg-slate-950/25 px-3 py-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-sm font-bold text-slate-200">{{ $event->stageLabel() }}</p>
                                        <span class="text-[11px] text-slate-500">{{ $event->scheduled_at?->format('Y/m/d H:i') ?? '日時未設定' }}</span>
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-500">状態: {{ $event->status === 'result_waiting' ? '結果待ち' : ($event->status === 'scheduled' ? '予定' : $event->status) }}</p>
                                    @if ($event->type === 'interview' && (($event->scheduled_at && $event->scheduled_at->lte(now())) || $event->interviewReview))
                                        <a href="{{ route('plans.career.interview_reviews.show', [$plan, $event]) }}" class="mt-2 inline-block text-xs font-bold text-fuchsia-300">
                                            {{ $event->interviewReview?->status === 'completed' ? '振り返りを見る →' : '面接を振り返る →' }}
                                        </a>
                                    @endif

                                    @if ($canEdit && $event->status === 'result_waiting')
                                        <form method="POST" action="{{ route('plans.career.events.result', [$plan, $event]) }}" class="mt-3 flex flex-wrap gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="result" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950/60 px-2 py-2 text-xs text-slate-100">
                                                <option value="passed">通過</option>
                                                <option value="rejected">不通過</option>
                                                <option value="offer">内定・オファー</option>
                                                <option value="withdrawn">辞退</option>
                                            </select>
                                            <button type="submit" class="btn-secondary px-3 py-2 text-xs">結果を反映</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($canEdit)
                        <div class="mt-4 grid gap-3 lg:grid-cols-2">
                            <form method="POST" action="{{ route('plans.career.applications.update', [$plan, $application]) }}" class="rounded-xl border border-white/8 bg-slate-950/20 p-3">
                                @csrf
                                @method('PATCH')
                                <p class="text-xs font-bold text-slate-300">現在地を補正</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <select name="stage" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950/60 px-2 py-2 text-xs text-slate-100">
                                        @foreach (['candidate'=>'候補','preparing'=>'応募準備','applied'=>'応募済み','screening'=>'書類選考','interview'=>'面接中','final_interview'=>'最終面接','offer'=>'内定・オファー','closed'=>'終了'] as $value => $label)
                                            <option value="{{ $value }}" @selected($application->stage === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <select name="status" class="rounded-lg border border-slate-700 bg-slate-950/60 px-2 py-2 text-xs text-slate-100">
                                        @foreach (['active'=>'進行中','waiting'=>'結果待ち','completed'=>'完了','withdrawn'=>'辞退'] as $value => $label)
                                            <option value="{{ $value }}" @selected($application->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="result" value="{{ $application->result }}">
                                    <button type="submit" class="btn-secondary px-3 py-2 text-xs">更新</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('plans.career.events.store', [$plan, $application]) }}" class="rounded-xl border border-white/8 bg-slate-950/20 p-3">
                                @csrf
                                <p class="text-xs font-bold text-slate-300">面接予定を追加</p>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <select name="stage" class="rounded-lg border border-slate-700 bg-slate-950/60 px-2 py-2 text-xs text-slate-100">
                                        <option value="interview">面接</option>
                                        <option value="final_interview">最終面接</option>
                                    </select>
                                    <input type="datetime-local" name="scheduled_at" required class="rounded-lg border border-slate-700 bg-slate-950/60 px-2 py-2 text-xs text-slate-100">
                                </div>
                                <select name="task_id" class="mt-2 w-full rounded-lg border border-slate-700 bg-slate-950/60 px-2 py-2 text-xs text-slate-100">
                                    <option value="">Taskは自動で照合</option>
                                    @foreach ($plan->tasks->filter(fn ($task) => ! in_array($task->status, ['done','cancelled'], true)) as $task)
                                        <option value="{{ $task->id }}">{{ $task->title }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn-secondary mt-2 px-3 py-2 text-xs">予定を保存</button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-700 p-5 text-center text-sm text-slate-500">まだ応募先データはありません。まずCaptureだけ残しておいて大丈夫です。</div>
            @endforelse
        </div>

        @if ($canEdit)
            <details class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/20 p-4">
                <summary class="cursor-pointer text-sm font-bold text-slate-300">自動取得できないときだけ、応募先を手動で補う</summary>
                <form method="POST" action="{{ route('plans.career.applications.store', $plan) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <input name="company_name" required class="rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-slate-100" placeholder="企業名">
                    <input name="role_title" class="rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-slate-100" placeholder="職種（任意）">
                    <input type="url" name="company_website" class="rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-slate-100" placeholder="企業URL（任意）">
                    <select name="stage" class="rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-slate-100">
                        <option value="candidate">候補</option>
                        <option value="preparing">応募準備</option>
                        <option value="applied">応募済み</option>
                        <option value="screening">書類選考</option>
                        <option value="interview">面接中</option>
                        <option value="final_interview">最終面接</option>
                        <option value="offer">内定・オファー</option>
                    </select>
                    @if ($captures->where('status', 'pending')->isNotEmpty())
                        <select name="capture_id" class="rounded-xl border border-slate-700 bg-slate-950/50 px-3 py-2 text-sm text-slate-100 sm:col-span-2">
                            <option value="">Captureとは後で接続</option>
                            @foreach ($captures->where('status', 'pending') as $capture)
                                <option value="{{ $capture->id }}">#{{ $capture->id }} {{ $capture->sourceLabel() }} · {{ $capture->captured_at?->format('m/d H:i') }}</option>
                            @endforeach
                        </select>
                    @endif
                    <div class="sm:col-span-2">
                        <button type="submit" class="btn-primary px-3 py-2 text-xs">応募先を追加</button>
                    </div>
                </form>
            </details>
        @endif
    </section>
</div>
@endsection
