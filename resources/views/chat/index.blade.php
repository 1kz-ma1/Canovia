@extends('layouts.app')

@section('title', '計画・実績 | Canovia')

@section('content')
    @php
        $flow = $draft['flow'] ?? null;
        $step = $draft['step'] ?? null;
        $answers = $draft['answers'] ?? [];
        $flowTitles = [
            'work_log' => 'タスク・作業の進捗報告',
            'policy_change' => '方針変更の報告',
            'ai_context' => '普段使うAIへ計画を共有',
            'task' => 'タスク追加',
            'plan' => '計画作成',
            'review' => '計画を更新',
            'status' => '現在の状況確認',
        ];
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">
        <header class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="text-sm font-semibold text-sky-400">Plan operations</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-100 font-heading">Canovia サポーター</h1>
                <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-300">
                    進捗報告と方針変更を分けて考える必要はありません。実際に起きたことをそのまま報告し、
                    必要な実績記録・進捗更新・計画変更をまとめて反映します。
                </p>
            </div>

            @if ($draft)
                <form method="POST" action="{{ route('chat.reset') }}">
                    @csrf
                    <button type="submit" class="btn-secondary">最初からやり直す</button>
                </form>
            @endif
        </header>

        @if (session('success'))
            <div class="assistant-notice assistant-notice-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="assistant-notice assistant-notice-error">
                <p class="font-bold">入力内容を確認してください。</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <main class="assistant-chat-shell chat-main-shell">
            @if (! $draft)
                @include('chat.partials.menu')
            @else
                <div class="assistant-message-row assistant-message-left">
                    <div class="assistant-avatar">CV</div>
                    <div class="assistant-bubble assistant-bubble-support">
                        <p class="assistant-speaker">Canovia サポーター</p>
                        <p class="mt-2 text-sm text-slate-400">現在の手続き</p>
                        <h2 class="mt-1 text-xl font-bold text-slate-100">{{ $flowTitles[$flow] ?? 'チャット操作' }}</h2>
                    </div>
                </div>

                @if ($flow === 'work_log')
                    @include('chat.partials.progress_report')
                @elseif ($flow === 'policy_change')
                    @include('chat.partials.policy_change')
                @elseif ($flow === 'ai_context')
                    @include('chat.partials.ai_context')
                @elseif ($flow === 'task')
                    @include('chat.partials.task')
                @elseif ($flow === 'plan')
                    @include('chat.partials.plan')
                @elseif ($flow === 'review')
                    @include('chat.partials.review')
                @elseif ($flow === 'status')
                    @include('chat.partials.status')
                @endif

                @if ($step === 'confirm')
                    @include('chat.partials.confirm')
                @endif
            @endif
        </main>
    </div>
@endsection
