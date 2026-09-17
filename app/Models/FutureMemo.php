<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FutureMemo extends Model
{
    use HasFactory;

    public const KINDS = [
        'want_to_do',
        'ideal_self',
        'concern',
        'value',
    ];

    public const CATEGORIES = [
        'career',
        'learning',
        'project',
        'life',
        'health',
        'money',
        'hobby',
        'other',
    ];

    protected $fillable = [
        'user_id',
        'kind',
        'category',
        'content',
        'use_for_ai',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'use_for_ai' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            'want_to_do' => 'やりたいこと',
            'ideal_self' => 'なりたい自分',
            'concern' => '気になっていること',
            'value' => '大事にしたいこと',
            default => '未来メモ',
        };
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            'career' => 'キャリア',
            'learning' => '学習・資格',
            'project' => '制作・開発',
            'life' => '生活',
            'health' => '健康',
            'money' => 'お金',
            'hobby' => '趣味',
            'other' => 'その他',
            default => '未分類',
        };
    }
}
