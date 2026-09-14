@extends('layouts.app')

@section('title', 'タスク編集 | Pace Keeper')

@section('content')
    @php
        $taskProgressColorClass = match ($task->status) {
            'done' => 'progress-green',
            'doing' => 'progress-blue',
            'todo' => 'progress-slate',
            default => 'progress-slate',
        };

        $taskStatusLabel = match ($task->status) {
            'todo' => '未着手',
            'doing' => '進行中',
            'done' => '完了',
            default => $task->status,
        };

        $taskStatusClass = match ($task->status) {
            'done' => 'status-green',
            'doing' => 'status-blue',
            'todo' => 'status-slate',
            default => 'status-slate',
        };
    @endphp
    <section class="mb-8">
        <p class="mb-2 text-sm font-semibold text-slate-500">Edit Task</p>

        <h1 class="text-3xl font-bold tracking-tight text-slate-900">
            タスク編集
        </h1>

        <p class="mt-3 max-w-3xl leading-7 text-slate-600">
            タスクの想定作業時間・進捗率・状態を調整できます。
            変更内容は計画全体の進捗計算にも反映されます。
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

            <form action="{{ route('tasks.update', $task) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label for="title" class="mb-2 block text-sm font-medium text-slate-700">
                        タスク名
                    </label>

                    <input
                        id="title"
                        type="text"
                        name="title"
                        value="{{ old('title', $task->title) }}"
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
                    >{{ old('description', $task->description) }}</textarea>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="remaining_minutes" class="mb-2 block text-sm font-medium text-slate-700">残り作業時間（分）</label>
                        <input id="remaining_minutes" type="number" name="remaining_minutes" value="{{ old('remaining_minutes', $task->remaining_minutes ?? 0) }}" min="0" required class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200">
                    </div>
                    <div>
                        <label for="estimated_minutes" class="mb-2 block text-sm font-medium text-slate-700">
                            想定作業時間（分）
                        </label>

                        <input
                            id="estimated_minutes"
                            type="number"
                            name="estimated_minutes"
                            value="{{ old('estimated_minutes', $task->estimated_minutes) }}"
                            min="0"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>

                    <div>
                        <label for="progress_percent" class="mb-2 block text-sm font-medium text-slate-700">
                            進捗率（%）
                        </label>

                        <input
                            id="progress_percent"
                            type="number"
                            name="progress_percent"
                            value="{{ old('progress_percent', $task->progress_percent) }}"
                            min="0"
                            max="100"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="status" class="mb-2 block text-sm font-medium text-slate-700">
                            状態
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                        >
                            <option value="todo" @selected(old('status', $task->status) === 'todo')>未着手</option>
                            <option value="doing" @selected(old('status', $task->status) === 'doing')>進行中</option>
                            <option value="done" @selected(old('status', $task->status) === 'done')>完了</option>
                            <option value="cancelled" @selected(old('status', $task->status) === 'cancelled')>中止</option>
                        </select>
                    </div>

                    <div>
                        <label for="priority" class="mb-2 block text-sm font-medium text-slate-700">
                            優先度
                        </label>

                        <select
                            id="priority"
                            name="priority"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200"
                        >
                            <option value="1" @selected(old('priority', $task->priority) == 1)>1：高</option>
                            <option value="2" @selected(old('priority', $task->priority) == 2)>2：やや高</option>
                            <option value="3" @selected(old('priority', $task->priority) == 3)>3：普通</option>
                            <option value="4" @selected(old('priority', $task->priority) == 4)>4：やや低</option>
                            <option value="5" @selected(old('priority', $task->priority) == 5)>5：低</option>
                        </select>
                    </div>

                    <div>
                        <label for="activation_cost" class="mb-2 block text-sm font-medium text-slate-700">開始ハードル</label>
                        <select id="activation_cost" name="activation_cost" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200">
                            @foreach ([1 => '1：すぐ始められる', 2 => '2：始めやすい', 3 => '3：普通', 4 => '4：準備が必要', 5 => '5：腰を据える必要あり'] as $value => $label)
                                <option value="{{ $value }}" @selected((int) old('activation_cost', $task->activation_cost ?? 3) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-slate-500">難易度ではなく「着手までの重さ」です。今日のおすすめに利用します。</p>
                    </div>

                    <div class="md:col-span-2">
                        <label for="next_action_note" class="mb-2 block text-sm font-medium text-slate-700">次回ここから（任意）</label>
                        <textarea id="next_action_note" name="next_action_note" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200">{{ old('next_action_note', $task->next_action_note) }}</textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label for="depends_on_task_id" class="mb-2 block text-sm font-medium text-slate-700">前提Task</label>
                        <select id="depends_on_task_id" name="depends_on_task_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900 focus:ring-2 focus:ring-slate-200">
                            <option value="">なし</option>
                            @foreach ($task->plan->tasks->where('id', '!=', $task->id) as $candidate)
                                <option value="{{ $candidate->id }}" @selected((int) old('depends_on_task_id', $task->depends_on_task_id) === $candidate->id)>{{ $candidate->title }}</option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-slate-500">前提Taskが完了するまで、このTaskは「今日のおすすめ」から外れます。</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        更新する
                    </button>

                    <a
                        href="{{ route('plans.show', $task->plan) }}"
                        class="btn-secondary"
                    >
                        計画詳細へ戻る
                    </a>
                </div>
            </form>
        </div>

        <aside class="space-y-6">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-bold text-slate-900">
                    所属計画
                </h2>

                <p class="mt-3 text-sm text-slate-500">
                    このタスクは以下の計画に紐づいています。
                </p>

                <a
                    href="{{ route('plans.show', $task->plan) }}"
                    class="mt-4 block rounded-xl bg-slate-50 p-4 hover:bg-slate-100"
                >
                    <span class="block font-bold text-slate-900">
                        {{ $task->plan->title }}
                    </span>

                    <span class="mt-1 block text-sm text-slate-500">
                        {{ $task->plan->start_date }} 〜 {{ $task->plan->deadline?->format('Y-m-d') ?? '期限未設定' }}
                    </span>
                </a>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-lg font-bold text-slate-900">
                    現在のタスク状態
                </h2>

                <div class="mt-4">
                    <div class="mb-1 flex justify-between text-sm">
                        <span class="text-slate-600">進捗率</span>
                        <span class="font-semibold text-slate-900">{{ $task->progress_percent }}%</span>
                    </div>

                    <div class="progress-track">
                        <div
                            class="progress-bar {{ $taskProgressColorClass }}"
                            style="width: {{ min(100, max(0, $task->progress_percent)) }}%;"
                        ></div>
                    </div>
                </div>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">想定時間</dt>
                        <dd class="font-medium text-slate-900">
                            {{ round($task->estimated_minutes / 60, 1) }}時間
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">状態</dt>
                        <dd class="font-medium text-slate-900">
                            <span class="status-pill {{ $taskStatusClass }}">{{ $taskStatusLabel }}</span>
                        </dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">優先度</dt>
                        <dd class="font-medium text-slate-900">
                            {{ $task->priority }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-red-200 bg-red-50 p-6">
                <h2 class="text-lg font-bold text-red-900">
                    危険な操作
                </h2>

                <p class="mt-3 text-sm leading-6 text-red-700">
                    タスクを削除しても、過去の作業実績はタスク名の記録とともにTimelineへ残ります。
                    タスク自体の削除は元に戻せません。
                </p>

                <form
                    action="{{ route('tasks.destroy', $task) }}"
                    method="POST"
                    class="mt-4"
                    onsubmit="return confirm('このタスクを削除しますか？過去の作業実績はTimelineへ残ります。');"
                >
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="btn-danger text-sm"
                    >
                        このタスクを削除する
                    </button>
                </form>
            </div>
        </aside>
    </section>
@endsection
