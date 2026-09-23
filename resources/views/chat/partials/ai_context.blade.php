@if ($step === 'plan')
    <div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">どの計画をAIへ共有しますか？</h2>
        <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-3">@csrf
            @foreach ($ownedPlans as $plan)
                <label class="chat-choice-card"><input type="radio" name="plan_id" value="{{ $plan->id }}" required><span><span class="block font-bold text-slate-100">{{ $plan->title }}</span><span class="mt-1 block text-sm text-slate-400">最終共有：{{ $plan->last_ai_context_exported_at?->format('Y-m-d H:i') ?? '未共有' }}</span></span></label>
            @endforeach
            <button type="submit" class="btn-primary">この計画を選ぶ</button>
        </form>
    </div></div>
@endif

@if ($step === 'context_mode' && $selectedPlan)
    <div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">どの範囲を共有しますか？</h2>
        <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-3">@csrf
            <label class="chat-choice-card"><input type="radio" name="context_mode" value="full" required><span><span class="block font-bold text-slate-100">計画全体を共有</span><span class="mt-1 block text-sm text-slate-400">新しいAIチャットを始める場合や、認識をリセットしたい場合。</span></span></label>
            <label class="chat-choice-card"><input type="radio" name="context_mode" value="diff" required><span><span class="block font-bold text-slate-100">前回共有後の差分だけ共有</span><span class="mt-1 block text-sm text-slate-400">既に相談中のAIチャットへ、変更点だけ追加します。未共有の場合は全体版になります。</span></span></label>
            <button type="submit" class="btn-primary">次へ</button>
        </form>
    </div></div>
@endif

@if ($step === 'purpose')
    <div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">何について相談しますか？</h2>
        <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-3">@csrf
            @foreach ($contextPurposeLabels as $value => $label)
                <label class="chat-choice-card"><input type="radio" name="purpose" value="{{ $value }}" required><span class="font-bold text-slate-100">{{ $label }}</span></label>
            @endforeach
            <button type="submit" class="btn-primary">次へ</button>
        </form>
    </div></div>
@endif

@if ($step === 'question')
    <div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">AIへ聞きたいことを追加しますか？</h2>
        <p class="mt-2 text-sm text-slate-400">空欄の場合は、現状整理と次に考えるべきことを依頼します。</p>
        <form method="POST" action="{{ route('chat.answer') }}" class="mt-4 space-y-4">@csrf<textarea name="question" rows="5" class="form-control" placeholder="例：今のスコープで11月末までに無料版を公開できそう？優先して削るべき機能も教えて。">{{ old('question') }}</textarea><button type="submit" class="btn-primary">共有用プロンプトを生成</button></form>
    </div></div>
@endif

@if ($step === 'prompt' && $selectedPlan)
    <div class="assistant-message-row assistant-message-left"><div class="assistant-avatar">CV</div><div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p><h2 class="mt-2 text-xl font-bold text-slate-100">AIへ共有するコンテキストを生成しました</h2>
        @if (($answers['context_mode'] ?? null) === 'diff' && ($answers['effective_context_mode'] ?? null) === 'full')
            <div class="assistant-notice assistant-notice-info mt-4">この計画はまだ共有履歴がないため、今回は計画全体のプロンプトを生成しました。</div>
        @endif
        <textarea id="aiContextPrompt" readonly tabindex="-1" aria-hidden="true" class="sr-only">{{ $answers['prompt'] }}</textarea>
        <div class="mt-4 rounded-2xl border border-cyan-300/15 bg-cyan-300/[0.035] p-4">
            <p class="text-sm font-semibold text-slate-100">共有コンテキストはCanovia側で整理済みです</p>
            <p class="mt-1 text-xs leading-5 text-slate-400">原文を読む必要はありません。用途に合わせてコピーしてください。</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" class="btn-primary" onclick="copyAndMarkContext(this)">コピーして共有済みにする</button>
                <button type="button" class="btn-secondary" onclick="copyChatPrompt('aiContextPrompt', this)">コピーのみ</button>
            </div>
            <details class="ai-handoff-details mt-4 rounded-xl border border-slate-800 bg-slate-950/35 p-3">
                <summary class="cursor-pointer text-xs font-semibold text-slate-300">この共有内容に含まれる情報</summary>
                <ul class="mt-3 space-y-2 text-xs leading-5 text-slate-500">
                    <li>・対象Planの目的、現在進捗、期限</li>
                    <li>・Task、最近の実績、方針変更</li>
                    <li>・選択した相談目的と質問</li>
                    <li>・差分共有の場合は前回共有後の変更点</li>
                </ul>
            </details>
            <p class="mt-3 text-xs leading-5 text-slate-400">共有済みにすると、次回はこの時点以降に追加・変更されたタスク、ログ、方針変更だけを抽出できます。</p>
        </div>
        <form id="markContextForm" method="POST" action="{{ route('chat.context_exported') }}" class="hidden">@csrf</form>
    </div></div>
    <script>
        (() => {
            function selectForManualCopy(element) {
                if (! element) {
                    return;
                }

                element.focus({ preventScroll: true });
                element.select();
                element.setSelectionRange(0, element.value.length);
            }

            function copyWithLegacyCommand(text) {
                const temporaryTextarea = document.createElement('textarea');
                temporaryTextarea.value = text;
                temporaryTextarea.setAttribute('readonly', '');
                temporaryTextarea.setAttribute('aria-hidden', 'true');
                temporaryTextarea.style.position = 'fixed';
                temporaryTextarea.style.top = '0';
                temporaryTextarea.style.left = '-9999px';
                temporaryTextarea.style.opacity = '0';
                temporaryTextarea.style.pointerEvents = 'none';

                document.body.appendChild(temporaryTextarea);
                temporaryTextarea.focus();
                temporaryTextarea.select();
                temporaryTextarea.setSelectionRange(0, temporaryTextarea.value.length);

                let copied = false;

                try {
                    copied = document.execCommand('copy');
                } catch (error) {
                    copied = false;
                } finally {
                    temporaryTextarea.remove();
                }

                return copied;
            }

            async function copyText(text, sourceElement) {
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    try {
                        await navigator.clipboard.writeText(text);
                        return true;
                    } catch (error) {
                        // 権限や安全なコンテキストの制約で失敗した場合は旧方式へ切り替える。
                    }
                }

                if (copyWithLegacyCommand(text)) {
                    return true;
                }

                selectForManualCopy(sourceElement);

                return false;
            }

            window.copyChatPrompt = async function (id, button = null) {
                const element = document.getElementById(id);

                if (! element) {
                    alert('コピー対象のプロンプトが見つかりません。');
                    return;
                }

                if (button) {
                    button.disabled = true;
                }

                try {
                    const copied = await copyText(element.value, element);

                    if (copied) {
                        alert('プロンプトをコピーしました。');
                        return;
                    }

                    alert('自動コピーに失敗しました。選択された内容を手動でコピーしてください。');
                } finally {
                    if (button) {
                        button.disabled = false;
                    }
                }
            };

            window.copyAndMarkContext = async function (button = null) {
                const element = document.getElementById('aiContextPrompt');
                const form = document.getElementById('markContextForm');

                if (! element) {
                    alert('コピー対象のプロンプトが見つかりません。');
                    return;
                }

                if (button) {
                    button.disabled = true;
                }

                const copied = await copyText(element.value, element);

                if (! copied) {
                    if (button) {
                        button.disabled = false;
                    }

                    alert('自動コピーに失敗しました。選択された内容を手動でコピーしてください。共有日時は更新していません。');
                    return;
                }

                if (! form) {
                    if (button) {
                        button.disabled = false;
                    }

                    alert('コピーは完了しましたが、共有済み状態を保存できませんでした。');
                    return;
                }

                form.submit();
            };
        })();
    </script>
@endif
