@if (! empty($answers['memo']) && $step !== 'activity')
    <div class="assistant-message-row assistant-message-right">
        <div class="assistant-bubble assistant-bubble-user">
            <p class="assistant-speaker">あなた</p>
            <p class="mt-2 whitespace-pre-line leading-7">{{ $answers['memo'] }}</p>
        </div>
        <div class="assistant-avatar assistant-avatar-user">YOU</div>
    </div>
@endif

@if ($step === 'activity')
    <div class="assistant-message-row assistant-message-left">
        <div class="assistant-avatar">PK</div>
        <div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
            <p class="assistant-speaker">Pace Keeper サポーター</p>
            <h2 class="mt-2 text-xl font-bold text-slate-100">何を行い、何が分かりましたか？</h2>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                タスク名や当初の計画を気にせず、実際に行ったことを先に入力してください。
            </p>
            <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-4">
                @csrf
                <textarea name="memo" rows="6" class="form-control" required placeholder="例：Wave形式を試作したが現在のゲーム性には合わないと判断し、自由探索方式の検討を進めた。">{{ old('memo') }}</textarea>
                <button type="submit" class="btn-primary">次へ</button>
            </form>
        </div>
    </div>
@endif

@if ($step === 'worked_on')
    <div class="assistant-message-row assistant-message-left">
        <div class="assistant-avatar">PK</div>
        <div class="assistant-bubble assistant-bubble-support">
            <p class="assistant-speaker">Pace Keeper サポーター</p>
            <h2 class="mt-2 text-xl font-bold text-slate-100">いつ行いましたか？</h2>
            <div class="mt-4 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('chat.answer') }}">@csrf<input type="hidden" name="worked_on" value="{{ now()->toDateString() }}"><button class="chat-quick-button" type="submit">今日</button></form>
                <form method="POST" action="{{ route('chat.answer') }}">@csrf<input type="hidden" name="worked_on" value="{{ now()->subDay()->toDateString() }}"><button class="chat-quick-button" type="submit">昨日</button></form>
            </div>
            <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <label class="flex-1"><span class="form-label">その他の日付</span><input type="date" name="worked_on" class="form-control" value="{{ old('worked_on', now()->toDateString()) }}" required></label>
                <button type="submit" class="btn-secondary">この日付を選ぶ</button>
            </form>
        </div>
    </div>
@endif

@if ($step === 'minutes')
    <div class="assistant-message-row assistant-message-left">
        <div class="assistant-avatar">PK</div>
        <div class="assistant-bubble assistant-bubble-support">
            <p class="assistant-speaker">Pace Keeper サポーター</p>
            <h2 class="mt-2 text-xl font-bold text-slate-100">何分取り組みましたか？</h2>
            <form method="POST" action="{{ route('chat.answer') }}" class="mt-4">
                @csrf
                <div class="chat-option-row">
                    @foreach ([15, 30, 60, 90] as $minutes)
                        <label class="chat-option-pill"><input type="radio" name="minutes_choice" value="{{ $minutes }}" required><span>{{ $minutes }}分</span></label>
                    @endforeach
                    <label class="chat-option-pill"><input type="radio" name="minutes_choice" value="custom" required><span>その他</span></label>
                </div>
                <input type="number" name="custom_minutes" min="1" max="1440" class="form-control mt-4" placeholder="その他を選んだ場合の分数">
                <button type="submit" class="btn-primary mt-4">次へ</button>
            </form>
        </div>
    </div>
@endif

@if ($step === 'plan')
    <div class="assistant-message-row assistant-message-left">
        <div class="assistant-avatar">PK</div>
        <div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
            <p class="assistant-speaker">Pace Keeper サポーター</p>
            <h2 class="mt-2 text-xl font-bold text-slate-100">どの計画に関する報告ですか？</h2>
            @if ($ownedPlans->isEmpty())
                <p class="mt-3 text-slate-400">所有している計画がありません。先に計画を作成してください。</p>
            @else
                <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-3">
                    @csrf
                    @foreach ($ownedPlans as $plan)
                        <label class="chat-choice-card"><input type="radio" name="plan_id" value="{{ $plan->id }}" required><span><span class="block font-bold text-slate-100">{{ $plan->title }}</span><span class="mt-1 block text-sm text-slate-400">{{ $plan->category ?? 'カテゴリ未設定' }}・期限 {{ $plan->deadline?->format('Y-m-d') ?? '未設定' }}</span></span></label>
                    @endforeach
                    <button type="submit" class="btn-primary mt-2">この計画を選ぶ</button>
                </form>
            @endif
        </div>
    </div>
@endif

@if ($step === 'task' && $selectedPlan)
    <div class="assistant-message-row assistant-message-left">
        <div class="assistant-avatar">PK</div>
        <div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
            <p class="assistant-speaker">Pace Keeper サポーター</p>
            <h2 class="mt-2 text-xl font-bold text-slate-100">この成果をどこへ関連付けますか？</h2>
            <p class="mt-2 text-sm text-slate-400">既存タスクに合わなければ、計画全体の成果として記録できます。</p>
            <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-3">
                @csrf
                <label class="chat-choice-card"><input type="radio" name="task_id" value="none" required><span><span class="block font-bold text-slate-100">計画全体の成果</span><span class="mt-1 block text-sm text-slate-400">調査、試作、方向転換、計画外の作業など</span></span></label>
                @foreach ($selectedPlan->tasks->whereNotIn('status', ['done', 'cancelled']) as $task)
                    <label class="chat-choice-card"><input type="radio" name="task_id" value="{{ $task->id }}" required><span><span class="block font-bold text-slate-100">{{ $task->title }}</span><span class="mt-1 block text-sm text-slate-400">進捗 {{ $task->progress_percent }}%・想定 {{ $task->estimated_minutes }}分</span></span></label>
                @endforeach
                <button type="submit" class="btn-primary">次へ</button>
            </form>
        </div>
    </div>
@endif

@if ($step === 'result')
    <div class="assistant-message-row assistant-message-left">
        <div class="assistant-avatar">PK</div>
        <div class="assistant-bubble assistant-bubble-support">
            <p class="assistant-speaker">Pace Keeper サポーター</p>
            <h2 class="mt-2 text-xl font-bold text-slate-100">記録への反映方法を選んでください</h2>
            <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-4">
                @csrf
                @if ($selectedTask)
                    <label><span class="form-label">「{{ $selectedTask->title }}」の進捗増加率</span><input type="number" name="progress_delta_percent" min="0" max="100" class="form-control" value="{{ old('progress_delta_percent', 0) }}" required></label>
                @else
                    <input type="hidden" name="progress_delta_percent" value="0">
                    <div class="assistant-notice assistant-notice-info">計画全体の成果として記録するため、特定タスクの進捗率は変更しません。</div>
                @endif
                <label><span class="form-label">体感難易度</span><select name="difficulty" class="form-control" required><option value="easy">簡単</option><option value="normal" selected>普通</option><option value="hard">難しい</option></select></label>
                <button type="submit" class="btn-primary">確認へ</button>
            </form>
        </div>
    </div>
@endif
