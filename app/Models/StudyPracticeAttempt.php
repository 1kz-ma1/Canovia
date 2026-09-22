<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyPracticeAttempt extends Model
{
    protected $fillable = [
        'study_practice_session_id',
        'plan_id',
        'task_id',
        'user_id',
        'actor_token',
        'request_hash',
        'exercise_title',
        'questions',
        'answers',
        'assessment',
        'score_percent',
        'strengths',
        'weaknesses',
        'recommended_task_progress_percent',
        'progress_before_percent',
        'progress_after_percent',
        'evidence_summary',
        'next_action',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'answers' => 'array',
            'assessment' => 'array',
            'score_percent' => 'integer',
            'strengths' => 'array',
            'weaknesses' => 'array',
            'recommended_task_progress_percent' => 'integer',
            'progress_before_percent' => 'integer',
            'progress_after_percent' => 'integer',
            'applied_at' => 'datetime',
        ];
    }

    public function practiceSession()
    {
        return $this->belongsTo(StudyPracticeSession::class, 'study_practice_session_id');
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
