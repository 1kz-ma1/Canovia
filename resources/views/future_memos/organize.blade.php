@extends('layouts.app')

@section('title', 'AIと方向を整理 | Canovia')

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <header>
        <p class="pk-v18-card-kicker">CANOVIA / DISCOVERY</p>
        <h1 class="mt-2 text-2xl font-black text-slate-50 sm:text-3xl">次に進みたい方向をAIと整理する</h1>
        <p class="mt-2 max-w-3xl text-sm leading-7 text-slate-400">未来メモを材料に、普段使っているAIへ相談します。AIは必要なら最大5問だけ質問し、最後に選べる目標候補を返します。</p>
    </header>

    @if (session('status'))<div class="assistant-notice assistant-notice-info">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="assistant-notice assistant-notice-error"><strong>読み込みを確認してください。</strong><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="info-card space-y-4">
        <div><h2 class="text-lg font-black text-slate-100">1. 今回だけ追加で伝えること</h2><p class="mt-1 text-xs leading-5 text-slate-400">空欄でもOK。未来メモは自動で相談文へ入ります。</p></div>
        <form method="POST" action="{{ route('future_memos.organize.prompt') }}" class="space-y-3">@csrf
            <textarea name="extra_context" class="form-control min-h-28" maxlength="5000" placeholder="例：就活が近いので、3か月以内に始められるものを優先したい">{{ old('extra_context', $extraContext) }}</textarea>
            <button class="btn-secondary" type="submit">相談文を更新</button>
        </form>
    </section>

    <section class="info-card space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-lg font-black text-slate-100">2. AIへ相談</h2><p class="mt-1 text-xs leading-5 text-slate-400">未来メモのうち「AI共有 ON」の内容だけが含まれます。</p></div><a class="text-xs font-bold text-cyan-300" href="{{ route('future_memos.index') }}">未来メモを編集</a></div>
        <textarea id="futureMemoPrompt" class="form-control min-h-[360px] font-mono text-sm" readonly>{{ $prompt }}</textarea>
        <div class="flex items-center gap-3"><button type="button" class="btn-primary" data-copy-future-prompt>相談用の文章をコピー</button><span class="text-xs font-bold text-cyan-300" data-copy-future-status></span></div>
    </section>

    <section class="info-card space-y-4">
        <div><h2 class="text-lg font-black text-slate-100">3. AIの最後のJSONを戻す</h2><p class="mt-1 text-xs leading-5 text-slate-400">質問への回答を終えたあと、AIが返した最後の回答を貼り付けます。</p></div>
        <form method="POST" action="{{ route('future_memos.organize.preview') }}" class="space-y-3">@csrf
            <textarea name="ai_json" class="form-control min-h-[260px] font-mono text-sm" placeholder="AIの最後の回答を貼り付け">{{ old('ai_json') }}</textarea>
            <button class="btn-primary" type="submit">候補を読み込む</button>
        </form>
    </section>

    @if (is_array($proposal) && !empty($proposal['goal_candidates']))
        <section class="space-y-3">
            <div><p class="pk-v18-card-kicker">AI PROPOSAL</p><h2 class="mt-1 text-xl font-black text-slate-100">あなたが選べる方向</h2>@if(!empty($proposal['summary']))<p class="mt-2 text-sm leading-6 text-slate-400">{{ $proposal['summary'] }}</p>@endif</div>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($proposal['goal_candidates'] as $candidate)
                    <article class="page-card p-4">
                        <p class="text-[10px] font-black tracking-[.16em] text-cyan-300">{{ $candidate['category'] ?: '候補' }}</p>
                        <h3 class="mt-2 text-base font-black text-slate-100">{{ $candidate['title'] }}</h3>
                        @if($candidate['reason'])<p class="mt-2 text-xs leading-6 text-slate-400">{{ $candidate['reason'] }}</p>@endif
                        <dl class="mt-3 grid gap-2 text-xs"><div><dt class="font-bold text-slate-500">期間目安</dt><dd class="mt-1 text-slate-200">{{ $candidate['timeframe'] ?: '未定' }}</dd></div><div><dt class="font-bold text-slate-500">最初の一歩</dt><dd class="mt-1 text-slate-200">{{ $candidate['first_step'] ?: '計画作成時に整理' }}</dd></div></dl>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('future_memos.candidate.memo') }}">@csrf @foreach($candidate as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<button type="submit" class="btn-secondary">未来メモに残す</button></form>
                            <form method="POST" action="{{ route('future_memos.candidate.plan') }}">@csrf @foreach($candidate as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<button type="submit" class="btn-primary">この目標で計画を作る</button></form>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>
<script>
(() => {
    const button = document.querySelector('[data-copy-future-prompt]');
    const textarea = document.getElementById('futureMemoPrompt');
    const status = document.querySelector('[data-copy-future-status]');
    button?.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(textarea.value); status.textContent = 'コピーしました'; }
        catch (_) { textarea.focus(); textarea.select(); document.execCommand?.('copy'); status.textContent = '選択しました。コピーしてください'; }
    });
})();
</script>
@endsection
