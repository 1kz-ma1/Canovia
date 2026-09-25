<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareerSelectionEvent extends Model
{
    public const TYPES = ['interview', 'screening', 'offer', 'other'];
    public const STATUSES = ['scheduled', 'completed', 'result_waiting', 'cancelled'];

    protected $fillable = [
        'career_application_id',
        'task_id',
        'source_capture_id',
        'type',
        'stage',
        'status',
        'scheduled_at',
        'completed_at',
        'result',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function application()
    {
        return $this->belongsTo(CareerApplication::class, 'career_application_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function sourceCapture()
    {
        return $this->belongsTo(CareerCapture::class, 'source_capture_id');
    }

    public function interviewReview()
    {
        return $this->hasOne(InterviewReview::class);
    }

    public function stageLabel(): string
    {
        return match ($this->stage) {
            'interview' => '面接',
            'final_interview' => '最終面接',
            'screening' => '選考',
            default => '選考',
        };
    }
}
