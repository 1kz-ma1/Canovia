<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyRecallReview extends Model
{
    protected $fillable = [
        'study_recall_item_id',
        'plan_id',
        'task_id',
        'user_id',
        'actor_token',
        'review_request_id',
        'rating',
        'interval_before_days',
        'interval_after_days',
        'ease_before',
        'ease_after',
        'due_before',
        'due_after',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'interval_before_days' => 'integer',
            'interval_after_days' => 'integer',
            'ease_before' => 'float',
            'ease_after' => 'float',
            'due_before' => 'datetime',
            'due_after' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function item()
    {
        return $this->belongsTo(StudyRecallItem::class, 'study_recall_item_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
