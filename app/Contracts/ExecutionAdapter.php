<?php

namespace App\Contracts;

use App\Enums\EvidenceSource;
use App\Models\Plan;
use App\Models\Task;

interface ExecutionAdapter
{
    public function key(): string;

    public function supports(Plan $plan, Task $task): bool;

    /**
     * Evidence sources this adapter may emit.
     *
     * @return array<int, EvidenceSource>
     */
    public function evidenceSources(): array;
}
