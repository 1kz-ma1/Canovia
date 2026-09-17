<?php

return [
    'version' => env('CANOVIA_APP_VERSION', env('PACEKEEPER_APP_VERSION', 'v29')),
    'onboarding_version' => (int) env('CANOVIA_ONBOARDING_VERSION', 1),
    'admin_email' => env('CANOVIA_ADMIN_EMAIL', env('PACEKEEPER_ADMIN_EMAIL')),
    'feedback_admin_password' => env('FEEDBACK_ADMIN_PASSWORD', env('TEMPLATE_ADMIN_PASSWORD')),

    // Canonical public origin. Route generation and shared URLs use this URL in production.
    'canonical_url' => rtrim((string) env('CANOVIA_CANONICAL_URL', env('APP_URL', '')), '/'),

    // Old public hosts that should hand users over to the canonical Canovia origin.
    'legacy_hosts' => array_values(array_filter(array_map(
        static fn (string $host) => trim(strtolower($host)),
        explode(',', (string) env('CANOVIA_LEGACY_HOSTS', 'pacekeeper-d3mm.onrender.com'))
    ))),

    'redirect_legacy_hosts' => filter_var(
        env('CANOVIA_REDIRECT_LEGACY_HOSTS', false),
        FILTER_VALIDATE_BOOL
    ),
];
