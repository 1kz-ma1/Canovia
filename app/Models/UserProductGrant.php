<?php

namespace App\Models;

use App\Enums\ProductKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserProductGrant extends Model
{
    protected $fillable = [
        'user_id',
        'product_key',
        'source',
        'starts_at',
        'expires_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'product_key' => ProductKey::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query, $at = null): Builder
    {
        $at ??= now();

        return $query
            ->where(function (Builder $query) use ($at) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $query) use ($at) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $at);
            });
    }
}
