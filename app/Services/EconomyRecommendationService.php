<?php

namespace App\Services;

use App\Enums\ProductKey;
use App\Models\CareerCapture;
use App\Models\InterviewReview;
use App\Models\PlanArtifact;
use App\Models\StudyPracticeAttempt;
use App\Models\User;

class EconomyRecommendationService
{
    public function __construct(
        private readonly PlanCategoryProfileService $categoryProfiles,
    ) {}

    /**
     * Deterministic, minimal-sufficient recommendation.
     * This service optimizes fit and friction reduction, not revenue.
     *
     * @return array<string,mixed>
     */
    public function recommend(User $user): array
    {
        $plans = $user->plans()->get();
        $domainPlanIds = [
            'study' => collect(),
            'career' => collect(),
            'development' => collect(),
            'creative' => collect(),
        ];

        foreach ($plans as $plan) {
            $key = $this->categoryProfiles->forPlan($plan)->key;
            if (array_key_exists($key, $domainPlanIds)) {
                $domainPlanIds[$key]->push((int) $plan->id);
            }
        }

        $domainPlanCounts = collect($domainPlanIds)
            ->map(fn ($ids) => $ids->count())
            ->all();

        $studyAttempts = StudyPracticeAttempt::query()
            ->where('user_id', $user->id)
            ->whereIn('plan_id', $domainPlanIds['study'])
            ->count();

        $careerCaptures = CareerCapture::query()
            ->where('user_id', $user->id)
            ->whereIn('plan_id', $domainPlanIds['career'])
            ->count();

        $completedInterviewReviews = InterviewReview::query()
            ->whereIn('plan_id', $domainPlanIds['career'])
            ->whereNotNull('completed_at')
            ->count();

        $artifactCount = PlanArtifact::query()
            ->whereIn('plan_id', $domainPlanIds['development'])
            ->count();

        $githubArtifactCount = PlanArtifact::query()
            ->whereIn('plan_id', $domainPlanIds['development'])
            ->where('provider', 'github')
            ->count();

        $packReasons = [];

        if ($studyAttempts >= 3 || ($domainPlanCounts['study'] >= 2 && $studyAttempts >= 1)) {
            $packReasons[ProductKey::StudyPack->value] =
                "AI演習 {$studyAttempts} 回と学習Plan {$domainPlanCounts['study']} 件の利用があり、長期的な弱点追跡を自動化する価値があります。";
        }

        if ($careerCaptures >= 2 || $completedInterviewReviews >= 1) {
            $packReasons[ProductKey::CareerPack->value] =
                "Career Capture {$careerCaptures} 件、完了Interview Review {$completedInterviewReviews} 件があり、取り込み・横断分析の自動化が有効です。";
        }

        if ($githubArtifactCount >= 1 || $artifactCount >= 2) {
            $packReasons[ProductKey::DeveloperPack->value] =
                "制作Artifact {$artifactCount} 件（GitHub {$githubArtifactCount} 件）があり、開発Evidenceの自動取得を減摩擦化できます。";
        }

        $purposePacks = array_keys($packReasons);
        $recommended = [];
        $reasons = [];

        if (count($purposePacks) >= 3) {
            $recommended = [ProductKey::AllAccess->value];
            $reasons[ProductKey::AllAccess->value] =
                'Study・Career・Developerの複数領域で継続利用が確認できるため、各領域を一つの構成で扱う候補です。';
        } elseif ($purposePacks !== []) {
            $recommended[] = ProductKey::PremiumCore->value;
            $reasons[ProductKey::PremiumCore->value] =
                '現在使っている領域の手作業をNative AI・自動化へ置き換える土台として必要です。';

            foreach ($purposePacks as $pack) {
                $recommended[] = $pack;
                $reasons[$pack] = $packReasons[$pack];
            }
        }

        $unused = collect([
            ProductKey::StudyPack->value => '学習の継続利用シグナルがまだ十分ではありません。',
            ProductKey::CareerPack->value => 'Career Capture / Interview Reviewの継続利用シグナルがまだ十分ではありません。',
            ProductKey::DeveloperPack->value => '開発Artifact / GitHub利用シグナルがまだ十分ではありません。',
            ProductKey::CreatorPack->value => 'Creator Packは実機能が固まるまで推薦対象にしません。',
        ])->except($purposePacks)->all();

        return [
            'recommended_products' => $recommended,
            'why' => $reasons,
            'unused_products' => $unused,
            'free_is_sufficient' => $recommended === [],
            'signal_strength' => [
                'study' => [
                    'plan_count' => $domainPlanCounts['study'],
                    'practice_attempts' => $studyAttempts,
                ],
                'career' => [
                    'plan_count' => $domainPlanCounts['career'],
                    'captures' => $careerCaptures,
                    'completed_interview_reviews' => $completedInterviewReviews,
                ],
                'developer' => [
                    'plan_count' => $domainPlanCounts['development'],
                    'artifacts' => $artifactCount,
                    'github_artifacts' => $githubArtifactCount,
                ],
                'creator' => [
                    'plan_count' => $domainPlanCounts['creative'],
                ],
            ],
        ];
    }
}
