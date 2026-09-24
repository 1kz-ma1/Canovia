<?php

namespace App\Http\Controllers;

use App\Models\FutureMemo;
use App\Services\FutureMemoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FutureMemoController extends Controller
{
    public function index(Request $request, FutureMemoService $service)
    {
        return view('future_memos.index', [
            'memos' => $service->all($request),
            'kinds' => FutureMemo::KINDS,
            'categories' => FutureMemo::CATEGORIES,
        ]);
    }

    public function store(Request $request, FutureMemoService $service)
    {
        $validated = $request->validate([
            'kind' => ['required', Rule::in(array_keys(FutureMemo::KINDS))],
            'category' => ['nullable', Rule::in(array_keys(FutureMemo::CATEGORIES))],
            'content' => ['required', 'string', 'max:2000'],
            'use_for_ai' => ['nullable', 'boolean'],
        ]);

        $service->create($request, [
            'kind' => $validated['kind'],
            'category' => $validated['category'] ?? null,
            'content' => trim($validated['content']),
            'use_for_ai' => $request->boolean('use_for_ai', true),
        ]);

        return back()->with('success', '未来メモを追加しました。');
    }

    public function update(Request $request, FutureMemo $futureMemo, FutureMemoService $service)
    {
        $service->authorize($request, $futureMemo);
        $validated = $request->validate([
            'kind' => ['required', Rule::in(array_keys(FutureMemo::KINDS))],
            'category' => ['nullable', Rule::in(array_keys(FutureMemo::CATEGORIES))],
            'content' => ['required', 'string', 'max:2000'],
            'use_for_ai' => ['nullable', 'boolean'],
        ]);

        $futureMemo->update([
            'kind' => $validated['kind'],
            'category' => $validated['category'] ?? null,
            'content' => trim($validated['content']),
            'use_for_ai' => $request->boolean('use_for_ai'),
        ]);

        return back()->with('success', '未来メモを更新しました。');
    }

    public function destroy(Request $request, FutureMemo $futureMemo, FutureMemoService $service)
    {
        $service->authorize($request, $futureMemo);
        $futureMemo->delete();

        return back()->with('success', '未来メモを削除しました。');
    }

    public function organize(Request $request, FutureMemoService $service)
    {
        $memos = $service->all($request, true);
        $extraContext = trim((string) $request->session()->get('future_memo.organize_extra_context', ''));
        $prompt = $this->buildDiscoveryPrompt($service->promptContext($request), $extraContext);

        return view('future_memos.organize', [
            'memos' => $memos,
            'prompt' => $prompt,
            'extraContext' => $extraContext,
            'proposal' => $request->session()->get('future_memo.goal_proposal'),
        ]);
    }

    public function generatePrompt(Request $request, FutureMemoService $service)
    {
        $validated = $request->validate([
            'extra_context' => ['nullable', 'string', 'max:5000'],
        ]);
        $request->session()->put('future_memo.organize_extra_context', trim((string) ($validated['extra_context'] ?? '')));
        $request->session()->forget('future_memo.goal_proposal');

        return redirect()->route('future_memos.organize')->with('status', 'AIに相談する文章を更新しました。');
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'ai_json' => ['required', 'string', 'max:100000'],
        ]);

        $decoded = $this->decodeJson($validated['ai_json']);
        if (($decoded['flow'] ?? null) !== 'goal_discovery') {
            throw ValidationException::withMessages(['ai_json' => '未来メモ整理用のJSONではないようです。上の相談文から作った回答を貼り付けてください。']);
        }

        $candidates = collect($decoded['goal_candidates'] ?? [])->take(5)->map(function ($candidate) {
            if (! is_array($candidate)) {
                return null;
            }
            $title = trim((string) ($candidate['title'] ?? ''));
            if ($title === '') {
                return null;
            }
            return [
                'title' => mb_substr($title, 0, 255),
                'reason' => mb_substr(trim((string) ($candidate['reason'] ?? '')), 0, 1000),
                'timeframe' => mb_substr(trim((string) ($candidate['timeframe'] ?? '')), 0, 255),
                'first_step' => mb_substr(trim((string) ($candidate['first_step'] ?? '')), 0, 1000),
                'category' => mb_substr(trim((string) ($candidate['category'] ?? '')), 0, 100),
            ];
        })->filter()->values()->all();

        if ($candidates === []) {
            throw ValidationException::withMessages(['ai_json' => '目標候補を読み取れませんでした。AIにgoal_candidatesを含むJSONだけで返してもらってください。']);
        }

        $request->session()->put('future_memo.goal_proposal', [
            'summary' => mb_substr(trim((string) ($decoded['summary'] ?? '')), 0, 2000),
            'goal_candidates' => $candidates,
        ]);

        return redirect()->route('future_memos.organize')->with('status', 'AIの提案を読み込みました。候補を選んでください。');
    }

    public function candidateToMemo(Request $request, FutureMemoService $service)
    {
        $candidate = $this->validatedCandidate($request);
        $service->create($request, [
            'kind' => 'want_to_do',
            'category' => $this->mapCategory($candidate['category']),
            'content' => $candidate['title'] . ($candidate['reason'] ? "\n理由: {$candidate['reason']}" : ''),
            'use_for_ai' => true,
        ]);

        return redirect()->route('future_memos.index')->with('success', '目標候補を未来メモに残しました。');
    }

    public function candidateToPlan(Request $request)
    {
        $candidate = $this->validatedCandidate($request);
        $request->session()->put('plan_create_prefill', [
            'title' => $candidate['title'],
            'description' => collect([
                $candidate['reason'] ? 'この目標を選んだ理由: ' . $candidate['reason'] : null,
                $candidate['timeframe'] ? '希望時期の目安: ' . $candidate['timeframe'] : null,
                $candidate['first_step'] ? '最初の一歩: ' . $candidate['first_step'] : null,
            ])->filter()->implode("\n"),
            'category' => $this->mapPlanCategory($candidate['category']),
        ]);

        return redirect()->route('plans.create')->with('status', '目標候補を計画作成へ引き継ぎました。内容は自由に修正できます。');
    }

    private function validatedCandidate(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'timeframe' => ['nullable', 'string', 'max:255'],
            'first_step' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function mapCategory(?string $category): string
    {
        $value = mb_strtolower(trim((string) $category));
        foreach (FutureMemo::CATEGORIES as $key => $label) {
            if ($value === mb_strtolower($label) || $value === $key) {
                return $key;
            }
        }
        return 'other';
    }

    private function mapPlanCategory(?string $category): string
    {
        $value = trim((string) $category);

        return match ($value) {
            '勉強・資格', 'study' => '資格学習',
            '制作・開発', 'creation' => '個人開発',
            '就職・将来', 'career' => '就活・キャリア',
            default => 'その他',
        };
    }

    private function decodeJson(string $text): array
    {
        $trimmed = trim($text);
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $trimmed, $matches)) {
            $trimmed = trim($matches[1]);
        } elseif (! str_starts_with($trimmed, '{')) {
            $start = strpos($trimmed, '{');
            $end = strrpos($trimmed, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $trimmed = substr($trimmed, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($trimmed, true);
        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages(['ai_json' => 'JSONを読み取れませんでした。AIの最後の回答をそのまま貼り付けてください。']);
        }
        return $decoded;
    }

    private function buildDiscoveryPrompt(string $memoContext, string $extraContext): string
    {
        $extra = $extraContext !== '' ? $extraContext : '特になし';

        return <<<PROMPT
あなたはCanoviaの「次に進みたい方向」を整理するアシスタントです。
以下は本人がCanoviaへ保存した未来メモです。本人の意思を尊重し、一つの目標を勝手に決定しないでください。

【未来メモ】
{$memoContext}

【今回追加で伝えたいこと】
{$extra}

まず、この会話ですでに分かっている情報も利用してください。目標候補を出すために本質的な情報が不足している場合だけ、最大5問まで自由形式で質問してください。すでに答えたことは聞き直さないでください。
十分な情報が揃ったら、本人が選べる実行候補を3〜5件に整理してください。大きな夢だけでなく、資格取得、就活開始、生活改善、制作、習慣化のような現実的な候補も認めてください。

最後の回答は説明やMarkdownを付けず、次のJSONだけにしてください。
{
  "schema_version": "1.0",
  "flow": "goal_discovery",
  "summary": "本人の希望・制約・現在地の短い整理",
  "goal_candidates": [
    {
      "title": "本人が選べる具体的な目標候補",
      "reason": "未来メモや会話のどの情報とつながるか",
      "timeframe": "期間の目安。決められなければ未定",
      "first_step": "今すぐ始められる最初の一歩",
      "category": "就職・将来 / 勉強・資格 / 制作・開発 / 生活 / 健康 / お金 / 趣味 / その他"
    }
  ]
}
PROMPT;
    }
}
