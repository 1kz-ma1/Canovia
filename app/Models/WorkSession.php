<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSession extends Model
{
    protected $fillable = [
        'actor_token',
        'browser_session_id',
        'client_session_id',
        'start_request_id',
        'plan_id',
        'task_id',
        'status',
        'intended_minutes',
        'started_at',
        'paused_at',
        'ended_at',
        'actual_seconds',
        'paused_seconds',
        'source',
        'needs_plan_update',
        'plan_updated_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'ended_at' => 'datetime',
            'intended_minutes' => 'integer',
            'actual_seconds' => 'integer',
            'paused_seconds' => 'integer',
            'needs_plan_update' => 'boolean',
            'plan_updated_at' => 'datetime',
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
}
