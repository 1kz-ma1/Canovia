<?php

$canoviaAi = (bool) env('FEATURE_CANOVIA_AI', env('FEATURE_PACEKEEPER_AI', false));

return [
    // 将来のアプリ内伴走AI。実装・課金方針が固まるまでは一般UIに出さない。
    'canovia_ai' => $canoviaAi,

    // Legacy internal key for backwards compatibility with older deployments.
    'pacekeeper_ai' => $canoviaAi,
];
