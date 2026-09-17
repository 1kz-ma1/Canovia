<div class="assistant-message-row assistant-message-left">
    <div class="assistant-avatar">CV</div>
    <div class="assistant-bubble assistant-bubble-support assistant-wide-bubble">
        <p class="assistant-speaker">Canovia サポーター</p>
        <h2 class="mt-2 text-xl font-bold text-slate-100">どの計画を更新しますか？</h2>
        <p class="mt-2 text-sm leading-7 text-slate-300">
            実績・分かったこと・予定との違い・方針変更を一度に報告できます。更新の種類を先に選ぶ必要はありません。
        </p>

        @if ($ownedPlans->isEmpty())
            <div class="mt-5 rounded-xl border border-slate-700 bg-slate-900/70 p-4">
                <p class="text-sm text-slate-300">更新できる計画がまだありません。</p>
                <a href="{{ route('plans.create') }}" class="btn-primary mt-4 inline-flex">計画を作る</a>
            </div>
        @else
            <form method="POST" action="{{ route('chat.answer') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="review_plan_id" class="mb-2 block text-sm font-semibold text-slate-200">対象Plan</label>
                    <select id="review_plan_id" name="plan_id" class="form-control" required>
                        <option value="">選択してください</option>
                        @foreach ($planItems as $item)
                            <option value="{{ $item['plan']->id }}">
                                {{ $item['plan']->title }}（{{ $item['progress']['weighted_progress_percent'] }}% / {{ $item['progress']['status'] }}）
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary">この計画を更新</button>
            </form>
        @endif
    </div>
</div>
