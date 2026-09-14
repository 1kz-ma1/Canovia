<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PwaHandoff extends Model
{
    protected $fillable = [
        'token_hash',
        'user_id',
        'guest_plan_ids',
        'expires_at',
        'used_at',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'guest_plan_ids' => 'array',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
