<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CareerCapture extends Model
{
    public const SOURCES = ['screenshot', 'url', 'email', 'calendar', 'manual'];
    public const STATUSES = ['pending', 'linked', 'ignored'];

    protected $fillable = [
        'plan_id',
        'career_application_id',
        'user_id',
        'actor_token',
        'source_type',
        'status',
        'source_url',
        'screenshot_path',
        'screenshot_mime',
        'screenshot_original_name',
        'raw_text',
        'extracted_data',
        'confidence',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'extracted_data' => 'array',
            'confidence' => 'float',
            'captured_at' => 'datetime',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function application()
    {
        return $this->belongsTo(CareerApplication::class, 'career_application_id');
    }

    public function payload()
    {
        return $this->hasOne(CareerCapturePayload::class);
    }

    public function sourceLabel(): string
    {
        return match ($this->source_type) {
            'screenshot' => 'スクリーンショット',
            'url' => '求人URL',
            'email' => 'メール',
            'calendar' => 'カレンダー',
            'manual' => '手動',
            default => '外部情報',
        };
    }
}
