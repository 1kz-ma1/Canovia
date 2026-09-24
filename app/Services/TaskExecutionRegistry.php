<?php

namespace App\Services;

class TaskExecutionRegistry
{
    /**
     * Execution is intentionally separate from evidence.
     * A tool may help the user act without being able to prove the outcome.
     *
     * @return array<string, mixed>
     */
    public function descriptor(string $toolId): array
    {
        return match ($toolId) {
            'ai_practice' => [
                'execution_mode' => 'native',
                'evidence_mode' => 'automatic',
                'evidence_source' => 'study_practice',
                'primary_eligible' => true,
            ],
            'timer' => [
                'execution_mode' => 'native',
                'evidence_mode' => 'automatic',
                'evidence_source' => 'focus_timer',
                'primary_eligible' => false,
            ],
            'artifacts' => [
                'execution_mode' => 'connected',
                'evidence_mode' => 'linked',
                'evidence_source' => 'artifact',
                'primary_eligible' => true,
            ],
            'resources' => [
                'execution_mode' => 'external',
                'evidence_mode' => 'none',
                'evidence_source' => null,
                'primary_eligible' => true,
            ],
            default => [
                'execution_mode' => 'external',
                'evidence_mode' => 'none',
                'evidence_source' => null,
                'primary_eligible' => false,
            ],
        };
    }

    /**
     * Reserved evidence-source vocabulary for future connectors.
     *
     * @return array<string, array<string, mixed>>
     */
    public function evidenceSources(): array
    {
        return [
            'native' => ['automatic' => true, 'examples' => ['study_practice', 'focus_timer']],
            'github' => ['automatic' => true, 'examples' => ['commit', 'pull_request', 'merge', 'issue']],
            'file' => ['automatic' => true, 'examples' => ['created', 'updated', 'exported']],
            'image' => ['automatic' => false, 'examples' => ['photo_evidence']],
            'calendar' => ['automatic' => true, 'examples' => ['event_started', 'event_ended']],
        ];
    }
}
