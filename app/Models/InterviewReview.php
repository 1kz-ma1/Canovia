<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterviewReview extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'plan_id',
        'career_application_id',
        'career_selection_event_id',
        'status',
        'context_snapshot',
        'insights',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'context_snapshot' => 'array',
            'insights' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function application()
    {
        return $this->belongsTo(CareerApplication::class, 'career_application_id');
    }

    public function selectionEvent()
    {
        return $this->belongsTo(CareerSelectionEvent::class, 'career_selection_event_id');
    }

    public function answers()
    {
        return $this->hasMany(InterviewReviewAnswer::class)->orderBy('sort_order')->orderBy('id');
    }

    public function answerFor(string $key): ?string
    {
        return $this->answers->firstWhere('question_key', $key)?->answer;
    }
}
