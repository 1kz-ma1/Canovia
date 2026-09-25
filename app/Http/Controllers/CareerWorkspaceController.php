<?php

namespace App\Http\Controllers;

use App\Models\CareerApplication;
use App\Models\CareerCapture;
use App\Models\CareerSelectionEvent;
use App\Models\Plan;
use App\Services\BehaviorIdentityService;
use App\Services\CareerCaptureService;
use App\Services\PlanCategoryProfileService;
use App\Services\PlanOwnershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CareerWorkspaceController extends Controller
{
    public function index(
        Request $request,
        Plan $plan,
        PlanOwnershipService $ownership,
        PlanCategoryProfileService $profiles,
    ) {
        $ownership->authorizeView($request, $plan);
        $this->authorizeCareerPlan($plan, $profiles);

        $plan->load([
            'careerCaptures' => fn ($query) => $query->latest('captured_at')->latest('id'),
            'careerApplications' => fn ($query) => $query->with(['selectionEvents.interviewReview'])->latest('updated_at')->latest('id'),
            'tasks' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        return view('career.index', [
            'plan' => $plan,
            'captures' => $plan->careerCaptures,
            'applications' => $plan->careerApplications,
            'canEdit' => $ownership->canEdit($request, $plan),
        ]);
    }

    public function storeCapture(
        Request $request,
        Plan $plan,
        PlanOwnershipService $ownership,
        PlanCategoryProfileService $profiles,
        BehaviorIdentityService $identity,
        CareerCaptureService $captureService,
    ) {
        $ownership->authorizeEdit($request, $plan);
        $this->authorizeCareerPlan($plan, $profiles);

        $validated = $request->validate([
            'source_type' => ['required', Rule::in(['screenshot', 'url'])],
            'source_url' => ['nullable', 'required_if:source_type,url', 'url', 'max:2048'],
            'screenshot' => ['nullable', 'required_if:source_type,screenshot', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $file = $request->file('screenshot');
        $path = null;

        if ($file) {
            $path = $file->store('career-captures/'.$plan->id);
        }

        try {
            $captureService->record(
                $plan,
                sourceType: $validated['source_type'],
                sourceUrl: $validated['source_url'] ?? null,
                screenshotPath: $path,
                screenshotMime: $file?->getMimeType(),
                screenshotOriginalName: $file?->getClientOriginalName(),
                rawText: trim((string) ($validated['note'] ?? '')) ?: null,
                userId: $request->user()?->id,
                actorToken: $identity->resolve($request),
            );
        } catch (\Throwable $error) {
            if ($path) {
                Storage::delete($path);
            }

            throw $error;
        }

        return redirect()
            ->route('plans.career.index', $plan)
            ->with('success', 'Career Inboxへ追加しました。');
    }

    public function screenshot(
        Request $request,
        Plan $plan,
        CareerCapture $capture,
        PlanOwnershipService $ownership,
        PlanCategoryProfileService $profiles,
    ) {
        $ownership->authorizeView($request, $plan);
        $this->authorizeCareerPlan($plan, $profiles);
        abort_unless((int) $capture->plan_id === (int) $plan->id, 404);
        abort_unless(filled($capture->screenshot_path) && Storage::exists($capture->screenshot_path), 404);

        $stream = Storage::readStream($capture->screenshot_path);
        abort_unless(is_resource($stream), 404);

        $filename = basename((string) ($capture->screenshot_original_name ?: 'career-capture'));

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $capture->screenshot_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroyCapture(
        Request $request,
        Plan $plan,
        CareerCapture $capture,
        PlanOwnershipService $ownership,
        PlanCategoryProfileService $profiles,
    ) {
        $ownership->authorizeEdit($request, $plan);
        $this->authorizeCareerPlan($plan, $profiles);
        abort_unless((int) $capture->plan_id === (int) $plan->id, 404);

        if ($capture->screenshot_path) {
            Storage::delete($capture->screenshot_path);
        }

        $capture->delete();

        return redirect()
            ->route('plans.career.index', $plan)
            ->with('success', 'Captureを削除しました。');
    }

    public function storeApplication(
        Request $request,
        Plan $plan,
        PlanOwnershipService $ownership,
        PlanCategoryProfileService $profiles,
    ) {
        $ownership->authorizeEdit($request, $plan);
        $this->authorizeCareerPlan($plan, $profiles);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_website' => ['nullable', 'url', 'max:2048'],
            'role_title' => ['nullable', 'string', 'max:255'],
            'stage' => ['required', Rule::in(CareerApplication::STAGES)],
            'capture_id' => ['nullable', 'integer'],
        ]);

        $capture = null;
        if (! empty($validated['capture_id'])) {
            $capture = CareerCapture::query()
                ->where('plan_id', $plan->id)
                ->findOrFail((int) $validated['capture_id']);
        }

        $application = CareerApplication::create([
            'plan_id' => $plan->id,
            'company_name' => trim($validated['company_name']),
            'company_website' => $validated['company_website'] ?? null,
            'role_title' => trim((string) ($validated['role_title'] ?? '')) ?: null,
            'stage' => $validated['stage'],
            'status' => 'active',
            'source' => $capture?->source_type ?? 'manual',
        ]);

        if ($capture) {
            $capture->update([
                'career_application_id' => $application->id,
                'status' => 'linked',
            ]);
        }

        return redirect()
            ->route('plans.career.index', $plan)
            ->with('success', '応募先を追加しました。');
    }

    public function updateApplication(
        Request $request,
        Plan $plan,
        CareerApplication $application,
        PlanOwnershipService $ownership,
        PlanCategoryProfileService $profiles,
    ) {
        $ownership->authorizeEdit($request, $plan);
        $this->authorizeCareerPlan($plan, $profiles);
        abort_unless((int) $application->plan_id === (int) $plan->id, 404);

        $validated = $request->validate([
            'stage' => ['required', Rule::in(CareerApplication::STAGES)],
            'status' => ['required', Rule::in(CareerApplication::STATUSES)],
            'result' => ['nullable', 'string', 'max:32'],
        ]);

        $application->update($validated);

        return redirect()
            ->route('plans.career.index', $plan)
            ->with('success', '応募状況を更新しました。');
    }

    public function linkCapture(
        Request $request,
        Plan $plan,
        CareerCapture $capture,
        PlanOwnershipService $ownership,
        PlanCategoryProfileService $profiles,
    ) {
        $ownership->authorizeEdit($request, $plan);
        $this->authorizeCareerPlan($plan, $profiles);
        abort_unless((int) $capture->plan_id === (int) $plan->id, 404);

        $validated = $request->validate([
            'career_application_id' => ['required', 'integer'],
        ]);

        $application = CareerApplication::query()
            ->where('plan_id', $plan->id)
            ->findOrFail((int) $validated['career_application_id']);

        $capture->update([
            'career_application_id' => $application->id,
            'status' => 'linked',
        ]);

        return redirect()
            ->route('plans.career.index', $plan)
            ->with('success', 'Captureを応募先へ紐付けました。');
    }

    public function storeSelectionEvent(
        Request $request,
        Plan $plan,
        CareerApplication $application,
        PlanOwnershipService $ownership,
        PlanCategoryProfileService $profiles,
    ) {
        $ownership->authorizeEdit($request, $plan);
        $this->authorizeCareerPlan($plan, $profiles);
        abort_unless((int) $application->plan_id === (int) $plan->id, 404);

        $validated = $request->validate([
            'stage' => ['required', Rule::in(['interview', 'final_interview'])],
            'scheduled_at' => ['required', 'date'],
            'task_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $taskId = null;
        if (! empty($validated['task_id'])) {
            $taskId = $plan->tasks()->whereKey((int) $validated['task_id'])->value('id');
            abort_unless($taskId, 422, 'このPlanのTaskを指定してください。');
        } else {
            $interviewTasks = $plan->tasks()
                ->whereNotIn('status', ['done', 'cancelled'])
                ->get()
                ->filter(function ($task) {
                    $text = mb_strtolower(trim($task->title.' '.($task->description ?? '').' '.($task->next_action_note ?? '')));

                    return preg_match('/面接|面談|一次|二次|三次|最終|選考|逆質問/u', $text) === 1;
                })
                ->values();

            if ($interviewTasks->count() === 1) {
                $taskId = $interviewTasks->first()->id;
            }
        }

        $event = CareerSelectionEvent::create([
            'career_application_id' => $application->id,
            'task_id' => $taskId,
            'type' => 'interview',
            'stage' => $validated['stage'],
            'status' => 'scheduled',
            'scheduled_at' => $validated['scheduled_at'],
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
        ]);

        $application->update([
            'stage' => $validated['stage'],
            'status' => 'active',
            'next_event_at' => $event->scheduled_at,
        ]);

        return redirect()
            ->route('plans.career.index', $plan)
            ->with('success', '面接予定を追加しました。');
    }

    private function authorizeCareerPlan(Plan $plan, PlanCategoryProfileService $profiles): void
    {
        abort_unless($profiles->forPlan($plan)->key === 'career', 404);
    }
}
