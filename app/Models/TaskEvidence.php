<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskEvidence extends Model
{
    protected $table = 'task_evidences';

    protected $fillable = [
        'plan_id',
        'task_id',
        'user_id',
        'actor_token',
        'milestone_id',
        'source',
        'type',
        'provider',
        'provider_reference',
        'summary',
        'confidence',
        'observed_duration_seconds',
        'metadata',
        'fingerprint',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'observed_duration_seconds' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
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

    public function milestone()
    {
        return $this->belongsTo(TaskMilestone::class, 'milestone_id');
    }

    public function progressDecisions()
    {
        return $this->hasMany(TaskProgressDecision::class);
    }
}
