<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PracticeQuestionDemand extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'task_id',
        'study_practice_session_id',
        'question_pack_id',
        'prepare_request_id',
        'strategy_key',
        'exam_profile_key',
        'assembly_mode',
        'generation_provider',
        'requested_count',
        'bank_selected_count',
        'generated_requested_count',
        'generated_count',
        'focus_topics',
        'coverage',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'requested_count' => 'integer',
            'bank_selected_count' => 'integer',
            'generated_requested_count' => 'integer',
            'generated_count' => 'integer',
            'focus_topics' => 'array',
            'coverage' => 'array',
            'metadata' => 'array',
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

    public function session()
    {
        return $this->belongsTo(StudyPracticeSession::class, 'study_practice_session_id');
    }

    public function questionPack()
    {
        return $this->belongsTo(QuestionPack::class);
    }
}
