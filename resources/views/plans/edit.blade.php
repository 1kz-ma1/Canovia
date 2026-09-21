@extends('layouts.app')

@section('title', '計画編集 | Canovia')

@section('content')
    <section class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            計画を編集
        </h1>

        <p class="mt-3 max-w-3xl leading-7 text-slate-600">
            変えたいところだけ直せます。今の内容はそのまま入っています。
        </p>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <h2 class="mb-2 font-bold">入力内容を確認してください</h2>

                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('plans.update', $plan) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label for="title" class="mb-2 block text-sm font-medium text-slate-700">
                        計画タイトル
                    </label>

                    <input
                        id="title"
                        type="text"
                        name="title"
                        value="{{ old('title', $plan->title) }}"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                    >
                </div>

                <div>
                    <label for="description" class="mb-2 block text-sm font-medium text-slate-700">
                        説明
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        rows="5"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                    >{{ old('description', $plan->description) }}</textarea>
                </div>

                <div>
                    <label for="category" class="mb-2 block text-sm font-medium text-slate-700">
                        カテゴリ
                    </label>

                    <select
                        id="category"
                        name="category"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                    >
                        <option value="">選択してください</option>
                        <option value="資格学習" @selected(old('category', $plan->category) === '資格学習')>資格学習</option>
                        <option value="個人開発" @selected(old('category', $plan->category) === '個人開発')>個人開発</option>
                        <option value="制作活動" @selected(old('category', $plan->category) === '制作活動')>制作活動</option>
                        <option value="ゲーム開発" @selected(old('category', $plan->category) === 'ゲーム開発')>ゲーム開発</option>
                        <option value="その他" @selected(old('category', $plan->category) === 'その他')>その他</option>
                    </select>
                </div>

                <div>
                    <label for="priority" class="mb-2 block text-sm font-medium text-slate-700">計画優先度</label>
                    <select id="priority" name="priority" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200">
                        @for ($priority = 1; $priority <= 5; $priority++)
                            <option value="{{ $priority }}" @selected((int) old('priority', $plan->priority ?? 3) === $priority)>
                                {{ $priority }}{{ $priority === 1 ? '（最優先）' : ($priority === 5 ? '（低）' : '') }}
                            </option>
                        @endfor
                    </select>
                    <p class="mt-2 text-xs leading-5 text-slate-500">1が最優先です。HomeではPlan優先度をTask選定より先に使います。</p>
                </div>

                <section id="plan-design" class="scroll-mt-28">
                    <div class="mb-3">
                        <h2 class="text-lg font-bold text-slate-900">この計画の見た目</h2>
                    </div>
                    @include('plans.partials.visual-picker', ['visualPlan' => $plan])
                </section>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="start_date" class="mb-2 block text-sm font-medium text-slate-700">
                            開始日
                        </label>

                        <input
                            id="start_date"
                            type="date"
                            name="start_date"
                            value="{{ old('start_date', $plan->start_date?->format('Y-m-d')) }}"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>

                    <div>
                        <label for="deadline" class="mb-2 block text-sm font-medium text-slate-700">
                            期限
                        </label>

                        <input
                            id="deadline"
                            type="date"
                            name="deadline"
                            value="{{ old('deadline', $plan->deadline?->format('Y-m-d')) }}"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>
                </div>

                @auth
                    <div class="rounded-xl border border-violet-400/20 bg-violet-500/5 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <span class="block font-medium text-slate-100">共同計画</span>
                                <span class="mt-1 block text-sm leading-6 text-slate-400">共有URL・参加コード・メンバー権限を管理します。</span>
                            </div>
                            <a href="{{ route('plans.collaboration.settings', $plan) }}" class="btn-secondary">{{ $plan->is_collaborative ? '共有設定を開く' : '共同計画を設定' }}</a>
                        </div>
                    </div>
                @endauth

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <input type="hidden" name="is_public" value="0">
                    <label class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            name="is_public"
                            value="1"
                            @checked(old('is_public', $plan->is_public))
                            class="mt-1"
                        >

                        <span>
                            <span class="block font-medium text-slate-900">
                                この計画を公開する
                            </span>

                            <span class="mt-1 block text-sm leading-6 text-slate-600">
                                公開すると、共有URLを知っている人がこの計画を見られます。
                                公開ページでは編集や作業ログの追加はできません。
                            </span>
                        </span>
                    </label>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        更新する
                    </button>

                    <a
                        href="{{ route('plans.show', $plan) }}"
                        class="btn-secondary"
                    >
                        戻る
                    </a>
                </div>
            </form>
        </div>

        <aside class="space-y-6">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-bold text-slate-900">
                    現在の状態
                </h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">公開設定</dt>
                        <dd class="font-medium text-slate-900">
                            {{ $plan->is_public ? '公開' : '非公開' }}
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">作成日</dt>
                        <dd class="font-medium text-slate-900">
                            {{ $plan->created_at->format('Y-m-d') }}
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">最終更新</dt>
                        <dd class="font-medium text-slate-900">
                            {{ $plan->updated_at->format('Y-m-d H:i') }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-red-200 bg-red-50 p-6">
                <h2 class="text-lg font-bold text-red-900">
                    危険な操作
                </h2>

                <p class="mt-3 text-sm leading-6 text-red-700">
                    計画を削除すると、紐づくタスクと作業ログも削除されます。
                    この操作は元に戻せません。
                </p>

                <form
                    action="{{ route('plans.destroy', $plan) }}"
                    method="POST"
                    class="mt-4"
                    onsubmit="return confirm('この計画を削除しますか？紐づくタスクと作業ログも削除されます。この操作は元に戻せません。');"
                >
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                    >
                        この計画を削除する
                    </button>
                </form>
            </div>
        </aside>
    </section>
@endsection