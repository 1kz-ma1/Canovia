<?php

namespace App\Data;

final readonly class PlanCategoryProfileData
{
    public function __construct(
        public string $key,
        public string $label,
        public string $roadmapRenderer,
        public string $roadmapTitle,
        public string $roadmapDescription,
        public string $surfaceTone,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'roadmap_renderer' => $this->roadmapRenderer,
            'roadmap_title' => $this->roadmapTitle,
            'roadmap_description' => $this->roadmapDescription,
            'surface_tone' => $this->surfaceTone,
        ];
    }
}
