<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminFeedbackController extends Controller
{
    public function login(Request $request)
    {
        if ($this->authorized($request)) {
            return redirect()->route('admin.feedback.index');
        }

        return view('admin.feedback.login', [
            'passwordConfigured' => filled(config('canovia.feedback_admin_password')),
        ]);
    }

    public function authenticate(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:255'],
        ]);

        $expected = (string) config('canovia.feedback_admin_password', '');
        if ($expected === '' || ! hash_equals($expected, (string) $validated['password'])) {
            return back()->withErrors([
                'password' => '管理用パスワードが正しくありません。',
            ]);
        }

        $request->session()->put('feedback_admin_authenticated', true);
        $request->session()->regenerate();

        return redirect()->route('admin.feedback.index');
    }

    public function index(Request $request)
    {
        if (! $this->authorized($request)) {
            return redirect()->route('admin.feedback.login');
        }

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['usability', 'bug', 'request', 'positive'])],
            'status' => ['nullable', Rule::in(['new', 'reviewing', 'resolved'])],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'archived' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $showArchived = ($validated['archived'] ?? '0') === '1';

        $query = Feedback::query()->with(['user', 'plan', 'task'])
            ->when($showArchived,
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->whereNull('archived_at')
            );
        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (! empty($validated['rating'])) {
            $query->where('rating', (int) $validated['rating']);
        }

        $feedbacks = $query->latest()->paginate(30)->withQueryString();
        // Archived feedback is intentionally excluded from all analysis metrics.
        $ratedQuery = Feedback::query()->whereNull('archived_at')->whereNotNull('rating');
        $ratedCount = (clone $ratedQuery)->count();
        $averageRating = $ratedCount > 0 ? round((float) (clone $ratedQuery)->avg('rating'), 2) : null;
        $distributionRaw = (clone $ratedQuery)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');
        $distribution = collect(range(5, 1))->mapWithKeys(
            fn (int $rating) => [$rating => (int) ($distributionRaw[$rating] ?? 0)]
        );
        $newCount = Feedback::query()->whereNull('archived_at')->where('status', 'new')->count();

        return view('admin.feedback.index', compact(
            'feedbacks',
            'ratedCount',
            'averageRating',
            'distribution',
            'newCount',
            'showArchived',
        ));
    }

    public function updateStatus(Request $request, Feedback $feedback)
    {
        $this->ensureAuthorized($request);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'reviewing', 'resolved'])],
        ]);

        $feedback->update(['status' => $validated['status']]);

        return back()->with('status', 'フィードバックの状態を更新しました。');
    }


    public function archive(Request $request, Feedback $feedback)
    {
        $this->ensureAuthorized($request);

        if ($feedback->archived_at === null) {
            $feedback->update(['archived_at' => now()]);
        }

        return back()->with('status', 'フィードバックをアーカイブしました。分析対象から除外されます。');
    }

    public function restore(Request $request, Feedback $feedback)
    {
        $this->ensureAuthorized($request);

        if ($feedback->archived_at !== null) {
            $feedback->update(['archived_at' => null]);
        }

        return back()->with('status', 'フィードバックを復元しました。');
    }

    private function ensureAuthorized(Request $request): void
    {
        if (! $this->authorized($request)) {
            abort(403);
        }
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
