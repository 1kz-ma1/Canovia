<?php

use App\Enums\FeatureKey;

return [
    /*
    |--------------------------------------------------------------------------
    | Entitlement policy
    |--------------------------------------------------------------------------
    |
    | This configuration answers "may this actor use this published feature?"
    | It does NOT decide whether a feature is publicly exposed. Feature rollout
    | and beta visibility remain the responsibility of config/features.php.
    |
    | V40.6 intentionally keeps every current feature free. Monetization may
    | later set individual "free" values to false and add Premium/Coin/Gift/
    | Sponsor resolvers without changing feature code.
    |
    */

    'default_policy' => 'allow',

    'features' => [
        FeatureKey::AiPractice->value => [
            'label' => 'AI Practice',
            'free' => true,
        ],
        FeatureKey::AdvancedAnalytics->value => [
            'label' => 'Advanced Analytics',
            'free' => true,
        ],
        FeatureKey::QuestionPack->value => [
            'label' => 'Question Pack',
            'free' => true,
        ],
        FeatureKey::ProjectArtifact->value => [
            'label' => 'Project Artifact',
            'free' => true,
        ],
        FeatureKey::AutomaticAiExecution->value => [
            'label' => 'Automatic AI Execution',
            'free' => true,
        ],
    ],
];
