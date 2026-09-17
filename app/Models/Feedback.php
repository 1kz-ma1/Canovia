<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    // "feedback" is an uncountable word, so Laravel does not pluralize it.
    // The existing migration intentionally uses the `feedbacks` table.
    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id',
        'actor_token',
        'plan_id',
        'task_id',
        'type',
        'rating',
        'message',
        'page',
        'app_version',
        'context',
        'status',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'context' => 'array',
            'archived_at' => 'datetime',
        ];
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function releaseNote()
    {
        return $this->hasOne(ReleaseNote::class);
    }

}
