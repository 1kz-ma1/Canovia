<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'question_pack_id', 'external_key', 'source_type', 'source_reference',
        'prompt', 'response_schema', 'grading_rule', 'learning_metadata',
        'explanation', 'difficulty', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'response_schema' => 'array',
            'grading_rule' => 'array',
            'learning_metadata' => 'array',
            'difficulty' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function pack()
    {
        return $this->belongsTo(QuestionPack::class, 'question_pack_id');
    }
}
