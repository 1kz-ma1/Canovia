@php
    // Presentation only: preserve the engine's selection, order, and registered views.
    $disclosureLabel = match ($surface->id) {
        'study_focus' => '学習の分析・弱点',
        'delivery_focus' => '成果物の状況',
        'recent_evidence' => '最近のEvidence・判断の根拠',
        'task_list' => 'このPlanのTask一覧',
        'recent_activity' => '最近の活動・履歴',
        'career_pipeline' => '応募・選考の流れ',
        'career_result_waiting' => '選考結果待ちの状況',
        default => null,
    };
@endphp

@if ($disclosureLabel)
    <details class="pk-action-details page-card p-4 sm:p-5" data-surface-disclosure="{{ $surface->id }}">
        <summary>{{ $disclosureLabel }}</summary>
        <div class="mt-3">
            @include($surface->view, ['surface' => $surface])
        </div>
    </details>
@else
    @include($surface->view, ['surface' => $surface])
@endif
