# V41 Execution / Evidence Foundation

## Purpose

Canoviaの実行モデルを、Timer中心の

```text
Plan -> Task -> WorkSession / Timer -> WorkLog -> Progress
```

から、状態変化を中心に扱える

```text
Plan -> Task -> Execution Action -> Evidence -> Progress -> Next Action
                              \-> Focus Timer (optional)
```

へ拡張する。

V41では外部ツールの完全統合やAIによる自動計画変更までは行わない。
将来それらを安全に追加できるデータ境界と、今すぐ使えるHome UXを先に作る。

## Time policy

時間は削除しない。

- `estimated_minutes` / `remaining_minutes`: Taskの大きさ、今日できるか、期限に間に合うかを判断する**計画上の目安**
- WorkSessionの実時間: 実際にFocus Timerを使った場合に取得できる補助情報
- Progress: 時間の経過だけを達成証拠とはみなさない

そのためFocus Timerは標準Primary Actionではなく任意Toolとして扱う。

## Plan Hub

Homeの各Planタブは、Roadmapの縮小版ではなく日常利用用Plan Hubとする。

表示の優先順位:

1. Plan概要
2. CURRENT TASK / Next Action
3. Task短縮一覧
4. PLAN TOOLS
5. 最近の活動

Roadmapは専用画面へのリンクを残し、Home内では再描画しない。

Primary ActionはTaskに合うExecution Toolを優先する。

- 資格学習: AI Practice
- 制作: Artifact
- 資料利用: Resource
- 適切な専用Toolがない場合: Task / Plan確認
- Focus Timer: 常に任意Secondary Action

## Task Evidence

`task_evidences` は「Taskに対して現実に起きた観測事実」を保存する。

主要フィールド:

- plan_id / task_id
- user_id / actor_token
- source
- type
- external_key
- confidence
- occurred_at
- metadata

`external_key` があるEvidenceは `task_id + source + external_key` でidempotentに記録する。

### EvidenceSource

V41で予約するsource:

- native
- github
- file
- image
- calendar
- external

GitHub / file / image / calendarはV41では接続口のみ。OAuth、Webhook、ローカル監視、画像解析は別リリースで実装する。

## First native Evidence: AI Practice

AI Practice assessmentを保存した時点で `study_practice_assessed` Evidenceを自動作成する。

metadataには以下を保持する。

- StudyPracticeAttempt / Session ID
- score
- recommended Task progress
- evidence summary
- strengths / weaknesses
- next action / structured next_step

同じAttemptからは同じexternal_keyを使い、重複Evidenceを作らない。

## Conservative progress rule

`EvidenceProgressService` はV41では**提案だけ**を返し、Taskを自動更新しない。

最初のルール:

- high-confidenceな `study_practice_assessed`
- recommended progressを0..100へ正規化
- 現在進捗より低い値は返さない

Evidence収集と自動変更Policyを分離することで、将来AIを導入してもユーザー制御を維持する。

## Milestone boundary

`task_milestones` を追加し、将来Progressを単なる手入力率ではなく

```text
Evidence -> Milestone completion -> Progress
```

で計算できる境界を用意する。

V41ではMilestoneの自動生成・自動完了は行わない。

## ExecutionAdapter

`ExecutionAdapter` contractを追加する。

将来のAdapter例:

- GitHubAdapter
- FileAdapter
- ImageEvidenceAdapter
- CalendarAdapter

Adapterは「外部アプリをCanoviaへ内蔵する」ためではなく、外部環境で起きた状態変化をEvidenceへ変換する責務を持つ。

## Not in V41

- GitHub OAuth / Webhookの本接続
- Drive / OneDrive / Dropbox等の変更監視
- ローカルフォルダ監視
- 写真のAI解析
- Evidenceからの無確認Progress自動変更
- AIによるPlan全体の自動再構成

これらはEvidence foundationの上へ段階的に追加する。
