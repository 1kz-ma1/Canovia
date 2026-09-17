<div class="assistant-message-row assistant-message-left">
    <div class="assistant-avatar">CV</div>
    <div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p>
        <h2 class="mt-2 text-xl font-bold text-slate-100">この内容で登録しますか？</h2>
        <dl class="chat-confirm-grid mt-4">
            @if ($flow === 'work_log')
                <div><dt>計画</dt><dd>{{ $selectedPlan?->title ?? '未選択' }}</dd></div>
                <div><dt>関連先</dt><dd>{{ $selectedTask?->title ?? '計画全体' }}</dd></div>
                <div><dt>作業日</dt><dd>{{ $answers['worked_on'] }}</dd></div>
                <div><dt>作業時間</dt><dd>{{ $answers['actual_minutes'] }}分</dd></div>
                <div class="md:col-span-2"><dt>報告内容</dt><dd class="whitespace-pre-line">{{ $answers['memo'] }}</dd></div>
                <div><dt>進捗増加</dt><dd>{{ $answers['progress_delta_percent'] ?? 0 }}%</dd></div>
                <div><dt>難易度</dt><dd>{{ $answers['difficulty'] }}</dd></div>
            @elseif ($flow === 'task')
                <div><dt>計画</dt><dd>{{ $selectedPlan?->title }}</dd></div><div><dt>タスク</dt><dd>{{ $answers['title'] }}</dd></div><div class="md:col-span-2"><dt>達成条件</dt><dd>{{ $answers['description'] ?: '未設定' }}</dd></div><div><dt>想定時間</dt><dd>{{ $answers['estimated_minutes'] }}分</dd></div><div><dt>優先度</dt><dd>{{ $answers['priority'] }}</dd></div><div><dt>開始ハードル</dt><dd>{{ $answers['activation_cost'] ?? 3 }}/5</dd></div>
            @elseif ($flow === 'plan')
                <div><dt>計画名</dt><dd>{{ $answers['title'] }}</dd></div><div><dt>カテゴリ</dt><dd>{{ $answers['category'] ?: '未設定' }}</dd></div><div class="md:col-span-2"><dt>概要</dt><dd>{{ $answers['description'] ?: '未設定' }}</dd></div><div><dt>期間</dt><dd>{{ $answers['start_date'] }} ～ {{ $answers['deadline'] }}</dd></div><div><dt>公開設定</dt><dd>{{ $answers['is_public'] ? '公開' : '非公開' }}</dd></div>
            @endif
        </dl>
        <form method="POST" action="{{ route('chat.confirm') }}" class="mt-5">@csrf<button type="submit" class="btn-primary">登録する</button></form>
    </div>
</div>
