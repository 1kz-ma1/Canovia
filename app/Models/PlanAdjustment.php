<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanAdjustment extends Model
{
    protected $fillable = [
        'plan_id',
        'flow',
        'request_hash',
        'summary',
        'user_input',
        'prompt',
        'response_json',
        'applied_operations',
        'metrics_before',
        'metrics_after',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'user_input' => 'array',
            'applied_operations' => 'array',
            'metrics_before' => 'array',
            'metrics_after' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
