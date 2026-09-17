<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0A0F1E">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Canovia">
    <meta name="referrer" content="no-referrer">
    <link rel="manifest" href="{{ route('pwa.manifest') }}" crossorigin="use-credentials">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/icon-180.png">
    <title>ホーム画面に追加 | Canovia</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100dvh; color:#eef4ff; background:#050a19; overflow-x:hidden; }
        body::before { content:""; position:fixed; inset:0; z-index:-2; background:radial-gradient(circle at 78% 18%,rgba(112,92,255,.24),transparent 27rem),radial-gradient(circle at 10% 54%,rgba(77,211,255,.12),transparent 24rem),linear-gradient(180deg,#071128,#030713 70%); }
        body::after { content:""; position:fixed; inset:0; z-index:-1; opacity:.52; background-image:radial-gradient(circle at 16% 12%,#fff 0 1px,transparent 1.5px),radial-gradient(circle at 77% 27%,#8fdcff 0 1px,transparent 1.5px),radial-gradient(circle at 38% 61%,#ffc978 0 1px,transparent 1.5px),radial-gradient(circle at 88% 76%,#a78bfa 0 1px,transparent 1.5px); background-size:160px 160px,220px 220px,300px 300px,370px 370px; }
        main { width:min(100% - 32px,520px); margin:0 auto; min-height:100dvh; padding:max(34px,env(safe-area-inset-top)) 0 max(30px,env(safe-area-inset-bottom)); display:flex; flex-direction:column; justify-content:center; }
        .brand { display:flex; align-items:center; gap:12px; justify-content:center; }
        .brand img { width:58px; height:58px; filter:drop-shadow(0 0 18px rgba(111,219,255,.36)); }
        .brand strong { font-size:28px; letter-spacing:-.03em; }
        .card { margin-top:28px; padding:24px; border:1px solid rgba(116,150,255,.34); border-radius:28px; background:linear-gradient(145deg,rgba(17,35,76,.9),rgba(5,13,34,.94)); box-shadow:0 24px 65px rgba(0,3,18,.55),inset 0 1px rgba(255,255,255,.05); backdrop-filter:blur(18px); }
        .mascot { display:block; width:145px; margin:-52px auto 8px; filter:drop-shadow(0 20px 36px rgba(0,0,0,.5)); }
        h1 { margin:4px 0 0; text-align:center; font-size:25px; line-height:1.35; }
        .lead { margin:14px auto 0; max-width:34rem; text-align:center; color:#b9c7e8; font-size:14px; line-height:1.9; }
        .protect { margin-top:20px; padding:14px 16px; border-radius:18px; border:1px solid rgba(255,201,120,.28); background:rgba(255,196,103,.08); color:#f3e4c7; font-size:13px; line-height:1.8; }
        .steps { margin:20px 0 0; padding:0; list-style:none; display:grid; gap:12px; counter-reset:step; }
        .steps li { display:grid; grid-template-columns:34px 1fr; gap:11px; align-items:start; color:#dce7ff; font-size:14px; line-height:1.65; }
        .steps li::before { counter-increment:step; content:counter(step); display:grid; place-items:center; width:32px; height:32px; border-radius:50%; background:linear-gradient(145deg,#1a51a0,#633ee8); border:1px solid rgba(148,197,255,.62); box-shadow:0 0 18px rgba(92,126,255,.24); font-weight:900; }
        .note { margin-top:20px; color:#7f92bb; font-size:12px; line-height:1.7; text-align:center; }
        .back { display:inline-flex; margin:18px auto 0; color:#9edfff; text-decoration:none; font-size:13px; font-weight:800; }
        .launching { display:none; text-align:center; }
        .launching.is-visible { display:block; }
        .launching .orbit { width:56px; height:56px; margin:0 auto 16px; border:2px solid rgba(108,217,255,.28); border-top-color:#ffc978; border-right-color:#9b7cff; border-radius:50%; animation:spin .9s linear infinite; box-shadow:0 0 20px rgba(105,154,255,.22); }
        @keyframes spin { to { transform:rotate(360deg); } }
    </style>
</head>
<body>
<main>
    <div class="browser-guide" data-browser-guide>
        <div class="brand"><img src="/brand/logo-mark.svg" alt=""><strong>Canovia</strong></div>
        <section class="card">
            <img class="mascot" src="/brand/mascot-guide.webp" alt="">
            <h1>データをそのまま、ホーム画面へ。</h1>
            <p class="lead">この画面から追加すると、Safariで使っているログイン状態やGuest計画を、ホーム画面版Canoviaへ安全に引き継げます。</p>
            <div class="protect"><strong>重要：</strong>この画面を閉じずに追加してください。引き継ぎリンクは一度だけ使え、30分で期限切れになります。</div>
            <ol class="steps">
                <li><span>Safari下部の<strong>共有</strong>ボタンを押す</span></li>
                <li><span><strong>「ホーム画面に追加」</strong>を選ぶ</span></li>
                <li><span>右上の<strong>「追加」</strong>を押して、ホーム画面からCanoviaを開く</span></li>
            </ol>
            <p class="note">ホーム画面から初めて開いたときだけ、自動で引き継ぎ処理を行います。</p>
        </section>
        <a class="back" href="{{ route('home') }}">← Canoviaへ戻る</a>
    </div>

    <section class="card launching" data-launching>
        <div class="orbit" aria-hidden="true"></div>
        <h1>Canoviaを準備しています</h1>
        <p class="lead">ログイン状態と計画を引き継いでいます。少しだけお待ちください。</p>
    </section>
</main>
<script>
    const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    if (standalone) {
        document.querySelector('[data-browser-guide]')?.setAttribute('hidden', 'hidden');
        document.querySelector('[data-launching]')?.classList.add('is-visible');
        window.setTimeout(() => window.location.replace(@json($handoffUrl)), 120);
    }
</script>
</body>
</html>
