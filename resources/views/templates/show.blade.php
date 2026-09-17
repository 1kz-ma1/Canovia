@extends('layouts.app')

@section('title', $planTemplate->title . ' | Canovia')

@section('content')
    <section class="mb-8">
        <p class="mb-2 text-sm font-semibold text-slate-500">Template Detail</p>

        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            {{ $planTemplate->title }}
        </h1>

        <p class="mt-3 max-w-3xl leading-7 text-slate-600">
            {{ $planTemplate->description ?? '説明はまだ設定されていません。' }}
        </p>

        <div class="mt-4 flex flex-wrap gap-2">
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600">
                {{ $planTemplate->category ?? '未設定' }}
            </span>

            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium text-slate-600">
                想定日数：{{ $planTemplate->estimated_days }}日
            </span>
        </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1fr_420px]">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 text-2xl font-bold text-slate-900">含まれるタスク</h2>

            @if ($planTemplate->templateTasks->isEmpty())
                <p class="rounded-xl border border-dashed border-slate-300 p-5 text-slate-600">
                    このテンプレートにはタスクが登録されていません。
                </p>
            @else
                <div class="space-y-4">
                    @foreach ($planTemplate->templateTasks->sortBy('sort_order') as $task)
                        <article class="rounded-xl border border-slate-200 p-4">
                            <h3 class="font-bold text-slate-900">{{ $task->title }}</h3>

                            <p class="mt-2 text-sm leading-6 text-slate-600">
                                {{ $task->description ?? '説明なし' }}
                            </p>

                            <p class="mt-3 inline-flex rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                                想定時間：{{ round($task->estimated_minutes / 60, 1) }}時間
                                （{{ $task->estimated_minutes }}分）
                            </p>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>

        <aside class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="text-2xl font-bold text-slate-900">
                このテンプレートから計画を作成する
            </h2>

            <p class="mt-3 text-sm leading-6 text-slate-600">
                テンプレートを使用すると、内容があなたの計画としてコピーされます。
                コピー後は通常の計画として編集できます。元のテンプレートは変更されません。
            </p>

            @if ($errors->any())
                <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('templates.use', $planTemplate) }}" method="POST" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="title" class="mb-1 block text-sm font-medium text-slate-700">作成する計画名</label>
                    <input id="title" type="text" name="title" value="{{ old('title', $planTemplate->title) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200">
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label for="start_date" class="mb-1 block text-sm font-medium text-slate-700">開始日</label>
                        <input id="start_date" type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200">
                    </div>

                    <div>
                        <label for="deadline" class="mb-1 block text-sm font-medium text-slate-700">期限</label>
                        <input id="deadline" type="date" name="deadline" value="{{ old('deadline') }}" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200">
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="is_public" value="1" @checked(old('is_public')) class="mt-1">

                        <span>
                            <span class="block font-medium text-slate-900">作成した計画を公開する</span>
                            <span class="mt-1 block text-sm leading-6 text-slate-600">
                                公開すると、共有URLを知っている人が閲覧できます。
                            </span>
                        </span>
                    </label>
                </div>

                <button type="submit" class="btn-primary w-full">
                    このテンプレートで計画を作成する
                </button>

                <a href="{{ route('templates.index') }}"
                    class="block text-center text-sm font-medium text-slate-700 hover:underline">
                    テンプレート一覧へ戻る
                </a>
            </form>
        </aside>
    </section>
@endsection