<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyRecallItem extends Model
{
    protected $fillable = [
        'plan_id',
        'task_id',
        'prompt',
        'answer',
        'note',
        'tags',
        'fingerprint',
        'repetitions',
        'lapse_count',
        'interval_days',
        'ease_factor',
        'due_at',
        'last_reviewed_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'repetitions' => 'integer',
            'lapse_count' => 'integer',
            'interval_days' => 'integer',
            'ease_factor' => 'float',
            'due_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
            'is_active' => 'boolean',
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

    public function reviews()
    {
        return $this->hasMany(StudyRecallReview::class);
    }

    public function isDue(): bool
    {
        return $this->is_active && ($this->due_at === null || $this->due_at->lte(now()));
    }

    public function isMastered(): bool
    {
        return $this->repetitions >= 3 && $this->interval_days >= 7;
    }
}
