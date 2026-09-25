<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterviewReviewAnswer extends Model
{
    protected $fillable = [
        'interview_review_id',
        'question_key',
        'prompt',
        'answer',
        'source',
        'sort_order',
    ];

    public function review()
    {
        return $this->belongsTo(InterviewReview::class, 'interview_review_id');
    }
}
