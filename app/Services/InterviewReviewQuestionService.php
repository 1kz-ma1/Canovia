<?php

namespace App\Services;

use App\Models\CareerSelectionEvent;
use App\Models\InterviewReview;

class InterviewReviewQuestionService
{
    /**
     * @return array<int,array{key:string,prompt:string,source:string}>
     */
    public function questions(CareerSelectionEvent $event): array
    {
        $event->loadMissing('application.plan');
        $application = $event->application;

        $questions = [];

        $previous = InterviewReview::query()
            ->with('answers')
            ->where('plan_id', $application->plan_id)
            ->where('status', InterviewReview::STATUS_COMPLETED)
            ->where('career_selection_event_id', '!=', $event->id)
            ->latest('completed_at')
            ->latest('id')
            ->first();

        $previousFocus = trim((string) ($previous?->answerFor('next_focus') ?? ''));
        if ($previousFocus !== '') {
            $questions[] = [
                'key' => 'previous_focus',
                'prompt' => '前回は「'.mb_substr($previousFocus, 0, 180).'」を次に意識すると振り返っていました。今回は実践できましたか？',
                'source' => 'rule_context',
            ];
        }

        $questions[] = [
            'key' => 'best_moment',
            'prompt' => '今回、一番うまく伝えられたと思うことは何でしたか？',
            'source' => 'rule',
        ];
        $questions[] = [
            'key' => 'asked_questions',
            'prompt' => '覚えている範囲で、実際に聞かれた質問を残しておきますか？',
            'source' => 'rule',
        ];
        $questions[] = [
            'key' => 'difficult_moment',
            'prompt' => '一番答えにくかった質問や、引っかかった場面はどこでしたか？',
            'source' => 'rule',
        ];
        $questions[] = [
            'key' => 'redo_answer',
            'prompt' => '同じ質問をもう一度受けるなら、どこをどう答え直したいですか？',
            'source' => 'rule',
        ];
        $questions[] = [
            'key' => 'company_impression',
            'prompt' => '面接前と比べて、この企業や仕事への印象はどう変わりましたか？',
            'source' => 'rule',
        ];

        if ($event->stage === 'final_interview') {
            $questions[] = [
                'key' => 'decision_check',
                'prompt' => 'もし内定した場合、入社を判断する前に確認しておきたいことはありますか？',
                'source' => 'rule_stage',
            ];
        }

        $questions[] = [
            'key' => 'next_focus',
            'prompt' => '次の面接で一つだけ改善するとしたら、何を意識しますか？',
            'source' => 'rule',
        ];

        return array_values($questions);
    }
}
