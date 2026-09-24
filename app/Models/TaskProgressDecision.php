<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskProgressDecision extends Model
{
    protected $fillable = [
        'task_id',
        'task_evidence_id',
        'user_id',
        'actor_token',
        'source',
        'status',
        'progress_before_percent',
        'progress_after_percent',
        'reason',
        'metadata',
        'decision_key',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_before_percent' => 'integer',
            'progress_after_percent' => 'integer',
            'metadata' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function evidence()
    {
        return $this->belongsTo(TaskEvidence::class, 'task_evidence_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
