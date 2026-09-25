<?php

namespace App\Contracts;

use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Collection;

interface StudyPracticeQuestionProvider
{
    public function key(): string;

    public function mode(): string;

    /**
     * @param array<string, mixed> $strategy
     * @return array<string, mixed>
     */
    public function prepare(Plan $plan, Task $task, Collection $recentAttempts, array $strategy, ?int $actorUserId = null): array;
}
