<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxItem extends Model
{
    public const SOURCE_TYPES = ['text', 'url', 'image', 'pdf'];
    public const STATUSES = ['new', 'review', 'processed', 'archived'];

    protected $fillable = [
        'user_id',
        'actor_token',
        'plan_id',
        'source_type',
        'status',
        'title',
        'content',
        'source_url',
        'storage_path',
        'mime_type',
        'original_name',
        'byte_size',
        'metadata',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'metadata' => 'array',
            'processed_at' => 'datetime',
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

    public function sourceLabel(): string
    {
        return match ($this->source_type) {
            'text' => 'テキスト',
            'url' => 'URL',
            'image' => '画像・スクリーンショット',
            'pdf' => 'PDF',
            default => 'Inbox',
        };
    }

    public function displayTitle(): string
    {
        if (filled($this->title)) {
            return (string) $this->title;
        }

        if (filled($this->original_name)) {
            return (string) $this->original_name;
        }

        if (filled($this->source_url)) {
            return (string) $this->source_url;
        }

        return str((string) $this->content)->squish()->limit(72)->toString() ?: 'Inbox Item';
    }
}
