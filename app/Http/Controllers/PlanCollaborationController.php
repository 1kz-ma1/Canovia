<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanMember;
use App\Services\PlanCollaborationService;
use App\Services\PlanActivityService;
use App\Services\PlanOwnershipService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanCollaborationController extends Controller
{
    public function settings(Request $request, Plan $plan, PlanOwnershipService $ownership)
    {
        $ownership->authorizePlan($request, $plan);
        $plan->load(['user', 'memberships.user', 'activityLogs.user']);
        $recentActivities = $plan->activityLogs()->with('user')->limit(20)->get();

        return view('plans.collaboration', compact('plan', 'recentActivities'));
    }

    public function enable(
        Request $request,
        Plan $plan,
        PlanOwnershipService $ownership,
        PlanCollaborationService $collaboration,
    ) {
        $ownership->authorizePlan($request, $plan);

        if (! $request->user()) {
            return redirect()->route('auth.login.form')->with('status', '共同計画を作るにはログインが必要です。');
        }

        if (! $collaboration->canOwnCollaborativePlan($request->user())) {
            abort(403, '共同計画の作成権限がありません。');
        }

        $collaboration->enable($plan);

        return redirect()->route('plans.collaboration.settings', $plan)
            ->with('status', '共同計画を有効にしました。参加者は最初は閲覧者として参加します。');
    }

    public function disable(
        Request $request,
        Plan $plan,
        PlanOwnershipService $ownership,
        PlanCollaborationService $collaboration,
    ) {
        $ownership->authorizePlan($request, $plan);
        $collaboration->disable($plan);

        return redirect()->route('plans.collaboration.settings', $plan)
            ->with('status', '共同計画を停止しました。メンバー情報は再開に備えて保持しています。');
    }

    public function regenerateInvite(
        Request $request,
        Plan $plan,
        PlanOwnershipService $ownership,
        PlanCollaborationService $collaboration,
        PlanActivityService $activity,
    ) {
        $ownership->authorizePlan($request, $plan);
        abort_unless($plan->is_collaborative, 404);
        $collaboration->regenerateInvite($plan);
        $activity->record($plan, $request->user(), 'invite_regenerated', 'plan', (int) $plan->id);

        return redirect()->route('plans.collaboration.settings', $plan)
            ->with('status', '共有リンクと参加コードを再発行しました。以前の招待情報は使えません。');
    }

    public function joinForm()
    {
        return view('plans.join');
    }

    public function joinByCode(Request $request, PlanCollaborationService $collaboration)
    {
        $validated = $request->validate([
            'join_code' => ['required', 'string', 'max:16'],
        ]);

        $code = strtoupper(preg_replace('/\s+/', '', trim((string) $validated['join_code'])) ?? '');
        if ($code !== '' && ! str_starts_with($code, 'CNV-')) {
            $code = 'CNV-' . $code;
        }
        $plan = Plan::query()
            ->where('is_collaborative', true)
            ->where('collaboration_join_code', $code)
            ->first();

        if (! $plan) {
            return back()->withErrors(['join_code' => '有効な共同計画が見つかりませんでした。'])->withInput();
        }

        return $this->join($request, $plan, $collaboration);
    }

    public function joinByToken(Request $request, string $token, PlanCollaborationService $collaboration)
    {
        $plan = Plan::query()
            ->with('user')
            ->where('is_collaborative', true)
            ->where('collaboration_share_token', $token)
            ->firstOrFail();

        if (! $request->user()) {
            $request->session()->put('url.intended', $request->fullUrl());

            return view('plans.join_invite', [
                'plan' => $plan,
                'token' => $token,
            ]);
        }

        return $this->join($request, $plan, $collaboration);
    }

    public function updateMember(
        Request $request,
        Plan $plan,
        PlanMember $member,
        PlanOwnershipService $ownership,
        PlanActivityService $activity,
    ) {
        $ownership->authorizePlan($request, $plan);
        abort_unless((int) $member->plan_id === (int) $plan->id, 404);

        $validated = $request->validate([
            'role' => ['required', Rule::in(PlanMember::ROLES)],
        ]);

        $beforeRole = $member->role;
        $member->update(['role' => $validated['role']]);
        $activity->record($plan, $request->user(), 'member_role_changed', 'user', (int) $member->user_id, [
            'before_role' => $beforeRole,
            'after_role' => $validated['role'],
            'member_name' => $member->user?->name,
        ]);

        return redirect()->route('plans.collaboration.settings', $plan)
            ->with('status', 'メンバー権限を更新しました。');
    }

    public function removeMember(
        Request $request,
        Plan $plan,
        PlanMember $member,
        PlanOwnershipService $ownership,
        PlanActivityService $activity,
    ) {
        $ownership->authorizePlan($request, $plan);
        abort_unless((int) $member->plan_id === (int) $plan->id, 404);
        $memberName = $member->user?->name;
        $memberUserId = (int) $member->user_id;
        $member->delete();
        $activity->record($plan, $request->user(), 'member_removed', 'user', $memberUserId, [
            'member_name' => $memberName,
        ]);

        return redirect()->route('plans.collaboration.settings', $plan)
            ->with('status', 'メンバーを共同計画から外しました。');
    }

    private function join(Request $request, Plan $plan, PlanCollaborationService $collaboration)
    {
        if ((int) $plan->user_id === (int) $request->user()->id) {
            return redirect()->route('plans.show', $plan)->with('status', 'あなたが作成した共同計画です。');
        }

        $member = $collaboration->addViewer($plan, $request->user(), $plan->user);
        if ($member->wasRecentlyCreated) {
            app(PlanActivityService::class)->record($plan, $request->user(), 'member_joined', 'user', (int) $request->user()->id, [
                'member_name' => $request->user()->name,
            ]);
        }
        $message = $member->wasRecentlyCreated
            ? '共同計画に参加しました。最初の権限は閲覧のみです。'
            : 'すでにこの共同計画へ参加しています。';

        return redirect()->route('plans.show', $plan)->with('status', $message);
    }
}
