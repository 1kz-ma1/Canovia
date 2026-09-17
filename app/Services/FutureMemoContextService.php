<?php

namespace App\Services;

use App\Models\FutureMemo;
use App\Models\User;
use Illuminate\Support\Collection;

class FutureMemoContextService
{
    public function memosFor(?User $user, int $limit = 12): Collection
    {
        if (! $user) {
            return collect();
        }

        return $user->futureMemos()
            ->where('use_for_ai', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function promptBlock(?User $user, int $limit = 12): string
    {
        $memos = $this->memosFor($user, $limit);

        if ($memos->isEmpty()) {
            return '';
        }

        return $memos
            ->map(function (FutureMemo $memo) {
                $category = $memo->category ? ' / ' . $memo->categoryLabel() : '';

                return sprintf('- %s%s: %s', $memo->kindLabel(), $category, trim($memo->content));
            })
            ->implode("\n");
    }
}
