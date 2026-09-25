# Canovia Product Specification

更新基準: 2026-09-25 / V41.5 Economy Foundation

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
5. **収益化ロジックを機能へ散らさない。** Feature側はPremium/Pack/Gift/Sponsorを直接判定しない。Coinは直接Feature解放に使わない。
6. **Feature FlagとEntitlementを分離する。** 公開可否と利用権は別責務とする。
7. **RoadmapとRuntime Feature Controlを分離する。** Product statusがruntime enableを自動決定しない。
8. **DeployとReleaseを分離できる構造を目指す。** ただしApp Reviewを迂回するために使わない。
9. **ユーザーの声は需要シグナルとして使い、単純多数決でプロダクトを作らない。**
10. **将来機能のために現在のUXを複雑にしない。**
11. **時間の経過そのものをProgressの証拠にしない。** 時間はTask負荷・期限・今日の実行可能性を判断する目安として使い、進捗はTask状態・Milestone・Evidenceを優先する。
12. **実行環境をすべて内蔵しない。** Canoviaは外部ツールを置き換えるのではなく、Execution ActionとEvidenceを通じて現実の作業とPlanを接続する。

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
- Home Plan Hub（CURRENT TASK / Task短縮一覧 / Plan Tools / 最近の活動）
- Focus Timerの任意Tool化と「時間=計画上の目安」方針
- TaskEvidence / TaskMilestone / ExecutionAdapter基盤
- AI Practice assessmentのnative Evidence自動記録
- Artifact / Focus Timerのnative Evidence自動記録
- Home Plan HubでCURRENT TASKのRecent Evidenceを表示
- Resource / Project Artifact
- Future Memo
- Achievement / Timeline / Release Notes
- PWA / Offline / Safe Update
- Feedback自由記述 / Admin Feedback
- FeatureAccessService / Entitlement resolver境界
- FeatureKey一元管理
- FeatureFlagServiceの最小公開可否境界
- Canovia Future / Roadmap Votingの最小データモデルとSupport UI
- V41.5 Economy Catalog / Product Grant / source-specific Product Grant Entitlement resolvers
- AI Capacityの独立境界（standard / boosted）
- 決定論的Economy RecommendationとAdmin Economy Inspector

### Next

iOS正式公開準備や、現行基盤を実運用へ接続する近い将来。

- iOSアプリ化とApp Store Review運用
- server-backed Feature Flag保存とAdmin操作
- platform / minimum app versionを使った公開制御
- Roadmap候補のAdmin管理（表示、投票受付、threshold、priority、status）
- SupportしたRoadmap Featureのstatus変化・Release通知
- Roadmap FeatureとRelease Notesの明示的な紐付け
- StoreKit / App Store Server API / Stripe等からProduct Grantへ同期するBilling Adapter
- Premium Coreの最初のNative AI実行経路
- Study / Career / Developer Packの具体Capability実装とFeatureKey接続

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

Canovia Economyの基本構造:

```text
FREE
│
└─ PREMIUM CORE
      ├─ Study Pack
      ├─ Career Pack
      ├─ Developer Pack
      ├─ Creator Pack
      ├─ AI Capacity Boost
      └─ All Access
```

原則:

> Freeでも、自分で動けば目標へ到達できる。  
> 課金すると、整理・転記・解析・判断・自動化をCanoviaがより多く引き受ける。

`Billing`、`Entitlement`、`AI Capacity`は別責務とする。

```text
Billing != Entitlement
Entitlement != AI Capacity
Pack ownership != unlimited AI
```

Feature側が問い合わせる内容は一つに限定する。

> このactorは、この公開済みFeatureを利用できるか。

`FeatureAccessService` がEntitlementの最終境界であり、Feature codeへ以下のような条件を散らさない。

```php
$user->is_premium
$user->has_study_pack
$user->coin_balance
$user->has_gift
```

V41.5では `ProductKey` / `config/economy.php` / `user_product_grants` を追加し、Provider非依存のProduct GrantをPremium / Gift / Sponsorそれぞれのresolverから既存の `FeatureAccessService` へ流す。

```text
Billing / Manual / Gift / Sponsor
              ↓
        Product Grant
              ↓
        Economy Catalog
              ↓
Premium / Gift / Sponsor
Product Grant resolvers
              ↓
     FeatureAccessService
```

現在のユーザー向けFree機能は維持する。Pack向けCapability-level FeatureKeyはFree=falseで予約し、実際のCapabilityを実装したときに既存アクセス境界へ接続する。

AI Capacityは `AiCapacityService` で独立判定する。All AccessはPurpose Packを包含するがAI Capacity Boostを包含しない。

料金構成の推薦はV41.5時点では生成AIではなく決定論的な `EconomyRecommendationService` が担当し、Freeを正式な推薦結果として扱う。売上最大化ではなく「現在の使い方に対する最小十分構成」を目的とする。

Coinは直接Feature解放するEntitlement sourceから外す。将来は応援・Gift・自己表現・Cosmetic等の別経済として扱い、Coinで注目やランキングを買えない方針とする。

未実装:

- StoreKit / Stripe等の購入処理
- 実料金
- 公開Paywall / Checkout
- Coin残高・取引
- Gift購入
- Sponsor課金
- Native AI使用量課金

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


## 13. V41 Execution / Evidence Foundation

Canoviaの実行支援は `Task -> Timer -> WorkLog` だけを正規経路としない。

```text
Plan
  -> Task
  -> Execution Action
  -> Evidence
  -> Progress
  -> Next Action
```

Focus TimerはExecution Actionの一つであり任意。正確な作業時間を取得できない外部作業でも、GitHubのPR、ファイル更新、写真、Calendar、Canovia内部イベントなどのEvidenceからTaskの状態変化を扱える設計を目指す。

V41では `TaskEvidence`、`TaskMilestone`、`ExecutionAdapter`、保守的な `EvidenceProgressService` を基盤として追加する。AI Practice assessmentは最初のnative Evidenceとして自動保存する。

V41.1ではnative EvidenceをTask-linked Artifactの登録・更新とFocus Timer完了/中断へ拡張する。Artifactは中程度のconfidence、Focus Timerは低confidenceのactivity Evidenceとして保存し、どちらも単独ではprogressを変更しない。Plan HubではCURRENT TASKに最近のEvidenceを最大3件表示し、ユーザーへ「Canoviaが確認できた事実」を返す。

EvidenceからProgressやPlanを自動変更するPolicyは別責務とし、V41ではEvidenceProgressServiceは進捗提案のみを返す。将来AIが導入された場合も、Evidence収集・意味解釈・Progress変更・Plan最適化を分離し、確度の低い判断や大きな計画変更を無確認で適用しない。


## 14. V41.2 Adaptive Surface Engine

Plan Hubは固定Card一覧ではなく、Category Profileと現在Situationからregistered Surface Moduleを選択・並べ替えて構成する。

```text
Plan
  -> Category Profile
  -> Situation Resolver
  -> Surface Engine
  -> Plan Hub / Roadmap
```

現在のCategory Profileは study / career / development / creative / general。カテゴリは初期文脈を与えるが、実際のSurface表示はTask・Evidence・Artifact・期限等のSituationも使う。

例としてcareerではCareer Pipelineを表示し、activeな面接Taskが存在する間だけInterview Focusを追加する。studyではAI Practiceまたは直近評価がある場合にStudy Focus、development / creativeではArtifactが存在するときだけDelivery Focusを追加する。

AI導入後は自由なUI生成を許可しない。AIは `policyContext` に含まれるregistered module IDだけを使って順序・非表示を提案し、`applyDecision` がunknown IDを除外する。current_task / plan_toolsはprotected moduleとして非表示不可とする。

Roadmapは同じCategory Profileを利用し、V41.2では安全なtask_flow rendererを維持しつつ、study_map / pipeline / delivery_flow / milestoneをpreferred rendererとして保持する。専門renderer実装時にController/View契約を変えず差し替えられる。


## 15. V41.3 Career Capture / Interview Review

就活カテゴリでは入力負担を最小化するため、Application本体より先に `CareerCapture` を入口に置く。

```text
Screenshot / URL / future Email / Calendar
  -> CareerCapture
  -> Extract / Match
  -> CareerApplication
  -> CareerSelectionEvent
  -> Surface Engine
```

V41.3 UIではScreenshotとURLを利用できる。企業名が分からない段階でもpending Captureとして保存でき、Application作成を必須にしない。手動Application入力はfallbackとして折りたたみ領域に置く。

Screenshot本体は現行Render構成でローカルfilesystem永続性に依存しないよう、非公開のDB Payloadへ最大3MBで保存する。将来object storageへ移行するためのpath境界は維持する。

CareerApplicationが存在する場合、Career PipelineはTask推定ではなくApplication stageを優先する。存在しない既存PlanはV41.2のTask inferenceへfallbackする。

Interview Eventは予定時刻とReview状態からSurfaceを切り替える。

```text
面接前       -> NEXT INTERVIEW
面接後未振返 -> INTERVIEW REVIEW
振返済       -> RESULT WAITING
結果確定     -> Application更新
```

Interview Reviewの質問は `InterviewReviewQuestionService` が供給する。前回Reviewのnext_focusを次回質問へ引き継ぎ、final interview等のstageでも質問を追加できる。Question/Answerはprompt/source付きで永続化するため、将来rule sourceをAI sourceへ置換してもReview UI/schemaを変更しない。

Review完了・選考結果はTaskとの関連がある場合Native TaskEvidenceへ保存するが、これらの事実だけでTask progressを自動変更しない。


## 16. V41.4 Practice Calibration / Weakness Priority

AI Practiceは「誤答したtopicを次回focusへ入れる」だけではなく、誤りの質・再現性・補強コスト・最近の出題偏りを分離する。

```text
Answer
  -> Error Classification
  -> Weakness Priority
  -> Question Mix
  -> Provider Selection
```

`StudyPracticeExamProfileService` は試験形式と問題難易度の校正を担当する。V41.4ではAP科目Aを最初の専用Profileとして、single_choice 4択を基本にし、数値計算は不要な筆算精度で難しくせず、条件判断・概念統合・式選択で難易度を調整する。

`StudyWeaknessPrioritizationService` は直近最大8 Attemptからtopic単位のseverity / confidence / expected_gain / recovery_cost / saturation / error_typeを算出する。1回だけの誤答はsuspectedとしてPrimaryへ固定せず、複数Attemptで繰り返したものをconfirmedとしてPrimary候補にする。最新Attemptで古い単発弱点を明確に克服した場合はresolvedとする。

Question MixはPrimary / Secondary / Diagnosticに分ける。Confirmed weaknessがある場合でもDiagnostic枠を残し、既知弱点の補強によって別の弱点が見えなくなることを防ぐ。Suspectedしかない場合はPrimaryを0とし、Diagnosticを過半にする。

External AI assessmentはquestion_feedbackへ `error_type` と `weakness_topics` を返す。calculation_slip / carelessはconcept_gap等より弱いSignalとして扱う。原因を回答から確認できない場合はunknownを使用し、AIに誤答理由を断定させない。

Question Bank selectorは `bank-v2-balanced` へ更新し、Primary / Secondary / Diagnostic quotaとdomain round-robinを利用する。External AI selectorは `prompt-v41.4-calibrated` とし、Canoviaが決めたExam ProfileとQuestion MixをPromptへ渡す。

AIのnext_step.focus_topicsは候補Signalとして残すが、次回演習方針を直接決定しない。最終的な出題配分はCanovia Policyが決める。


## 17. V41.5 Economy Foundation

V41.5は課金処理を導入せず、将来のPremium Core + Purpose Packを既存Entitlement境界へ接続できる基盤を実装する。

Canonical Products:

- `premium_core`
- `study_pack`
- `career_pack`
- `developer_pack`
- `creator_pack`
- `all_access`
- `ai_capacity_boost`

Product Grantは `user_product_grants` にProvider非依存の投影として保存する。raw receiptや課金Provider固有payloadはここへ保存しない。

Purpose PackはPremium Coreを前提とし、All AccessはPremium Core + Purpose Packsへ展開する。AI Capacity Boostは別軸でありAll Accessへ自動包含しない。

V41.5で追加するCapability-level FeatureKey:

- `study_long_term_weakness_profile`
- `career_native_capture_analysis`
- `developer_github_evidence`

これらは将来Capability用であり、現在のAI Practice / Career manual capture / Project Artifact manual linkingをFreeから奪わない。

Admin Economy Inspectorでは、ユーザーごとのProduct Grant、effective Products、FeatureAccessDecision、AI Capacity、決定論的推薦を確認し、開発用Grantを手動付与/解除できる。

Public Pricing UIは、実際のPremium価値と購入経路が成立するまで追加しない。
