<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NativeAiRun extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'task_id',
        'study_practice_session_id',
        'feature_key',
        'purpose',
        'provider',
        'model',
        'status',
        'request_hash',
        'provider_response_id',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'error_code',
        'error_message',
        'metadata',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
