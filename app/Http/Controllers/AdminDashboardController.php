<?php

namespace App\Http\Controllers;

use App\Enums\BehaviorEventType;
use App\Models\BehaviorEvent;
use App\Models\Feedback;
use App\Models\QuestionPack;
use App\Services\AdminAccessService;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(private readonly AdminAccessService $access)
    {
    }

    public function index(Request $request)
    {
        if (! $this->access->authorized($request)) {
            return redirect()->route('admin.login');
        }

        $feedbackNew = Feedback::query()
            ->whereNull('archived_at')
            ->where('status', 'new')
            ->count();

        $since = now()->subDays(7);
        $generationAttempts = BehaviorEvent::query()
            ->where('event_type', BehaviorEventType::PlanGenerationImportAttempted->value)
            ->where('occurred_at', '>=', $since)
            ->count();
        $generationFailures = BehaviorEvent::query()
            ->where('event_type', BehaviorEventType::PlanGenerationImportFailed->value)
            ->where('occurred_at', '>=', $since)
            ->count();
        $updateFailures = BehaviorEvent::query()
            ->whereIn('event_type', [
                BehaviorEventType::PlanUpdatePromptFailed->value,
                BehaviorEventType::PlanUpdatePreviewFailed->value,
                BehaviorEventType::PlanUpdateApplyFailed->value,
            ])
            ->where('occurred_at', '>=', $since)
            ->count();

        $questionPackCount = QuestionPack::query()->count();
        $publishedQuestionPackCount = QuestionPack::query()->where('status', 'published')->count();

        return view('admin.index', compact(
            'feedbackNew',
            'generationAttempts',
            'generationFailures',
            'updateFailures',
            'questionPackCount',
            'publishedQuestionPackCount',
        ));
    }
}
