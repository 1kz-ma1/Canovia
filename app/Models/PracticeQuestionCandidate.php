<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PracticeQuestionCandidate extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PROMOTED = 'promoted';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_REJECTED,
        self::STATUS_PROMOTED,
    ];

    protected $fillable = [
        'fingerprint',
        'status',
        'provider',
        'model',
        'exam_profile_key',
        'first_practice_question_demand_id',
        'latest_practice_question_demand_id',
        'native_ai_run_id',
        'question_payload',
        'review_hints',
        'generation_count',
        'last_seen_at',
        'review_data',
        'promoted_question_pack_id',
        'promoted_question_id',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'question_payload' => 'array',
            'review_hints' => 'array',
            'generation_count' => 'integer',
            'last_seen_at' => 'datetime',
            'review_data' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function firstDemand()
    {
        return $this->belongsTo(PracticeQuestionDemand::class, 'first_practice_question_demand_id');
    }

    public function latestDemand()
    {
        return $this->belongsTo(PracticeQuestionDemand::class, 'latest_practice_question_demand_id');
    }

    public function promotedPack()
    {
        return $this->belongsTo(QuestionPack::class, 'promoted_question_pack_id');
    }

    public function promotedQuestion()
    {
        return $this->belongsTo(Question::class, 'promoted_question_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
