<?php

namespace App\Http\Controllers;

use App\Enums\BehaviorEventType;
use App\Models\BehaviorEvent;
use Illuminate\Http\Request;

class AdminTelemetryController extends Controller
{
    public function index(Request $request)
    {
        if (! $this->authorized($request)) {
            return redirect()->route('admin.feedback.login');
        }

        $days = in_array((int) $request->query('days', 7), [7, 30], true)
            ? (int) $request->query('days', 7)
            : 7;

        $trackedTypes = [
            BehaviorEventType::PlanGenerationOpened,
            BehaviorEventType::PlanGenerationPromptCopyClicked,
            BehaviorEventType::PlanGenerationImportAttempted,
            BehaviorEventType::PlanGenerationImportFailed,
            BehaviorEventType::PlanGenerationImportSucceeded,
            BehaviorEventType::PlanUpdateOpened,
            BehaviorEventType::PlanUpdatePromptGenerated,
            BehaviorEventType::PlanUpdatePromptCopyClicked,
            BehaviorEventType::PlanUpdatePreviewAttempted,
            BehaviorEventType::PlanUpdatePreviewFailed,
            BehaviorEventType::PlanUpdatePreviewSucceeded,
            BehaviorEventType::PlanUpdateApplyAttempted,
            BehaviorEventType::PlanUpdateApplyFailed,
            BehaviorEventType::PlanUpdateApplied,
        ];

        $events = BehaviorEvent::query()
            ->whereIn('event_type', array_map(fn ($type) => $type->value, $trackedTypes))
            ->where('occurred_at', '>=', now()->subDays($days))
            ->orderBy('occurred_at')
            ->get();

        $generationStages = [
            BehaviorEventType::PlanGenerationOpened->value => '初期計画画面',
            BehaviorEventType::PlanGenerationPromptCopyClicked->value => 'プロンプトコピー',
            BehaviorEventType::PlanGenerationImportAttempted->value => 'JSON読み込み試行',
            BehaviorEventType::PlanGenerationImportSucceeded->value => '計画作成成功',
            BehaviorEventType::PlanGenerationImportFailed->value => '計画作成失敗',
        ];

        $updateStages = [
            BehaviorEventType::PlanUpdateOpened->value => '計画更新画面',
            BehaviorEventType::PlanUpdatePromptGenerated->value => 'プロンプト生成',
            BehaviorEventType::PlanUpdatePromptCopyClicked->value => 'プロンプトコピー',
            BehaviorEventType::PlanUpdatePreviewAttempted->value => 'JSONプレビュー試行',
            BehaviorEventType::PlanUpdatePreviewSucceeded->value => 'プレビュー成功',
            BehaviorEventType::PlanUpdatePreviewFailed->value => 'プレビュー失敗',
            BehaviorEventType::PlanUpdateApplyAttempted->value => '更新確定試行',
            BehaviorEventType::PlanUpdateApplied->value => '更新反映成功',
            BehaviorEventType::PlanUpdateApplyFailed->value => '更新反映失敗',
        ];

        $generation = $this->stageSummary($events, $generationStages);
        $updates = $this->stageSummary($events, $updateStages);

        $generationRate = $this->successRate(
            $events,
            BehaviorEventType::PlanGenerationImportAttempted,
            BehaviorEventType::PlanGenerationImportSucceeded,
        );
        $previewRate = $this->successRate(
            $events,
            BehaviorEventType::PlanUpdatePreviewAttempted,
            BehaviorEventType::PlanUpdatePreviewSucceeded,
        );
        $applyRate = $this->successRate(
            $events,
            BehaviorEventType::PlanUpdateApplyAttempted,
            BehaviorEventType::PlanUpdateApplied,
        );

        $surfaceSummary = collect(['web', 'pwa', 'unknown'])->mapWithKeys(function ($surface) use ($events) {
            $subset = $events->filter(fn ($event) => data_get($event->metadata, 'surface', 'unknown') === $surface);

            return [$surface => [
                'events' => $subset->count(),
                'actors' => $subset->pluck('actor_token')->unique()->count(),
                'generation_success' => $subset->filter(
                    fn ($event) => $event->event_type->value === BehaviorEventType::PlanGenerationImportSucceeded->value
                )->count(),
                'update_success' => $subset->filter(
                    fn ($event) => $event->event_type->value === BehaviorEventType::PlanUpdateApplied->value
                )->count(),
            ]];
        });

        $failureEvents = $events->filter(fn ($event) => in_array($event->event_type->value, [
            BehaviorEventType::PlanGenerationImportFailed->value,
            BehaviorEventType::PlanUpdatePreviewFailed->value,
            BehaviorEventType::PlanUpdateApplyFailed->value,
        ], true));

        $failureReasons = $failureEvents
            ->groupBy(fn ($event) => (string) data_get($event->metadata, 'failure_code', 'unknown'))
            ->map(fn ($group) => $group->count())
            ->sortDesc();

        $recentFailures = $failureEvents
            ->sortByDesc('occurred_at')
            ->take(20)
            ->map(fn ($event) => [
                'occurred_at' => $event->occurred_at,
                'event_type' => $event->event_type->value,
                'surface' => (string) data_get($event->metadata, 'surface', 'unknown'),
                'device' => (string) data_get($event->metadata, 'device', 'unknown'),
                'failure_code' => (string) data_get($event->metadata, 'failure_code', 'unknown'),
                'validation_fields' => (array) data_get($event->metadata, 'validation_fields', []),
            ])
            ->values();

        return view('admin.telemetry.index', compact(
            'days',
            'generation',
            'updates',
            'generationRate',
            'previewRate',
            'applyRate',
            'surfaceSummary',
            'failureReasons',
            'recentFailures',
        ));
    }

    private function stageSummary($events, array $labels): array
    {
        $summary = [];

        foreach ($labels as $eventType => $label) {
            $subset = $events->filter(fn ($event) => $event->event_type->value === $eventType);
            $summary[] = [
                'event_type' => $eventType,
                'label' => $label,
                'events' => $subset->count(),
                'actors' => $subset->pluck('actor_token')->unique()->count(),
            ];
        }

        return $summary;
    }

    private function successRate($events, BehaviorEventType $attempt, BehaviorEventType $success): ?float
    {
        $attemptActors = $events
            ->filter(fn ($event) => $event->event_type->value === $attempt->value)
            ->pluck('actor_token')
            ->unique();

        if ($attemptActors->isEmpty()) {
            return null;
        }

        $successActors = $events
            ->filter(fn ($event) => $event->event_type->value === $success->value)
            ->pluck('actor_token')
            ->unique()
            ->intersect($attemptActors);

        return round(($successActors->count() / $attemptActors->count()) * 100, 1);
    }

    private function authorized(Request $request): bool
    {
        if ((bool) $request->session()->get('feedback_admin_authenticated', false)) {
            return true;
        }

        $adminEmail = trim((string) config('canovia.admin_email', ''));
        $userEmail = trim((string) ($request->user()?->email ?? ''));

        return $adminEmail !== ''
            && $userEmail !== ''
            && mb_strtolower($adminEmail) === mb_strtolower($userEmail);
    }
}
