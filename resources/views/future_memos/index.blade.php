@extends('layouts.app')

@section('title', '未来メモ | Canovia')

@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <header class="pk-v18-page-hero relative overflow-hidden">
        <div class="relative z-10 max-w-2xl">
            <p class="pk-v18-card-kicker">CANOVIA / FUTURE MEMO</p>
            <h1 class="mt-2 text-2xl font-black text-slate-50 sm:text-3xl">まだ計画になっていない未来を置いておく</h1>
            <p class="mt-3 text-sm leading-7 text-slate-400">やりたいこと、なりたい自分、気になっていることを曖昧なまま残して大丈夫。AIへ共有するメモは、計画づくりをあなた向けにする材料になります。</p>
        </div>
    </header>

    @if (session('success'))<div class="assistant-notice assistant-notice-success">{{ session('success') }}</div>@endif
    @if (session('status'))<div class="assistant-notice assistant-notice-info">{{ session('status') }}</div>@endif
    @if ($errors->any())
        <div class="assistant-notice assistant-notice-error">
            <p class="font-bold">入力内容を確認してください。</p>
            <ul class="mt-2 list-disc pl-5 text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('future_memos.organize') }}" class="btn-primary">✨ AIと方向を整理する</a>
        <a href="{{ route('plans.create') }}" class="btn-secondary">計画を作る</a>
    </div>

    <section class="info-card space-y-4">
        <div>
            <h2 class="text-lg font-black text-slate-100">未来メモを追加</h2>
            <p class="mt-1 text-xs leading-5 text-slate-400">1つだけでもOK。あとからいつでも書き換えられます。</p>
        </div>
        <form method="POST" action="{{ route('future_memos.store') }}" class="grid gap-4 sm:grid-cols-2">
            @csrf
            <label class="block text-sm font-bold text-slate-200">種類
                <select name="kind" class="form-control mt-2" required>
                    @foreach ($kinds as $value => $label)<option value="{{ $value }}" @selected(old('kind') === $value)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="block text-sm font-bold text-slate-200">カテゴリ
                <select name="category" class="form-control mt-2">
                    <option value="">未分類</option>
                    @foreach ($categories as $value => $label)<option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="block sm:col-span-2 text-sm font-bold text-slate-200">内容
                <textarea name="content" class="form-control mt-2 min-h-28" maxlength="2000" required placeholder="例：基本情報を取って、就活で自信を持てるようになりたい">{{ old('content') }}</textarea>
            </label>
            <label class="sm:col-span-2 inline-flex items-center gap-2 text-sm text-slate-300">
                <input type="hidden" name="use_for_ai" value="0"><input type="checkbox" name="use_for_ai" value="1" checked>
                計画作成・計画更新でAIへの参考情報として使う
            </label>
            <div class="sm:col-span-2"><button class="btn-primary" type="submit">未来メモに残す</button></div>
        </form>
    </section>

    <section class="space-y-3">
        <div class="flex items-end justify-between gap-3">
            <div><p class="pk-v18-card-kicker">YOUR CONTEXT</p><h2 class="mt-1 text-lg font-black text-slate-100">保存した未来メモ</h2></div>
            <span class="text-xs text-slate-500">{{ $memos->count() }}件</span>
        </div>
        @forelse ($memos as $memo)
            <details class="page-card p-4" @if($loop->first && $memos->count() <= 2) open @endif>
                <summary class="cursor-pointer list-none">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><p class="text-[10px] font-black tracking-[.16em] text-cyan-300">{{ $memo->kindLabel() }} · {{ $memo->categoryLabel() }}</p><p class="mt-1 whitespace-pre-line text-sm font-bold leading-6 text-slate-100">{{ $memo->content }}</p></div>
                        <span class="shrink-0 rounded-full border border-cyan-300/20 px-2 py-1 text-[10px] font-bold {{ $memo->use_for_ai ? 'text-cyan-200' : 'text-slate-500' }}">{{ $memo->use_for_ai ? 'AI共有 ON' : 'AI共有 OFF' }}</span>
                    </div>
                </summary>
                <div class="mt-4 border-t border-slate-800/70 pt-4">
                    <form method="POST" action="{{ route('future_memos.update', $memo) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf @method('PUT')
                        <select name="kind" class="form-control">@foreach ($kinds as $value => $label)<option value="{{ $value }}" @selected($memo->kind === $value)>{{ $label }}</option>@endforeach</select>
                        <select name="category" class="form-control"><option value="">未分類</option>@foreach ($categories as $value => $label)<option value="{{ $value }}" @selected($memo->category === $value)>{{ $label }}</option>@endforeach</select>
                        <textarea name="content" class="form-control min-h-24 sm:col-span-2" maxlength="2000" required>{{ $memo->content }}</textarea>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-300"><input type="hidden" name="use_for_ai" value="0"><input type="checkbox" name="use_for_ai" value="1" @checked($memo->use_for_ai)> AIへの参考情報として使う</label>
                        <div class="flex justify-end"><button class="btn-secondary" type="submit">更新</button></div>
                    </form>
                    <form method="POST" action="{{ route('future_memos.destroy', $memo) }}" class="mt-2 flex justify-end" onsubmit="return confirm('この未来メモを削除しますか？')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs font-bold text-rose-300 hover:text-rose-200">削除</button>
                    </form>
                </div>
            </details>
        @empty
            <div class="page-card border-dashed p-6 text-center"><p class="text-sm font-bold text-slate-200">まだ未来メモはありません</p><p class="mt-2 text-xs leading-6 text-slate-500">大きな目標でなくて大丈夫。「少し気になる」くらいから残してみてください。</p></div>
        @endforelse
    </section>
</div>
@endsection
