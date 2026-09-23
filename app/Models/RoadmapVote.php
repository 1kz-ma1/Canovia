<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoadmapVote extends Model
{
    protected $fillable = [
        'roadmap_feature_id',
        'user_id',
        'actor_token',
        'voter_key',
    ];

    public function feature()
    {
        return $this->belongsTo(RoadmapFeature::class, 'roadmap_feature_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
