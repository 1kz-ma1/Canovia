<?php

return [
    'driver' => env('CANOVIA_NATIVE_AI_DRIVER', 'disabled'),
    'timeout_seconds' => (int) env('CANOVIA_NATIVE_AI_TIMEOUT_SECONDS', 45),

    'providers' => [
        'openai' => [
            'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('CANOVIA_NATIVE_AI_MODEL', 'gpt-5.6-luna'),
        ],
    ],

    'study_practice' => [
        'capacity' => [
            'standard' => [
                'generation_max_output_tokens' => (int) env('CANOVIA_NATIVE_AI_STANDARD_GENERATION_MAX_OUTPUT_TOKENS', 8000),
                'assessment_max_output_tokens' => (int) env('CANOVIA_NATIVE_AI_STANDARD_ASSESSMENT_MAX_OUTPUT_TOKENS', 6000),
            ],
            'boosted' => [
                'generation_max_output_tokens' => (int) env('CANOVIA_NATIVE_AI_BOOSTED_GENERATION_MAX_OUTPUT_TOKENS', 12000),
                'assessment_max_output_tokens' => (int) env('CANOVIA_NATIVE_AI_BOOSTED_ASSESSMENT_MAX_OUTPUT_TOKENS', 8000),
            ],
        ],
    ],
];
