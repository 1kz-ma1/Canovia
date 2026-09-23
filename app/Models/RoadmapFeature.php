<?php

namespace App\Models;

use App\Enums\RoadmapFeatureStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoadmapFeature extends Model
{
    protected $fillable = [
        'feature_key',
        'title',
        'description',
        'threshold',
        'status',
        'sort_order',
        'is_published',
        'voting_enabled',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold' => 'integer',
            'status' => RoadmapFeatureStatus::class,
            'sort_order' => 'integer',
            'is_published' => 'boolean',
            'voting_enabled' => 'boolean',
            'released_at' => 'datetime',
        ];
    }

    public function votes()
    {
        return $this->hasMany(RoadmapVote::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
