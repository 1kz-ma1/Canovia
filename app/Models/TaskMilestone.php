<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskMilestone extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'task_id',
        'title',
        'status',
        'weight',
        'sort_order',
        'evidence_requirement',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'sort_order' => 'integer',
            'evidence_requirement' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
