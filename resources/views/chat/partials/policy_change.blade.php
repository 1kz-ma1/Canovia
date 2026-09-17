@if ($step === 'plan')
    <div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">どの計画の方針が変わりましたか？</h2>
        <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-3">@csrf
            @foreach ($ownedPlans as $plan)
                <label class="chat-choice-card"><input type="radio" name="plan_id" value="{{ $plan->id }}" required><span><span class="block font-bold text-slate-100">{{ $plan->title }}</span><span class="mt-1 block text-sm text-slate-400">{{ $plan->description ? \Illuminate\Support\Str::limit($plan->description, 90) : '概要未設定' }}</span></span></label>
            @endforeach
            <button type="submit" class="btn-primary">この計画を選ぶ</button>
        </form>
    </div></div>
@endif

@if ($step === 'change_type')
    <div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">どのような変更ですか？</h2>
        <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-3">@csrf
            @foreach ($changeTypeLabels as $value => $label)
                <label class="chat-choice-card"><input type="radio" name="change_type" value="{{ $value }}" required><span class="font-bold text-slate-100">{{ $label }}</span></label>
            @endforeach
            <button type="submit" class="btn-primary">次へ</button>
        </form>
    </div></div>
@endif

@if ($step === 'change_summary')
    <div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">変更前と変更後を教えてください</h2>
        <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-4">@csrf
            <label><span class="form-label">変更前の方針（分かる範囲で）</span><textarea name="before_state" rows="4" class="form-control" placeholder="例：敵がWaveごとに出現する形式で作る予定だった">{{ old('before_state') }}</textarea></label>
            <label><span class="form-label">これから採用したい方針</span><textarea name="after_state" rows="5" class="form-control" required placeholder="例：自由探索形式に変更し、状態変化と現象の相互作用を中心にする">{{ old('after_state') }}</textarea></label>
            <button type="submit" class="btn-primary">次へ</button>
        </form>
    </div></div>
@endif

@if ($step === 'reason')
    <div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">なぜ変更すると判断しましたか？</h2>
        <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-4">@csrf
            <label><span class="form-label">変更理由</span><textarea name="reason" rows="5" class="form-control" required placeholder="実際に試して感じた問題や、目的とのずれを入力してください。">{{ old('reason') }}</textarea></label>
            <label><span class="form-label">判断材料・検証結果（任意）</span><textarea name="evidence" rows="4" class="form-control" placeholder="例：試作品では自由探索の方が現象の組合せを試しやすかった">{{ old('evidence') }}</textarea></label>
            <button type="submit" class="btn-primary">次へ</button>
        </form>
    </div></div>
@endif

@if ($step === 'impact')
    <div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">どこまで影響しそうですか？</h2>
        <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-4">@csrf
            <label><span class="form-label">影響を受けそうなタスク・機能・範囲（任意）</span><textarea name="affected_scope" rows="4" class="form-control" placeholder="不明な場合は空欄でも構いません。">{{ old('affected_scope') }}</textarea></label>
            <fieldset><legend class="form-label">期限への影響</legend><div class="chat-option-row mt-2"><label class="chat-option-pill"><input type="radio" name="deadline_effect" value="none" required><span>影響なし</span></label><label class="chat-option-pill"><input type="radio" name="deadline_effect" value="review" required><span>AIに判断してほしい</span></label><label class="chat-option-pill"><input type="radio" name="deadline_effect" value="change" required><span>変更が必要</span></label></div></fieldset>
            <fieldset><legend class="form-label">完成条件も変わりますか？</legend><div class="chat-option-row mt-2"><label class="chat-option-pill"><input type="radio" name="completion_condition_changed" value="0" required><span>変わらない</span></label><label class="chat-option-pill"><input type="radio" name="completion_condition_changed" value="1" required><span>変わる</span></label></div></fieldset>
            <button type="submit" class="btn-primary">次へ</button>
        </form>
    </div></div>
@endif

@if ($step === 'constraints')
    <div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">最後に、変更したくない条件はありますか？</h2>
        <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-4">@csrf
            <label><span class="form-label">維持したい条件（任意）</span><textarea name="must_keep" rows="4" class="form-control" placeholder="例：Steamで無料版を公開するゴールと11月末の目標は維持する">{{ old('must_keep') }}</textarea></label>
            <label><span class="form-label">AIに特に判断してほしいこと（任意）</span><textarea name="ai_request" rows="4" class="form-control" placeholder="例：不要タスクを中止し、新しい構成に合わせてタスクを作り直してほしい">{{ old('ai_request') }}</textarea></label>
            <button type="submit" class="btn-primary">外部AI用プロンプトを生成</button>
        </form>
    </div></div>
@endif

@if ($step === 'prompt' && $selectedPlan)
    <div class="assistant-message-row assistant-message-left">
        <div class="assistant-avatar">CV</div>
        <div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
            <p class="assistant-speaker">Canovia サポーター</p>
            <h2 class="mt-2 text-xl font-bold text-slate-100">方針変更用プロンプトを生成しました</h2>
            <p class="mt-2 leading-7 text-slate-300">
                外部AIは必要に応じて追加質問を行い、最後にCanoviaへ反映できるJSONを返します。
            </p>

            <textarea
                id="policyChangePrompt"
                class="form-control mt-4 min-h-[420px] font-mono text-xs"
                readonly
            >{{ $answers['prompt'] }}</textarea>

            <div class="mt-4 flex flex-wrap gap-3">
                <button
                    type="button"
                    class="btn-primary"
                    onclick="copyPolicyChangePrompt('policyChangePrompt')"
                >
                    プロンプトをコピー
                </button>

                <a href="{{ route('plans.review_assistant.show', $selectedPlan) }}" class="btn-secondary">
                    専用画面で確認する
                </a>
            </div>

            <div class="assistant-notice assistant-notice-warning mt-4">
                AIの提案は自動反映されません。JSONを読み込んだ後、変更前後を確認し、必要な項目だけ承認します。
            </div>
        </div>
    </div>

    <div class="assistant-message-row assistant-message-right">
        <div class="assistant-bubble assistant-bubble-user assistant-form-bubble">
            <p class="assistant-speaker">あなた</p>
            <h2 class="mt-2 text-lg font-bold text-slate-100">外部AIから返された最終JSONを貼り付ける</h2>
            <p class="mt-2 text-sm leading-6 text-slate-300">
                AIとの追加質問が終わり、最終的なJSONが返されたら、回答全体またはJSON部分をそのまま貼り付けてください。
            </p>

            <form
                method="POST"
                action="{{ route('plans.review_assistant.preview', $selectedPlan) }}"
                class="mt-4 space-y-4"
            >
                @csrf

                <textarea
                    name="operations_json"
                    rows="16"
                    class="form-control font-mono text-xs"
                    required
                    placeholder='{"schema_version":"1.1","action":"restructure_plan","target_plan":{"id":{{ $selectedPlan->id }},"title":"計画名","category":"カテゴリ"},"summary":"方針変更に合わせて計画を再編","operations":[{"type":"update_plan","description":"変更後の最新方針","reason":"方針変更を反映するため"},{"type":"update_task","task_id":31,"progress_percent":65,"status":"doing","estimated_minutes":1200,"priority":1,"reason":"既存成果を再評価したため"}]}'
                >{{ old('operations_json') }}</textarea>

                <button type="submit" class="btn-primary w-full md:w-auto">
                    JSONを読み込んで変更内容を確認
                </button>
            </form>
        </div>
        <div class="assistant-avatar assistant-avatar-user">YOU</div>
    </div>

    <script>
        async function copyPolicyChangePrompt(elementId) {
            const element = document.getElementById(elementId);

            if (! element) {
                alert('コピー対象が見つかりません。');
                return;
            }

            const text = element.value;

            try {
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    await navigator.clipboard.writeText(text);
                    alert('プロンプトをコピーしました。');
                    return;
                }

                throw new Error('Clipboard API is unavailable.');
            } catch (error) {
                const fallback = document.createElement('textarea');
                fallback.value = text;
                fallback.setAttribute('readonly', '');
                fallback.style.position = 'fixed';
                fallback.style.opacity = '0';
                fallback.style.pointerEvents = 'none';

                document.body.appendChild(fallback);
                fallback.select();
                fallback.setSelectionRange(0, fallback.value.length);

                let copied = false;

                try {
                    copied = document.execCommand('copy');
                } catch (fallbackError) {
                    copied = false;
                }

                document.body.removeChild(fallback);

                if (copied) {
                    alert('プロンプトをコピーしました。');
                    return;
                }

                element.focus();
                element.select();
                element.setSelectionRange(0, element.value.length);
                alert('自動コピーに失敗しました。選択された内容を手動でコピーしてください。');
            }
        }
    </script>
@endif
