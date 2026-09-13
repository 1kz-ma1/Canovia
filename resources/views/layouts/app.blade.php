@php
    $focusMode = request()->routeIs('work_sessions.active');
    $isCoreScreen = request()->routeIs('home') || request()->routeIs('navigation.index') || request()->routeIs('roadmap.index') || request()->routeIs('timeline.index') || request()->routeIs('calendar.index');
    $mobileSection = match (true) {
        request()->routeIs('navigation.*') => '今日',
        request()->routeIs('work_sessions.*') => '作業',
        request()->routeIs('roadmap.*') => 'ロードマップ',
        request()->routeIs('timeline.*') => 'タイムライン',
        request()->routeIs('calendar.*') => 'カレンダー',
        request()->routeIs('chat.*'), request()->routeIs('achievements.*'), request()->routeIs('plans.review_assistant.*') => '計画を更新',
        request()->routeIs('public_plans.*') => '共有プラン',
        request()->routeIs('plans.*'), request()->routeIs('tasks.*'), request()->routeIs('my_plans.*') => '計画',
        default => 'ホーム',
    };

    $feedbackPlan = request()->route('plan');
    $feedbackTask = request()->route('task');
    $feedbackWorkSession = request()->route('workSession');
    $feedbackPlanId = $feedbackPlan instanceof \App\Models\Plan
        ? $feedbackPlan->id
        : (($feedbackTask instanceof \App\Models\Task) ? $feedbackTask->plan_id : (($feedbackWorkSession instanceof \App\Models\WorkSession) ? $feedbackWorkSession->plan_id : null));
    $feedbackTaskId = $feedbackTask instanceof \App\Models\Task
        ? $feedbackTask->id
        : (($feedbackWorkSession instanceof \App\Models\WorkSession) ? $feedbackWorkSession->task_id : null);
    $onboardingVersion = (int) config('pacekeeper.onboarding_version', 1);
    $onboardingAuto = ! $focusMode && (! auth()->check() || (int) auth()->user()->onboarding_version < $onboardingVersion);
@endphp
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#020617">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="PaceKeeper">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180.png">
    <title>@yield('title', 'Pace Keeper')</title>

    <script>
        (() => {
            try {
                const root = document.documentElement;
                const storedTheme = localStorage.getItem('pacekeeper.ui.theme') || 'system';
                const storedAccent = localStorage.getItem('pacekeeper.ui.accent') || 'sky';
                const storedDensity = localStorage.getItem('pacekeeper.ui.density');
                const isMobile = window.matchMedia('(max-width: 767px)').matches;
                const density = storedDensity || (isMobile ? 'standard' : 'compact');
                const resolvedTheme = storedTheme === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark')
                    : storedTheme;
                root.dataset.uiTheme = storedTheme;
                root.dataset.themeResolved = resolvedTheme;
                root.dataset.uiAccent = storedAccent;
                root.dataset.uiDensity = density;
            } catch (_) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-focus-mode="{{ $focusMode ? '1' : '0' }}" data-onboarding-version="{{ $onboardingVersion }}" data-onboarding-auto="{{ $onboardingAuto ? '1' : '0' }}" data-onboarding-authenticated="{{ auth()->check() ? '1' : '0' }}" data-route-name="{{ request()->route()?->getName() }}" class="pk-cosmic-shell min-h-screen bg-slate-950 text-slate-100 antialiased {{ $focusMode ? 'pace-focus-mode' : '' }}">
    <div class="pk-cosmic-backdrop pointer-events-none fixed inset-0 -z-10 overflow-hidden" aria-hidden="true">
        <span class="pk-space-glow pk-space-glow-a"></span>
        <span class="pk-space-glow pk-space-glow-b"></span>
        <span class="pk-space-star pk-space-star-a"></span>
        <span class="pk-space-star pk-space-star-b"></span>
        <span class="pk-space-star pk-space-star-c"></span>
    </div>

    @unless ($focusMode)
        <header class="desktop-app-header sticky top-0 z-50 hidden border-b border-slate-800/90 bg-slate-950/90 backdrop-blur-xl md:block">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4">
                <a href="{{ route('home') }}" class="pk-brand-lockup group inline-flex items-center gap-3" aria-label="PaceKeeper ホーム">
                    <img src="/brand/logo-mark.svg" alt="" class="pk-brand-mark" width="44" height="44">
                    <span>
                        <span class="pk-brand-wordmark block text-xl font-black tracking-tight">PaceKeeper</span>
                        <span class="block text-[11px] font-semibold tracking-[0.08em] text-slate-400">自分のペースで、前へ。</span>
                    </span>
                </a>

                <nav class="pk-desktop-nav flex flex-wrap items-center gap-1 rounded-2xl border border-slate-800 bg-slate-900/75 p-1 text-sm shadow-lg shadow-slate-950/20" aria-label="メインナビゲーション">
                    <a href="{{ route('home') }}" class="nav-link pk-nav-link whitespace-nowrap {{ request()->routeIs('home') || request()->routeIs('calendar.*') || request()->routeIs('my_plans.*') || request()->routeIs('plans.show') || request()->routeIs('plans.edit') || request()->routeIs('tasks.*') ? 'nav-link-active' : '' }}"><span class="pk-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6.8 15.7c-1.8 1.7-2.7 3.4-2.5 4.9 1.5.2 3.2-.7 4.9-2.5M14.5 4.2c2.8-.9 5.2-.9 5.3-.8.1.1.1 2.5-.8 5.3-1 3.2-3.5 6.1-7.2 7.8L7.9 12.6c1.7-3.7 4.6-6.2 6.6-8.4Z"/><path d="m9.1 15 4 4M7.4 12.1l-2.7.6-1.5 2.6 4.2.8M14.8 16.3l.8 4.2 2.6-1.5.6-2.7"/><circle cx="15.2" cy="8.8" r="1.6"/></svg><i></i></span><span>ホーム</span></a>
                    <a href="{{ route('navigation.index') }}" data-navigation-link data-onboarding-target="today-nav" class="nav-link pk-nav-link whitespace-nowrap {{ request()->routeIs('navigation.*') || request()->routeIs('work_sessions.*') ? 'nav-link-active' : '' }}"><span class="pk-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m8.5 6.3 9 5.7-9 5.7V6.3Z"/></svg><i></i></span><span>今日</span></a>
                    <a href="{{ route('roadmap.index') }}" data-onboarding-target="roadmap-nav" class="nav-link pk-nav-link whitespace-nowrap {{ request()->routeIs('roadmap.*') || request()->routeIs('chat.*') || request()->routeIs('plans.review_assistant.*') || request()->routeIs('achievements.*') ? 'nav-link-active' : '' }}"><span class="pk-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5 9 3l6 2.5L20 3v15.5L15 21l-6-2.5L4 21V5.5Zm5-2.5v15.5M15 5.5V21"/></svg><i></i></span><span>ロードマップ</span></a>
                    <a href="{{ route('timeline.index') }}" class="nav-link pk-nav-link whitespace-nowrap {{ request()->routeIs('timeline.*') ? 'nav-link-active' : '' }}"><span class="pk-nav-icon pk-nav-icon-timeline" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="10" cy="10" r="6"/><path d="M10 7v3.5l2.4 1.5M14.8 15.5h4.4a1.8 1.8 0 0 1 1.8 1.8v1.8a1.8 1.8 0 0 1-1.8 1.8h-1.7l-1.8 1.4.2-1.4h-1.1a1.8 1.8 0 0 1-1.8-1.8v-1.8"/></svg><i></i></span><span>タイムライン</span></a>
                </nav>

                <div class="hidden items-center gap-2 lg:flex">
                    <a href="{{ route('calendar.index') }}" class="header-secondary-link">カレンダー</a>
                    <button type="button" class="ui-settings-trigger" data-ui-settings-open aria-label="表示設定を開く">表示</button>
                    @auth
                        <span class="max-w-36 truncate text-xs font-semibold text-slate-400">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('auth.logout') }}" data-clear-offline-state>
                            @csrf
                            <button type="submit" class="btn-secondary px-3 py-2 text-xs">ログアウト</button>
                        </form>
                    @else
                        <a href="{{ route('auth.login.form') }}" class="btn-secondary px-3 py-2 text-xs">ログイン</a>
                        <a href="{{ route('auth.register.form') }}" class="btn-primary px-3 py-2 text-xs">アカウント作成</a>
                    @endauth
                </div>
            </div>
        </header>

        <header class="mobile-app-header md:hidden">
            <div class="mobile-app-header-inner">
                @unless ($isCoreScreen)
                    <button type="button" class="mobile-back-button" data-mobile-back aria-label="前の画面に戻る">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                @else
                    <a href="{{ route('home') }}" class="mobile-brand-mark pk-mobile-brand-mark" aria-label="PaceKeeper ホーム"><img src="/brand/logo-mark.svg" alt="" width="32" height="32"></a>
                @endunless
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[11px] font-semibold uppercase tracking-[0.18em] text-sky-300">PaceKeeper</p>
                    <p class="truncate text-sm font-bold text-slate-50">{{ $mobileSection }}</p>
                </div>
                <button type="button" class="mobile-utility-button" data-ui-settings-open aria-label="表示設定を開く" title="表示設定">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18h1.4a1.6 1.6 0 0 0 0-3.2h-.9a1.8 1.8 0 0 1 0-3.6H15A6 6 0 0 0 15 3h-3Zm-4.5 7.5h.01M9 6.8h.01M14.8 6.6h.01M17.2 10h.01"/></svg>
                </button>
                @auth
                    <a href="{{ route('auth.account') }}" class="account-state-dot is-protected" title="アカウント保護済み" aria-label="アカウント設定"></a>
                @else
                    <a href="{{ route('auth.register.form') }}" class="account-state-dot" title="この端末だけに保存中" aria-label="アカウントを作る"></a>
                @endauth
            </div>
        </header>
    @endunless

    <main class="app-main mx-auto min-h-[calc(100vh-120px)] max-w-7xl px-4 py-5 sm:px-5 md:px-6 md:py-10 {{ $focusMode ? 'focus-main' : '' }}">
        @if (session('status'))
            <div class="assistant-notice assistant-notice-info mb-6" data-auto-toast>{{ session('status') }}</div>
        @endif

        @guest
            @if (request()->routeIs('home') || request()->routeIs('plans.*') || request()->routeIs('navigation.*') || request()->routeIs('work_sessions.*') || request()->routeIs('my_plans.*'))
                <div class="guest-protection-banner mb-4">
                    <span><strong>この端末だけに保存中</strong>。アカウントを作ると、端末を変えても続けられます。</span>
                    <a href="{{ route('auth.register.form') }}">アカウントを作る</a>
                </div>
            @endif
        @endguest

        <div class="sync-status-chip is-passive" data-sync-status data-sync-mode="online" aria-live="polite">
            <span class="sync-status-dot"></span><span data-sync-status-label>オンライン</span>
        </div>

        @yield('content')
    </main>

    @unless ($focusMode)
        <footer class="mt-12 hidden border-t border-slate-800 bg-slate-950/70 md:block">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-6 py-8 text-sm text-slate-500 md:flex-row md:items-center md:justify-between">
                <p>Pace Keeper — 自分のペースで、前へ。</p>
            </div>
        </footer>

        <div class="md:hidden">
            @include('layouts.partials.mobile-nav')
        </div>
    @endunless

    @yield('offline_snapshot')

    <div class="route-loading-overlay" data-route-loading aria-hidden="true">
        <div class="route-loading-card">
            <span class="route-loading-spinner" aria-hidden="true"></span>
            <span>読み込み中…</span>
        </div>
    </div>

    @unless ($focusMode)
        <dialog class="ui-settings-dialog" data-ui-settings-dialog aria-labelledby="ui-settings-title">
            <form method="dialog" class="ui-settings-card">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="ui-settings-title" class="text-xl font-bold text-slate-50">表示設定</h2>
                    </div>
                    <button type="button" class="feedback-close" data-ui-settings-close aria-label="閉じる">×</button>
                </div>

                <fieldset class="mt-6">
                    <legend class="text-sm font-bold text-slate-200">テーマ</legend>
                    <div class="ui-choice-grid mt-3" data-ui-theme-options>
                        <button type="button" class="ui-choice" data-ui-theme-value="system"><span>◐</span><strong>自動</strong><small>端末に合わせる</small></button>
                        <button type="button" class="ui-choice" data-ui-theme-value="dark"><span>●</span><strong>ダーク</strong><small>落ち着いた表示</small></button>
                        <button type="button" class="ui-choice" data-ui-theme-value="light"><span>○</span><strong>ライト</strong><small>明るい表示</small></button>
                    </div>
                </fieldset>

                <fieldset class="mt-6">
                    <legend class="text-sm font-bold text-slate-200">アクセント</legend>
                    <div class="ui-accent-options mt-3" data-ui-accent-options>
                        @foreach (\App\Models\Plan::ACCENT_LABELS as $accentKey => $accentLabel)
                            <button type="button" class="ui-accent-swatch" data-ui-accent-value="{{ $accentKey }}" data-accent="{{ $accentKey }}" aria-label="{{ $accentLabel }}"></button>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="mt-6">
                    <legend class="text-sm font-bold text-slate-200">表示密度</legend>
                    <div class="ui-choice-grid mt-3" data-ui-density-options>
                        <button type="button" class="ui-choice" data-ui-density-value="compact"><strong>コンパクト</strong><small>多めに表示</small></button>
                        <button type="button" class="ui-choice" data-ui-density-value="standard"><strong>標準</strong><small>ちょうどよく表示</small></button>
                        <button type="button" class="ui-choice" data-ui-density-value="comfortable"><strong>ゆったり</strong><small>余白を広めに</small></button>
                    </div>
                </fieldset>

                <div class="mt-6 grid gap-2 sm:grid-cols-2">
                    <button type="button" class="btn-secondary w-full justify-center" data-onboarding-restart>使い方を見る</button>
                    <button type="button" class="btn-secondary w-full justify-center" data-install-guide-open>ホーム画面に追加</button>
                </div>

                <button type="button" class="btn-primary mt-3 w-full" data-ui-settings-close>この見え方で使う</button>
            </form>
        </dialog>

        <div class="app-update-banner hidden" data-app-update role="status" aria-live="polite">
            <div class="min-w-0">
                <p class="font-bold text-slate-50">PaceKeeperを更新できます</p>
                <p class="mt-1 text-xs text-slate-300">作業中に勝手に再読み込みはしません。</p>
            </div>
            <div class="flex shrink-0 gap-2">
                <button type="button" class="btn-secondary px-3 py-2 text-xs" data-app-update-later>あとで</button>
                <button type="button" class="btn-primary px-3 py-2 text-xs" data-app-update-apply>更新する</button>
            </div>
        </div>

        <button type="button" class="feedback-fab" data-feedback-open aria-label="PaceKeeperへフィードバックを送る">意見</button>
        <dialog class="feedback-dialog" data-feedback-dialog aria-labelledby="feedback-title">
            <form method="POST" action="{{ route('feedback.store') }}" class="feedback-dialog-card">
                @csrf
                <input type="hidden" name="page" value="{{ request()->path() }}">
                @if ($feedbackPlanId)<input type="hidden" name="plan_id" value="{{ $feedbackPlanId }}">@endif
                @if ($feedbackTaskId)<input type="hidden" name="task_id" value="{{ $feedbackTaskId }}">@endif
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 id="feedback-title" class="text-xl font-bold text-slate-50">気づいたことを教えてください</h2>
                    </div>
                    <button type="button" class="feedback-close" data-feedback-close aria-label="閉じる">×</button>
                </div>
                <fieldset class="mt-5">
                    <legend class="text-sm font-semibold text-slate-300">PaceKeeperの総合評価</legend>
                    <input type="hidden" name="rating" value="" data-feedback-rating-input>
                    <div class="feedback-star-row mt-2" role="group" aria-label="5段階評価">
                        @for ($rating = 1; $rating <= 5; $rating++)
                            <button type="button" class="feedback-star" data-feedback-rating-value="{{ $rating }}" aria-label="{{ $rating }}点" aria-pressed="false">★</button>
                        @endfor
                    </div>
                    <p class="mt-1 text-xs text-slate-500" data-feedback-rating-label>未評価</p>
                </fieldset>

                <label class="mt-5 block">
                    <span class="text-sm font-semibold text-slate-300">どんな内容？</span>
                    <select name="type" class="form-control mt-2" required>
                        <option value="usability">使いにくい</option>
                        <option value="bug">バグ</option>
                        <option value="request">欲しい機能</option>
                        <option value="positive">よかった点</option>
                    </select>
                </label>
                <label class="mt-4 block">
                    <span class="text-sm font-semibold text-slate-300">ひとこと</span>
                    <textarea name="message" rows="5" maxlength="4000" class="form-control mt-2" placeholder="気になったことがあれば教えてください"></textarea>
                </label>
                <p class="mt-3 text-xs leading-5 text-slate-500">画面の情報は自動で添えます。個人情報は書かないでください。</p>
                <div class="mt-5 flex gap-2">
                    <button type="button" class="btn-secondary flex-1" data-feedback-close>キャンセル</button>
                    <button type="submit" class="btn-primary flex-1">送信</button>
                </div>
            </form>
        </dialog>
    @endunless

    @include('layouts.partials.onboarding')
</body>
</html>
