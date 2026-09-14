@extends('layouts.app')

@section('title', '公開計画一覧 | Pace Keeper')

@section('content')
    <section class="mb-8">
        <p class="mb-2 text-sm font-semibold text-slate-500">Public Plans</p>

        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            公開計画一覧
        </h1>

        <p class="mt-3 max-w-3xl leading-7 text-slate-600">
            他のユーザーが公開した計画を閲覧できます。
            公開計画は閲覧専用で、編集や作業ログの追加はできません。
        </p>
    </section>

    @if ($plans->isEmpty())
        <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <h2 class="text-xl font-bold text-slate-900">公開されている計画はまだありません</h2>
            <p class="mt-3 text-slate-600">公開設定された計画が作成されると、ここに表示されます。</p>
        </section>
    @else
        <section class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($plans as $plan)
                <article class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <h2 class="text-xl font-bold text-slate-900">
                            {{ $plan->title }}
                        </h2>

                        <span class="rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-200">
                            公開
                        </span>
                    </div>

                    <p class="text-sm leading-6 text-slate-600">
                        {{ $plan->description ?? '説明はまだ設定されていません。' }}
                    </p>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <p class="text-xs text-slate-500">カテゴリ</p>
                            <p class="font-semibold text-slate-900">{{ $plan->category ?? '未設定' }}</p>
                        </div>

                        <div class="rounded-lg bg-slate-50 p-3">
                            <p class="text-xs text-slate-500">期限</p>
                            <p class="font-semibold text-slate-900">{{ $plan->deadline?->format('Y-m-d') ?? '期限未設定' }}</p>
                        </div>
                    </div>

                    <a href="{{ route('public_plans.show', $plan->public_slug) }}"
                       class="btn-primary mt-5 text-sm">
                        詳細を見る
                    </a>
                </article>
            @endforeach
        </section>
    @endif
@endsection