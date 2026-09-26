<?php

namespace App\Services;

use App\Models\StudyRecallItem;
use App\Models\StudyRecallReview;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StudyRecallSchedulerService
{
    public const RATINGS = ['again', 'hard', 'good', 'easy'];

    /**
     * @return array{review:StudyRecallReview,created:bool,item:StudyRecallItem}
     */
    public function review(
        StudyRecallItem $item,
        string $rating,
        string $reviewRequestId,
        ?int $userId = null,
        ?string $actorToken = null,
    ): array {
        if (! in_array($rating, self::RATINGS, true)) {
            throw new InvalidArgumentException('Unsupported recall rating.');
        }

        return DB::transaction(function () use ($item, $rating, $reviewRequestId, $userId, $actorToken) {
            $locked = StudyRecallItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $existing = StudyRecallReview::query()
                ->where('review_request_id', $reviewRequestId)
                ->first();

            if ($existing) {
                return [
                    'review' => $existing,
                    'created' => false,
                    'item' => $locked->fresh(),
                ];
            }

            $now = now();
            $beforeInterval = (int) $locked->interval_days;
            $beforeEase = (float) $locked->ease_factor;
            $beforeDue = $locked->due_at?->copy();
            $beforeRepetitions = (int) $locked->repetitions;

            [$repetitions, $lapses, $interval, $ease, $dueAt] = match ($rating) {
                'again' => [
                    0,
                    (int) $locked->lapse_count + 1,
                    0,
                    max(1.30, round($beforeEase - 0.20, 2)),
                    $now->copy()->addMinutes(10),
                ],
                'hard' => [
                    $beforeRepetitions + 1,
                    (int) $locked->lapse_count,
                    $beforeInterval <= 0 ? 1 : max(1, (int) ceil($beforeInterval * 1.20)),
                    max(1.30, round($beforeEase - 0.05, 2)),
                    null,
                ],
                'good' => [
                    $beforeRepetitions + 1,
                    (int) $locked->lapse_count,
                    $beforeRepetitions === 0
                        ? 1
                        : ($beforeRepetitions === 1
                            ? 3
                            : max(4, (int) round(max(1, $beforeInterval) * $beforeEase))),
                    $beforeEase,
                    null,
                ],
                'easy' => [
                    $beforeRepetitions + 1,
                    (int) $locked->lapse_count,
                    $beforeRepetitions === 0
                        ? 3
                        : ($beforeRepetitions === 1
                            ? 7
                            : max(7, (int) round(max(1, $beforeInterval) * ($beforeEase + 0.30)))),
                    min(3.00, round($beforeEase + 0.10, 2)),
                    null,
                ],
            };

            if ($dueAt === null) {
                $dueAt = $now->copy()->addDays($interval);
            }

            $locked->update([
                'repetitions' => $repetitions,
                'lapse_count' => $lapses,
                'interval_days' => $interval,
                'ease_factor' => $ease,
                'due_at' => $dueAt,
                'last_reviewed_at' => $now,
            ]);

            $review = StudyRecallReview::query()->create([
                'study_recall_item_id' => (int) $locked->id,
                'plan_id' => (int) $locked->plan_id,
                'task_id' => (int) $locked->task_id,
                'user_id' => $userId,
                'actor_token' => $userId ? null : $actorToken,
                'review_request_id' => $reviewRequestId,
                'rating' => $rating,
                'interval_before_days' => $beforeInterval,
                'interval_after_days' => $interval,
                'ease_before' => $beforeEase,
                'ease_after' => $ease,
                'due_before' => $beforeDue,
                'due_after' => $dueAt,
                'reviewed_at' => $now,
            ]);

            return [
                'review' => $review,
                'created' => true,
                'item' => $locked->fresh(),
            ];
        });
    }
}
