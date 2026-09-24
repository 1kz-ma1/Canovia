<?php

namespace App\Models;

use App\Enums\EvidenceSource;
use Illuminate\Database\Eloquent\Model;

class TaskEvidence extends Model
{
    protected $fillable = [
        'plan_id',
        'task_id',
        'user_id',
        'actor_token',
        'source',
        'type',
        'external_key',
        'confidence',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source' => EvidenceSource::class,
            'confidence' => 'float',
            'occurred_at' => 'datetime',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
