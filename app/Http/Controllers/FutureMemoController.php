<?php

namespace App\Http\Controllers;

use App\Models\FutureMemo;
use App\Services\FutureMemoContextService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FutureMemoController extends Controller
{
    public function index(Request $request)
    {
        $memos = $request->user()
            ->futureMemos()
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return view('future_memos.index', compact('memos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $request->user()->futureMemos()->create([
            'kind' => $validated['kind'],
            'category' => $validated['category'] ?? null,
            'content' => trim($validated['content']),
            'use_for_ai' => $request->boolean('use_for_ai'),
            'sort_order' => (int) $request->user()->futureMemos()->max('sort_order') + 1,
        ]);

        $request->user()->forceFill(['future_memo_hint_snoozed_until' => null])->save();

        return back()->with('status', '未来メモを保存しました。');
    }

    public function update(Request $request, FutureMemo $futureMemo)
    {
        $this->authorizeMemo($request, $futureMemo);
        $validated = $request->validate($this->rules());

        $futureMemo->update([
            'kind' => $validated['kind'],
            'category' => $validated['category'] ?? null,
            'content' => trim($validated['content']),
            'use_for_ai' => $request->boolean('use_for_ai'),
        ]);

        return back()->with('status', '未来メモを更新しました。');
    }

    public function destroy(Request $request, FutureMemo $futureMemo)
    {
        $this->authorizeMemo($request, $futureMemo);
        $futureMemo->delete();

        return back()->with('status', '未来メモを削除しました。');
    }

    public function snoozeHint(Request $request)
    {
        $request->user()->forceFill([
            'future_memo_hint_snoozed_until' => now()->addDays(7),
        ])->save();

        return back()->with('status', '未来メモの案内は7日後まで非表示にします。');
    }

    public function assistant(Request $request)
    {
        $memos = $request->user()
            ->futureMemos()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('future_memos.assistant', [
            'memos' => $memos,
            'draft' => $request->session()->get('future_memo_assistant.draft'),
            'proposal' => $request->session()->get('future_memo_assistant.proposal'),
        ]);
    }

    public function generatePrompt(Request $request, FutureMemoContextService $context)
    {
        $validated = $request->validate([
            'current_state' => ['nullable', 'string', 'max:3000'],
            'available_time' => ['nullable', 'string', 'max:1000'],
            'constraints' => ['nullable', 'string', 'max:2000'],
        ]);

        $memoBlock = $context->promptBlock($request->user(), 20);
        $currentState = trim((string) ($validated['current_state'] ?? '')) ?: '特になし。必要なら現在の会話から確認してください。';
        $availableTime = trim((string) ($validated['available_time'] ?? '')) ?: '未指定';
        $constraints = trim((string) ($validated['constraints'] ?? '')) ?: '未指定';
        $memoText = $memoBlock !== '' ? $memoBlock : '- 未来メモはまだ登録されていません';

        $prompt = <<<PROMPT
あなたはCanoviaで「次に進みたい方向」を整理する伴走アシスタントです。
ユーザーの意思を尊重し、一つの正解や目標を勝手に決めないでください。

以下にはCanoviaへ本人が保存した未来メモが含まれます。これは「こうなりたい・やってみたい」という本人の希望を理解するための材料です。
現在の相談と関係が薄いメモは無理に使わず、関連する内容だけを判断材料にしてください。

【未来メモ】
{$memoText}

【今の状況】
{$currentState}

【使えそうな時間】
{$availableTime}

【避けたいこと・制約】
{$constraints}

進め方:
1. 現在の会話ですでに分かっている情報を優先し、同じことを聞き直さないでください。
2. 方向性を提案するために重要な情報が不足している場合だけ、最大5問まで質問してください。
3. 大きな夢に限定せず、資格、就活、習慣、制作、生活改善など、本人が現実に選べる候補を扱ってください。
4. 候補ごとに「なぜ合いそうか」「おおよその期間」「最初の一歩」を簡潔に示してください。
5. 本人に選択肢を残してください。

十分な情報が揃ったら、最後の回答は説明やMarkdownを付けず、次のJSONだけにしてください。
{
  "schema_version": "1.0",
  "flow": "future_direction",
  "summary": "今回整理できた方向性の要約",
  "goal_candidates": [
    {
      "title": "計画にできる目標候補",
      "reason": "本人の希望・状況とのつながり",
      "timeframe": "目安期間。例: 3か月",
      "first_step": "最初に行う具体的な一歩",
      "category": "career|learning|project|life|health|money|hobby|other"
    }
  ]
}

候補は1〜5件にしてください。確信が低い場合は断定せず、その理由もreasonに含めてください。
PROMPT;

        $request->session()->put('future_memo_assistant.draft', [
            'current_state' => $validated['current_state'] ?? '',
            'available_time' => $validated['available_time'] ?? '',
            'constraints' => $validated['constraints'] ?? '',
            'prompt' => $prompt,
        ]);
        $request->session()->forget('future_memo_assistant.proposal');

        return redirect()->route('future_memos.assistant')->with('status', 'AIへ送る相談文を生成しました。');
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'response_json' => ['required', 'string', 'max:100000'],
        ]);

        $json = $this->extractJson($validated['response_json']);
        $decoded = json_decode($json, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                'response_json' => 'AIの回答からJSONを読み取れませんでした。最後の回答をそのまま貼り付けてください。',
            ]);
        }

        if ((string) ($decoded['schema_version'] ?? '') !== '1.0' || ($decoded['flow'] ?? null) !== 'future_direction') {
            throw ValidationException::withMessages([
                'response_json' => 'Canoviaの方向整理用JSONではないようです。この画面の相談文から作った最後の回答を貼り付けてください。',
            ]);
        }

        $candidates = $decoded['goal_candidates'] ?? null;
        if (! is_array($candidates) || count($candidates) < 1 || count($candidates) > 5) {
            throw ValidationException::withMessages([
                'response_json' => 'goal_candidatesは1〜5件にしてください。',
            ]);
        }

        $normalized = collect($candidates)->map(function ($candidate, $index) {
            if (! is_array($candidate)) {
                throw ValidationException::withMessages(['response_json' => ($index + 1) . '件目の候補を読み取れませんでした。']);
            }

            $title = trim((string) ($candidate['title'] ?? ''));
            $reason = trim((string) ($candidate['reason'] ?? ''));
            $timeframe = trim((string) ($candidate['timeframe'] ?? ''));
            $firstStep = trim((string) ($candidate['first_step'] ?? ''));
            $category = trim((string) ($candidate['category'] ?? 'other'));

            if ($title === '' || mb_strlen($title) > 255 || mb_strlen($reason) > 2000 || mb_strlen($timeframe) > 255 || mb_strlen($firstStep) > 1000) {
                throw ValidationException::withMessages(['response_json' => ($index + 1) . '件目の候補内容が正しくありません。']);
            }

            if (! in_array($category, FutureMemo::CATEGORIES, true)) {
                $category = 'other';
            }

            return [
                'title' => $title,
                'reason' => $reason,
                'timeframe' => $timeframe,
                'first_step' => $firstStep,
                'category' => $category,
            ];
        })->values()->all();

        $request->session()->put('future_memo_assistant.proposal', [
            'summary' => trim((string) ($decoded['summary'] ?? '')),
            'goal_candidates' => $normalized,
        ]);

        return redirect()->route('future_memos.assistant')->with('status', 'AIの候補を読み込みました。選んで計画にできます。');
    }

    public function reset(Request $request)
    {
        $request->session()->forget([
            'future_memo_assistant.draft',
            'future_memo_assistant.proposal',
        ]);

        return redirect()->route('future_memos.assistant')->with('status', '方向整理の入力をリセットしました。');
    }

    private function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(FutureMemo::KINDS)],
            'category' => ['nullable', Rule::in(FutureMemo::CATEGORIES)],
            'content' => ['required', 'string', 'max:2000'],
            'use_for_ai' => ['nullable', 'boolean'],
        ];
    }

    private function authorizeMemo(Request $request, FutureMemo $futureMemo): void
    {
        abort_unless((int) $futureMemo->user_id === (int) $request->user()->id, 404);
    }

    private function extractJson(string $text): string
    {
        $trimmed = trim($text);

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $trimmed, $matches)) {
            return trim($matches[1]);
        }

        if (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}')) {
            return $trimmed;
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        return $start !== false && $end !== false && $end > $start
            ? trim(substr($trimmed, $start, $end - $start + 1))
            : $trimmed;
    }
}
