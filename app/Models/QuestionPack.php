<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionPack extends Model
{
    protected $fillable = [
        'slug', 'title', 'exam_code', 'subject', 'version', 'status',
        'downloadable', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'downloadable' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function questions()
    {
        return $this->hasMany(Question::class)->orderBy('sort_order')->orderBy('id');
    }
}
