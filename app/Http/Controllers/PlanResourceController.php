<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanResource;
use App\Services\PlanActivityService;
use App\Services\PlanOwnershipService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlanResourceController extends Controller
{
    public function index(Request $request, Plan $plan, PlanOwnershipService $ownership)
    {
        $ownership->authorizeView($request, $plan);

        $plan->load([
            'tasks' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'resources' => fn ($query) => $query->with(['tasks:id,title', 'createdBy:id,name'])->latest('id'),
        ]);

        $canEdit = $ownership->canEdit($request, $plan);
        $preferredProvider = $request->user()?->last_resource_provider;
        $providers = PlanResource::PROVIDERS;
        $resourceTypes = PlanResource::RESOURCE_TYPES;

        return view('resources.index', compact(
            'plan',
            'canEdit',
            'preferredProvider',
            'providers',
            'resourceTypes',
        ));
    }

    public function store(
        Request $request,
        Plan $plan,
        PlanOwnershipService $ownership,
        PlanActivityService $activity,
    ) {
        $ownership->authorizeEdit($request, $plan);
        $validated = $this->validateResource($request, $plan);

        if ($validated['provider'] === 'device') {
            throw ValidationException::withMessages([
                'provider' => 'デバイスからの直接アップロードは、ストレージ連携後に利用できるようになります。今は共有URLで登録してください。',
            ]);
        }

        $resource = $plan->resources()->create([
            'created_by_user_id' => $request->user()?->id,
            'provider' => $validated['provider'],
            'resource_type' => $validated['resource_type'],
            'title' => $validated['title'],
            'url' => $validated['url'],
        ]);

        $resource->tasks()->sync($this->taskIds($validated));
        $this->rememberProvider($request, $validated['provider']);

        $activity->record($plan, $request->user(), 'resource_created', 'plan_resource', (int) $resource->id, [
            'resource_title' => $resource->title,
        ]);

        return redirect()->route('plans.resources.index', $plan)
            ->with('success', '関連資料を追加しました。');
    }

    public function update(
        Request $request,
        Plan $plan,
        PlanResource $resource,
        PlanOwnershipService $ownership,
        PlanActivityService $activity,
    ) {
        $ownership->authorizeEdit($request, $plan);
        $this->ensureResourceBelongsToPlan($plan, $resource);
        $validated = $this->validateResource($request, $plan);

        if ($validated['provider'] === 'device') {
            throw ValidationException::withMessages([
                'provider' => 'デバイスからの直接アップロードは、ストレージ連携後に利用できるようになります。今は共有URLで登録してください。',
            ]);
        }

        $resource->update([
            'provider' => $validated['provider'],
            'resource_type' => $validated['resource_type'],
            'title' => $validated['title'],
            'url' => $validated['url'],
        ]);
        $resource->tasks()->sync($this->taskIds($validated));
        $this->rememberProvider($request, $validated['provider']);

        $activity->record($plan, $request->user(), 'resource_updated', 'plan_resource', (int) $resource->id, [
            'resource_title' => $resource->title,
        ]);

        return redirect()->route('plans.resources.index', $plan)
            ->with('success', '関連資料とタスクの紐づけを更新しました。');
    }

    public function destroy(
        Request $request,
        Plan $plan,
        PlanResource $resource,
        PlanOwnershipService $ownership,
        PlanActivityService $activity,
    ) {
        $ownership->authorizeEdit($request, $plan);
        $this->ensureResourceBelongsToPlan($plan, $resource);

        $title = $resource->title;
        $id = (int) $resource->id;
        $resource->delete();

        $activity->record($plan, $request->user(), 'resource_deleted', 'plan_resource', $id, [
            'resource_title' => $title,
        ]);

        return redirect()->route('plans.resources.index', $plan)
            ->with('success', '関連資料を削除しました。');
    }

    private function validateResource(Request $request, Plan $plan): array
    {
        return $request->validate([
            'provider' => ['required', Rule::in(array_keys(PlanResource::PROVIDERS))],
            'resource_type' => ['required', Rule::in(array_keys(PlanResource::RESOURCE_TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'string', 'max:2048', 'url', 'starts_with:http://,https://'],
            'task_ids' => ['nullable', 'array', 'max:100'],
            'task_ids.*' => [
                'integer',
                Rule::exists('tasks', 'id')->where(fn ($query) => $query->where('plan_id', $plan->id)),
            ],
        ]);
    }

    /** @return array<int> */
    private function taskIds(array $validated): array
    {
        return collect($validated['task_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function rememberProvider(Request $request, string $provider): void
    {
        if ($request->user()) {
            $request->user()->forceFill(['last_resource_provider' => $provider])->save();
        }
    }

    private function ensureResourceBelongsToPlan(Plan $plan, PlanResource $resource): void
    {
        if ((int) $resource->plan_id !== (int) $plan->id) {
            abort(404);
        }
    }
}
