<div class="assistant-message-row assistant-message-left">
    <div class="assistant-avatar">CV</div>
    <div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p>
        <h2 class="mt-2 text-2xl font-bold text-slate-100">今日は何をしますか？</h2>
        <p class="mt-2 leading-7 text-slate-300">
            計画を作る・現実に合わせて更新する・達成した計画を振り返る。必要な操作だけを選べます。
        </p>

        <div class="chat-action-grid mt-5">
            <a href="{{ route('navigation.index') }}" class="chat-action-card w-full text-left ring-1 ring-sky-400/40">
                <span class="chat-action-icon">▶</span>
                <span>
                    <span class="block font-bold text-slate-100">今日のおすすめから始める</span>
                    <span class="mt-1 block text-sm leading-6 text-slate-400">今のPlanと作業リズムから1件を提案し、その場ですぐタイマーを開始できます。</span>
                </span>
            </a>

            <a href="{{ route('plans.create') }}" class="chat-action-card w-full text-left">
                    <span class="chat-action-icon">✓</span>
                    <span>
                        <span class="block font-bold text-slate-100">計画を生成する</span>
                        <span class="mt-1 block text-sm leading-6 text-slate-400">目標と期限を登録し、AIに初期タスクを組み立ててもらいます。</span>
                    </span>
            </a>

            <form method="POST" action="{{ route('chat.start', 'review') }}">
                @csrf
                <button type="submit" class="chat-action-card w-full text-left">
                    <span class="chat-action-icon">＋</span>
                    <span>
                        <span class="block font-bold text-slate-100">やったことを後から反映する</span>
                        <span class="mt-1 block text-sm leading-6 text-slate-400">タイマーを使っていない作業も、AIとの会話から分かる範囲だけ計画へ戻せます。作業時間が曖昧でも構いません。</span>
                    </span>
                </button>
            </form>

            <form id="plan-update" method="POST" action="{{ route('chat.start', 'review') }}">
                @csrf
                <button type="submit" class="chat-action-card w-full text-left">
                    <span class="chat-action-icon">↻</span>
                    <span>
                        <span class="block font-bold text-slate-100">計画を更新する</span>
                        <span class="mt-1 block text-sm leading-6 text-slate-400">実績・発見・方針変更をまとめて報告し、今の現実に合う計画へ更新します。</span>
                    </span>
                </button>
            </form>

            <a href="{{ route('achievements.index') }}" class="chat-action-card w-full text-left">
                <span class="chat-action-icon">★</span>
                <span>
                    <span class="block font-bold text-slate-100">達成した計画を見る</span>
                    <span class="mt-1 block text-sm leading-6 text-slate-400">完了したPlanの成果と、そこまでの進み方をCanoviaと振り返ります。</span>
                </span>
            </a>

            <form method="POST" action="{{ route('chat.start', 'status') }}">
                @csrf
                <button type="submit" class="chat-action-card w-full text-left">
                    <span class="chat-action-icon">◎</span>
                    <span>
                        <span class="block font-bold text-slate-100">現在の状況を確認する</span>
                        <span class="mt-1 block text-sm leading-6 text-slate-400">進捗、進行中Task、最近の記録を確認します。</span>
                    </span>
                </button>
            </form>
        </div>

        <div class="mt-6 border-t border-slate-700 pt-5">
            <p class="text-sm font-semibold text-slate-400">補助操作</p>
            <div class="mt-3 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('chat.start', 'task') }}">
                    @csrf
                    <button type="submit" class="btn-secondary">タスクを追加</button>
                </form>
                <form method="POST" action="{{ route('chat.start', 'ai_context') }}">@csrf<button type="submit" class="btn-secondary">AIに現状を共有</button></form>
            </div>
        </div>
    </div>
</div>
