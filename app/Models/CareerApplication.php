<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareerApplication extends Model
{
    public const STAGES = [
        'candidate',
        'preparing',
        'applied',
        'screening',
        'interview',
        'final_interview',
        'offer',
        'closed',
    ];

    public const STATUSES = ['active', 'waiting', 'completed', 'withdrawn'];

    protected $fillable = [
        'plan_id',
        'company_name',
        'company_website',
        'role_title',
        'stage',
        'status',
        'source',
        'applied_at',
        'next_event_at',
        'result',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'applied_at' => 'date',
            'next_event_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function captures()
    {
        return $this->hasMany(CareerCapture::class);
    }

    public function selectionEvents()
    {
        return $this->hasMany(CareerSelectionEvent::class)->orderBy('scheduled_at')->orderBy('id');
    }

    public function reviews()
    {
        return $this->hasMany(InterviewReview::class);
    }

    public function stageLabel(): string
    {
        return match ($this->stage) {
            'candidate' => '候補',
            'preparing' => '応募準備',
            'applied' => '応募済み',
            'screening' => '書類選考',
            'interview' => '面接中',
            'final_interview' => '最終面接',
            'offer' => '内定・オファー',
            'closed' => '終了',
            default => '未分類',
        };
    }
}
