<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyPracticeSession extends Model
{
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_AWAITING_PROVIDER = 'awaiting_provider';
    public const STATUS_READY = 'ready';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_ASSESSED = 'assessed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ABANDONED = 'abandoned';

    protected $fillable = [
        'plan_id',
        'task_id',
        'user_id',
        'actor_token',
        'session_token',
        'prepare_request_id',
        'status',
        'strategy',
        'strategy_version',
        'selector_type',
        'selector_version',
        'question_provider',
        'question_provider_mode',
        'assessment_provider',
        'assessment_provider_mode',
        'selection_context',
        'provider_payload',
        'selected_questions',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'selection_context' => 'array',
            'provider_payload' => 'array',
            'selected_questions' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
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

    public function attempts()
    {
        return $this->hasMany(StudyPracticeAttempt::class);
    }
}
