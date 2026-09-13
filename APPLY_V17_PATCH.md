# PaceKeeper v17 — Cosmic Brand / Visual Polish

## 目的
PaceKeeperの既存機能・UXを変えずに、公開前の第一印象を「宇宙 × 伴走 × 前進」の世界観へ寄せるVisual Polishです。

## 反映内容
- PaceKeeperオリジナルの星＋軌道シンボルをSVGで追加
- PWA / Apple touch icon を新ブランドアイコンへ更新
- PCヘッダー / モバイルヘッダーのブランド表示を更新
- 4つの主要ナビに宇宙モチーフの専用アイコンを実装
  - ホーム: ロケット
  - 今日: 再生マーク
  - ロードマップ: 地図
  - タイムライン: 時計＋会話
- ナビアイコンの背面へ薄い軌道・星の意匠を追加
- Darkテーマの背景に控えめな星空・シアン/バイオレットの光を追加
- Home / Today / Roadmap / Timeline の冒頭をブランドHero化
- 生成済みキャラクター素材から案内役マスコットをアプリ用に最適化して配置
- Todayの「PK」アバターをブランドマークに変更
- 初回Onboardingにマスコット＋ブランドマークを追加
- Static Welcomeを新ブランドへ更新し、ローダーを「星＋軌道」に変更
- Offline shellもブランド表示へ統一
- Service Worker shell cacheを v6 に更新し、ブランド素材をオフラインキャッシュへ追加
- 既存の `Top pick` を `おすすめ` に変更

## 変更していないもの
- DB / Migration
- Recommendationロジック
- WorkSession / Timer
- Account / Feedback
- 既存ルーティング
- package.json / package-lock.json
- composer.json / composer.lock

## 適用後の確認
```powershell
php artisan optimize:clear
php artisan view:cache
php artisan test
npm ci
npm run build
```

ブラウザ/PWAではService Worker更新が入るため、旧バージョンが残る場合は表示される更新案内から更新してください。

## 手動チェック推奨
1. Darkテーマで Home / 今日 / Roadmap / Timeline を一周
2. モバイル下部ナビのラベル・アイコンが4列で崩れないこと
3. Todayの条件変更・おすすめ開始が従来通り動くこと
4. 初回Onboardingでマスコットが操作対象を邪魔しないこと
5. Static Welcomeで待機・再試行・本体遷移が従来通り動くこと
6. PWAを再インストールした場合、新アイコンが表示されること
7. Lightテーマも操作可能なこと（世界観はDarkを主役として設計）

## 次の改善候補（今回は未実装）
- マスコットの表情/ポーズ差分を透明背景の正式素材として作る
- 完了時/空状態/エラー時ごとのキャラクター演出
- Roadmapの宇宙テーマをタスクノードまで拡張
- ロゴタイプを完全なベクターアセットとして仕上げる
