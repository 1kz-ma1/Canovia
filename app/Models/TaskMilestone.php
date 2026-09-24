<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskMilestone extends Model
{
    protected $fillable = [
        'task_id',
        'title',
        'description',
        'status',
        'weight',
        'sort_order',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'sort_order' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function evidences()
    {
        return $this->hasMany(TaskEvidence::class, 'milestone_id');
    }
}
