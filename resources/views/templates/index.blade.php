@extends('layouts.app')

@section('title', 'テンプレート一覧 | Canovia')

@section('content')
    <section class="mb-8">
        <p class="mb-2 text-sm font-semibold text-slate-500">Templates</p>

        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            テンプレート一覧
        </h1>

        <p class="mt-3 max-w-3xl leading-7 text-slate-600">
            目的に合わせて、あらかじめ用意された計画テンプレートを選択できます。
            テンプレートを使用すると、自分の計画としてコピーされ、自由に編集できます。
        </p>
    </section>

    @if ($templates->isEmpty())
        <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <h2 class="text-xl font-bold text-slate-900">テンプレートはまだありません</h2>
            <p class="mt-3 text-slate-600">管理者がテンプレートを作成すると、ここに表示されます。</p>
        </section>
    @else
        <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($templates as $template)
                <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <h2 class="text-xl font-bold text-slate-900">
                            {{ $template->title }}
                        </h2>

                        <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">
                            {{ $template->category ?? '未設定' }}
                        </span>
                    </div>

                    <p class="line-clamp-3 text-sm leading-6 text-slate-600">
                        {{ $template->description ?? '説明はまだ設定されていません。' }}
                    </p>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <p class="text-xs text-slate-500">想定日数</p>
                            <p class="font-semibold text-slate-900">{{ $template->estimated_days }}日</p>
                        </div>

                        <div class="rounded-lg bg-slate-50 p-3">
                            <p class="text-xs text-slate-500">タスク数</p>
                            <p class="font-semibold text-slate-900">{{ $template->templateTasks->count() }}件</p>
                        </div>
                    </div>

                    <a href="{{ route('templates.show', $template) }}"
                        class="btn-primary mt-5 text-sm">
                        詳細を見る
                    </a>
                </article>
            @endforeach
        </section>
    @endif
@endsection