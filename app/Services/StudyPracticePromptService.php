<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Task;

class StudyPracticePromptService
{
    public function generationPrompt(Plan $plan, Task $task): string
    {
        $planDescription = trim((string) ($plan->description ?? '')) ?: '未設定';
        $taskDescription = trim((string) ($task->description ?? '')) ?: '未設定';

        return <<<PROMPT
あなたはCanoviaの学習演習作成AIです。
以下のPlanとTaskを達成するために、理解度を確認できる演習問題を作成してください。

【Plan】
plan_id: {$plan->id}
タイトル: {$plan->title}
カテゴリ: {$plan->category}
説明: {$planDescription}

【Task】
task_id: {$task->id}
タイトル: {$task->title}
説明: {$taskDescription}
現在進捗: {$task->progress_percent}%
残り想定時間: {$task->remaining_minutes}分

【目的】
- このTaskの達成に直接役立つ問題を10問前後作る
- 単なる暗記だけでなく、可能なら理解・判断・計算も含める
- 難易度は現在のTask内容に合わせる
- 問題文だけで解答に必要な条件が分かるようにする
- 正解や解説はこのJSONには含めない。採点は回答後に別途依頼する

【出力ルール】
説明文やMarkdownを付けず、JSONだけを返してください。
flow、plan_id、task_idは下記から変更しないでください。
typeは single_choice / multiple_choice / text / number のいずれかです。
single_choice / multiple_choice では choices を2〜6件付け、各choiceにidとlabelを付けてください。
text / number では choices は空配列にしてください。

{
  "schema_version": "1.0",
  "flow": "study_practice",
  "target_plan": {
    "id": {$plan->id}
  },
  "target_task": {
    "id": {$task->id}
  },
  "title": "演習タイトル",
  "questions": [
    {
      "id": "q1",
      "type": "single_choice",
      "prompt": "問題文",
      "choices": [
        {"id": "A", "label": "選択肢A"},
        {"id": "B", "label": "選択肢B"}
      ]
    }
  ]
}
PROMPT;
    }

    /**
     * @param array<int, array<string, mixed>> $questions
     * @param array<int, array<string, mixed>> $answers
     */
    public function evaluationPrompt(Plan $plan, Task $task, array $questions, array $answers): string
    {
        $questionsJson = json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $answersJson = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        return <<<PROMPT
あなたはCanoviaの学習評価AIです。
下記のPlan・Taskに対して実施した演習を採点し、学習成果を評価してください。

【Plan】
plan_id: {$plan->id}
タイトル: {$plan->title}

【Task】
task_id: {$task->id}
タイトル: {$task->title}
現在進捗: {$task->progress_percent}%

【出題内容】
{$questionsJson}

【ユーザー回答】
{$answersJson}

【評価方針】
- 各回答を問題文の条件に照らして評価する
- score_percentは0〜100の整数
- strengths / weaknesses は具体的な知識・思考内容を書く
- recommended_task_progress_percentは、今回の結果だけでなく現在進捗も踏まえた0〜100の整数
- next_actionは次に取るべき具体的な学習Actionを1つに絞る
- evidence_summaryには正答状況や判断根拠を簡潔に残す
- 推測でplan_idやtask_idを変更しない

説明文やMarkdownを付けず、JSONだけを返してください。

{
  "schema_version": "1.0",
  "flow": "study_assessment",
  "target_plan": {"id": {$plan->id}},
  "target_task": {"id": {$task->id}},
  "score_percent": 0,
  "strengths": ["理解できている点"],
  "weaknesses": ["補強すべき点"],
  "recommended_task_progress_percent": {$task->progress_percent},
  "evidence_summary": "評価根拠",
  "next_action": "次に行う具体的な学習"
}
PROMPT;
    }
}
