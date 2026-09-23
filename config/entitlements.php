<?php

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
        'ai_practice' => [
            'label' => 'AI Practice',
            'free' => true,
        ],
        'advanced_analytics' => [
            'label' => 'Advanced Analytics',
            'free' => true,
        ],
        'question_pack' => [
            'label' => 'Question Pack',
            'free' => true,
        ],
        'project_artifact' => [
            'label' => 'Project Artifact',
            'free' => true,
        ],
        'automatic_ai_execution' => [
            'label' => 'Automatic AI Execution',
            'free' => true,
        ],
    ],
];
