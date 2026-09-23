@php
    $guideConfig = config('canovia_guides', []);
    $guideCategories = collect($guideConfig['categories'] ?? []);
    $guideDefinitions = collect($guideConfig['guides'] ?? []);
    $guidePayload = [
        'version' => (int) ($guideConfig['version'] ?? 1),
        'authenticated' => auth()->check(),
        'guides' => $guideDefinitions,
    ];
@endphp

<dialog class="canovia-guide-dialog" data-guide-dialog aria-labelledby="canovia-guide-title">
    <section class="canovia-guide-card">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="canovia-guide-kicker">CANOVIA GUIDE</p>
                <h2 id="canovia-guide-title" class="mt-1 text-2xl text-slate-50">やりたいことから案内する</h2>
                <p class="mt-2 max-w-xl text-sm leading-6 text-slate-400">機能名を覚えなくても大丈夫。やりたいことを選ぶと、実際の画面を使って順番に案内します。</p>
            </div>
            <button type="button" class="feedback-close" data-guide-close aria-label="ガイドを閉じる">×</button>
        </div>

        <label class="canovia-guide-search mt-5">
            <span class="sr-only">ガイドを検索</span>
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4 4"/></svg>
            <input type="search" placeholder="やりたいことを検索  例：共同計画、AI演習、タイマー" data-guide-search>
        </label>

        <div class="canovia-guide-list mt-5" data-guide-list>
            @foreach ($guideCategories as $categoryKey => $category)
                @php
                    $categoryGuides = $guideDefinitions->filter(fn ($guide) => ($guide['category'] ?? null) === $categoryKey);
                @endphp
                @if ($categoryGuides->isNotEmpty())
                    <details class="canovia-guide-category" data-guide-category="{{ $categoryKey }}" open>
                        <summary>
                            <span class="canovia-guide-category-icon" aria-hidden="true">{{ $category['icon'] ?? '✦' }}</span>
                            <span class="min-w-0 flex-1">
                                <strong>{{ $category['label'] ?? $categoryKey }}</strong>
                                <small>{{ $category['description'] ?? '' }}</small>
                            </span>
                            <i aria-hidden="true"></i>
                        </summary>
                        <div class="canovia-guide-category-items">
                            @foreach ($categoryGuides as $guideKey => $guide)
                                <button
                                    type="button"
                                    class="canovia-guide-item"
                                    data-guide-start="{{ $guideKey }}"
                                    data-guide-search-text="{{ mb_strtolower(implode(' ', array_merge([$guide['title'] ?? '', $guide['description'] ?? ''], $guide['keywords'] ?? []))) }}"
                                    @if(($guide['requires_auth'] ?? false) && !auth()->check()) data-guide-requires-auth="1" @endif
                                >
                                    <span class="min-w-0 flex-1 text-left">
                                        <strong>{{ $guide['title'] }}</strong>
                                        <small>{{ $guide['description'] ?? '' }}</small>
                                    </span>
                                    @if (($guide['requires_auth'] ?? false) && !auth()->check())
                                        <span class="canovia-guide-state">ログイン</span>
                                    @else
                                        <span class="canovia-guide-state" data-guide-completion="{{ $guideKey }}">GO!</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endforeach
            <p class="canovia-guide-empty hidden" data-guide-empty>該当するガイドがありません。別の言葉で検索してみてください。</p>
        </div>
    </section>
</dialog>

<div class="canovia-guide-runner hidden" data-guide-runner aria-live="polite">
    <div class="canovia-guide-blocker" data-guide-blocker="top"></div>
    <div class="canovia-guide-blocker" data-guide-blocker="left"></div>
    <div class="canovia-guide-blocker" data-guide-blocker="right"></div>
    <div class="canovia-guide-blocker" data-guide-blocker="bottom"></div>
    <div class="canovia-guide-focus-ring" data-guide-focus-ring aria-hidden="true"></div>

    <section class="canovia-guide-bubble" data-guide-bubble role="dialog" aria-modal="false" aria-labelledby="canovia-guide-step-title">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="canovia-guide-kicker" data-guide-progress>CANOVIA GUIDE</p>
                <h2 id="canovia-guide-step-title" class="canovia-guide-step-title" data-guide-title></h2>
            </div>
            <button type="button" class="canovia-guide-stop" data-guide-stop>終了</button>
        </div>
        <p class="canovia-guide-copy" data-guide-copy></p>
        <p class="canovia-guide-missing hidden" data-guide-missing>この画面では対象がまだ表示されていません。必要なPlanやTaskを作成してから、もう一度このガイドを開始できます。</p>
        <div class="canovia-guide-actions">
            <button type="button" class="btn-secondary" data-guide-prev>前へ</button>
            <button type="button" class="btn-primary" data-guide-next>次へ</button>
        </div>
    </section>
</div>

<script type="application/json" id="canovia-guide-catalog">{!! json_encode($guidePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
