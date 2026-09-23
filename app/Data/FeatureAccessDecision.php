<?php

namespace App\Data;

use App\Enums\EntitlementSource;
use App\Enums\FeatureKey;

final readonly class FeatureAccessDecision
{
    public function __construct(
        public FeatureKey $feature,
        public bool $allowed,
        public ?EntitlementSource $source,
        public string $reason,
        public array $metadata = [],
    ) {}

    public static function allow(
        FeatureKey $feature,
        EntitlementSource $source,
        string $reason,
        array $metadata = [],
    ): self {
        return new self($feature, true, $source, $reason, $metadata);
    }

    public static function deny(
        FeatureKey $feature,
        string $reason = 'no_entitlement',
        array $metadata = [],
    ): self {
        return new self($feature, false, null, $reason, $metadata);
    }

    /**
     * Small, stable shape that can later be attached to BehaviorEvent metadata
     * without leaking billing payloads or provider-specific receipt data.
     */
    public function auditMetadata(): array
    {
        return [
            'feature_key' => $this->feature->value,
            'allowed' => $this->allowed,
            'access_source' => $this->source?->value,
            'access_reason' => $this->reason,
        ];
    }
}
