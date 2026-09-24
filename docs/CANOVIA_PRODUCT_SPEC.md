# Canovia Product Specification

更新基準: 2026-09-24 / main V40.7.3 + V40.7.4 guided learning flow + V40.7.5 assessment POST recovery

この文書をCanoviaのプロダクトレベル仕様の正とする。旧PaceKeeper v16系のProject Overview / Requirements / Functional Spec / Future Ideasは履歴資料として扱い、現在仕様の判断には本書と各V40系実装ドキュメントを優先する。

## 1. Product Vision

Canoviaは、**頑張りたいけれど、頑張り方が分からない・やる気が続かない人が、目標や夢へ進めるよう支援する伴走型OS**を目指す。

中核となる体験は次の循環である。

```text
Plan
  ↓
Task / Roadmap
  ↓
Today / Recommendation
  ↓
実行 / WorkSession / Timer
  ↓
振り返り / WorkLog
  ↓
AI支援 / 計画更新 / 学習
  ↓
次の行動
```

Canoviaは「完璧な計画を守らせる」より、現実の行動・発見・制約に合わせて計画を育て直すことを重視する。

## 2. Product Principles

1. **Canovia本体は一人でも価値がある。** Socialが成立しなくてもPersonal Utilityだけでプロダクトが成立する。
2. **無料でも核となる目標達成体験を成立させる。** 基本思想は「努力する権利には課金しない。努力を加速する価値に課金する」。
3. **アプリが知っている事実を再入力させない。** Plan、Task、WorkSession、WorkLog等の既知情報を再利用する。
4. **FactsとInterpretationを分離する。** 作業事実と、AI/推薦による意味付けを分ける。
5. **収益化ロジックを機能へ散らさない。** Feature側はPremium/Coin/Gift/Sponsorを直接判定しない。
6. **Feature FlagとEntitlementを分離する。** 公開可否と利用権は別責務とする。
7. **RoadmapとRuntime Feature Controlを分離する。** Product statusがruntime enableを自動決定しない。
8. **DeployとReleaseを分離できる構造を目指す。** ただしApp Reviewを迂回するために使わない。
9. **ユーザーの声は需要シグナルとして使い、単純多数決でプロダクトを作らない。**
10. **将来機能のために現在のUXを複雑にしない。**

## 3. Now / Next / Future

### Now

現在実装済み、またはV40.7で基盤を持つ領域。

- Guest / Account、所有権、共同計画
- Plan / Task / Roadmap / Today / Recommendation
- WorkSession / Timer / WorkLog / Continuity
- Calendar / Availability
- AI JSON外部往復によるTask生成・計画更新
- 外部AI Handoff UIの統一（Promptはコピー中心、JSONは1クリック貼り付け＋手動fallback）
- AI Practice / Question Pack基盤
- AI Practice途中回答の自動保存・無通知再開
- AI Practice回答済み・評価済み状態の永続復元と次Step自動Reveal
- AI Practice評価JSON POST時のanswered状態durable recovery（PHP Session欠落時もDBから復元）
- AI Practiceのcurrent-step UIと構造化next_stepによる「次にやること」導線
- Resource / Project Artifact
- Future Memo
- Achievement / Timeline / Release Notes
- PWA / Offline / Safe Update
- Feedback自由記述 / Admin Feedback
- FeatureAccessService / Entitlement resolver境界
- FeatureKey一元管理
- FeatureFlagServiceの最小公開可否境界
- Canovia Future / Roadmap Votingの最小データモデルとSupport UI

### Next

iOS正式公開準備や、現行基盤を実運用へ接続する近い将来。

- iOSアプリ化とApp Store Review運用
- server-backed Feature Flag保存とAdmin操作
- platform / minimum app versionを使った公開制御
- Roadmap候補のAdmin管理（表示、投票受付、threshold、priority、status）
- SupportしたRoadmap Featureのstatus変化・Release通知
- Roadmap FeatureとRelease Notesの明示的な紐付け
- Premium / StoreKitの具体要件確定後のPremiumEntitlementResolver
- 必要なFeatureのみPremium/Coin等の追加価値へ切り替える設計

### Future

需要確認後に実装判断する領域。

- Coin購入 / Coin消費 / Earn Coin
- Gift
- Sponsored Access
- 高度なpercentage rollout / audience targeting
- 公開プロフィール
- Public Challenge
- 同じ目標を持つ人の探索
- Achievement共有
- Cheer
- Follow
- Progress Feed
- Social notification
- private / shareable / publicの一般化されたvisibilityモデル

## 4. Monetization and Entitlements

将来のアクセス経路候補:

- Free
- Premium
- Coin
- Earn Coin
- Gift
- Sponsored Access

Feature側が問い合わせる内容は一つに限定する。

> このactorは、この公開済みFeatureを利用できるか。

`FeatureAccessService` がEntitlementの共通境界であり、Feature codeへ以下のような条件を散らさない。

```php
$user->is_premium
$user->coin_balance
$user->has_gift
```

V40.6時点ではFree resolverのみが有劻で、既存機能の利用範囲は変更しない。Premium購入、StoreKit、Coin残高、Coin取引、Earn Coin、Gift、Sponsor、Paywallは未実装である。

## 5. Feature Flag

Feature Flagの問い:

> この機能を現在公開するか。

Entitlementの問い:

> 公開済みのこの機能を、このactorが利用できるか。

V40.7では`FeatureFlagService`を最小境界として追加する。現在の永続化元は設定であり、server-side Admin操作・percentage rolloutの氵続化はNextへ送る。

最小定義で考慮できる項目:

- feature key
- enabled
- environment
- platform
- minimum app version

将来追加候補:

- rollout percentage
- optional audience
- released_at
- emergency off

Feature FlagをEntitlement resolverの内部へ入れない。

## 6. Deploy / Release / App Store

目標は、審査済み・配布済みコードについて、ユーザー向け公開タイミングをサーバー側で制御できること。

```text
Implement
  ↓
App Reviewで説明・確認可能にする
  ↓
Binary distribution
  ↓
Feature Flag off / internal
  ↓
段階Release
  ↓
必要ならoffへ戻す
```

Feature Flagは、審査されていない巨大な隠し機能を後から秘密裏に公開するためには使用しない。半年先のSocial全体を今から隠して実装することもしない。

## 7. Canovia Future / Feedback V2

Feedbackの主体を「自由記述だけ」から、Canovia側が整理した候補へSupportできる**Canovia Future**へ拡張する。

目的:

- 自由記述より参加ハードルを下げる
- プロダクト方向性をCanovia側で保持したまま需要を定量化する
- 自分がSupportした機能の進展を感じられる体験を作る

原則:

- 1 actor / 1 candidate / 1 Support
- Guestはbrowser actor token、Accountはuser idを投票単位にする
- thresholdは実装保証ではなく「Roadmap入り・正式検討開始の目安」
- 最終判断は運営側が保持する
- 従来の自由記述Feedbackは廃止しない

Roadmap status:

- `voting`
- `considering`
- `planned`
- `in_development`
- `ready`
- `rolling_out`
- `released`

理想的な流れ:

```text
Idea
 ↓
Voting
 ↓
Roadmap
 ↓
Development
 ↓
Release
 ↓
Release Notes
```

Roadmap statusとFeature Flagc񯨇򥋕連動させない。

## 8. Social Future

SocialはCanoviaの前提条件にしない。

拡張順:

```text
Personal Utility
 ↓
Outcome / Success
 ↓
Social Layer
 ↓
Network Effect
 ↓
Economy
```

Social候補はFutureとして保持し、需要が確認された機能だけを順次実装する。SNS化そのものを目的にせず、「自分自身が前に進める」という中核価値を優先する。

## 9. Visibility Direction

将来、共有対象には以下の概念を扱える余地を持たせる。

- `private`
- `shareable`
- `public`

ただし現時点で全モデルへ共通visibility列を追加することはしない。Featureごとの実要件が確定した段階で共通化を判断する。

## 10. Admin Direction

### Roadmap Admin

将来管理する項目:

- published / hidden
- voting enabled
- threshold
- priority / sort
- status

### Feature Flag Admin

将来管理する項目:

- enabled / disabled
- environment
- platform
- minimum version
- rollout
- audience

両者を同一テーブル・同一statusへ統合しない。

## 11. Data Boundaries

### Product Roadmap

`roadmap_features`

- feature_key
- title
- description
- threshold
- status
- sort_order
- is_published
- voting_enabled
- released_at

`roadmap_votes`

- roadmap_feature_id
- user_id nullable
- actor_token nullable
- voter_key

`(roadmap_feature_id, voter_key)`をuniqueとし、同一actorの重複Supportを防ぐ。

### Runtime Access

- Feature Flag: `FeatureFlagService`
- Entitlement: `FeatureAccessService`
- Ownership: `PlanOwnershipService`

3つは独立した責務として維持する。

## 12. Explicit Non-goals of V40.7

- Premium購入
- StoreKit
- Coin balance / ledger
- Coin購入・消費
- Earn Coin
- Gift
- Sponsor
- Paywall
- Social Feed / Follow / Cheerの本実装
- Roadmap Admin CRUD
- Feature Flag Admin UI
- DB backed remote flag persistence
- percentage rollout
- push notification
- Roadmap statusからFeature Flagへの自動同期

これらは仕様上Future/Nextとして保持し、需要とiOS要件が具体化してから実装する。
