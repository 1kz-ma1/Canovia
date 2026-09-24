<?php

namespace App\Data;

final readonly class PlanSurfaceModuleData
{
    /**
     * @param array<string,mixed> $payload
     */
    public function __construct(
        public string $id,
        public string $view,
        public int $priority,
        public string $visibility,
        public string $reason,
        public array $payload = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'view' => $this->view,
            'priority' => $this->priority,
            'visibility' => $this->visibility,
            'reason' => $this->reason,
            'payload' => $this->payload,
        ];
    }
}
