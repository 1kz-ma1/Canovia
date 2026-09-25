<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\ReleaseNote;
use App\Services\AdminAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminFeedbackController extends Controller
{
    public function __construct(private readonly AdminAccessService $adminAccess)
    {
    }

    public function login(Request $request)
    {
        abort_unless($this->authorized($request), 403);

        return redirect()->route('admin.dashboard');
    }

    public function authenticate(Request $request)
    {
        // V41.7: password knowledge never grants Admin access. This endpoint is
        // retained only for legacy bookmarks/forms and requires AdminAccess
        // middleware before reaching the controller.
        abort_unless($this->authorized($request), 403);

        return redirect()->route('admin.dashboard');
    }

    public function index(Request $request)
    {
        if (! $this->authorized($request)) {
            return redirect()->route('admin.login');
        }

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['usability', 'bug', 'request', 'positive'])],
            'status' => ['nullable', Rule::in(['new', 'reviewing', 'resolved'])],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'archived' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $showArchived = ($validated['archived'] ?? '0') === '1';

        $query = Feedback::query()->with(['user', 'plan', 'task', 'releaseNote'])
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

    public function publishReleaseNote(Request $request, Feedback $feedback)
    {
        $this->ensureAuthorized($request);

        if ($feedback->releaseNote()->exists()) {
            return back()->withErrors([
                'release_note' => 'このフィードバックには既に更新情報が紐づいています。',
            ]);
        }

        $validated = $request->validate([
            'version' => ['required', 'string', 'max:32'],
            'published_at' => ['required', 'date'],
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['required', 'string', 'max:1200'],
            'user_voice' => ['nullable', 'string', 'max:1600'],
            'highlights' => ['required', 'string', 'max:6000'],
            'tip' => ['nullable', 'string', 'max:1600'],
        ]);

        $highlights = collect(preg_split('/\R/u', $validated['highlights']))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->take(10)
            ->values()
            ->all();

        if ($highlights === []) {
            return back()->withErrors([
                'highlights' => '改善内容を1件以上入力してください。',
            ])->withInput();
        }

        $now = now();
        $publishedAt = \Carbon\Carbon::parse($validated['published_at'])
            ->setTime($now->hour, $now->minute, $now->second);

        $feedback->releaseNote()->create([
            'version' => trim($validated['version']),
            'title' => trim($validated['title']),
            'summary' => trim($validated['summary']),
            'user_voice' => filled($validated['user_voice'] ?? null) ? trim($validated['user_voice']) : null,
            'highlights' => $highlights,
            'tip' => filled($validated['tip'] ?? null) ? trim($validated['tip']) : null,
            'published_at' => $publishedAt,
        ]);

        $feedback->update(['status' => 'resolved']);

        return back()->with('status', 'フィードバックへの対応を更新情報として公開しました。');
    }

    public function unpublishReleaseNote(Request $request, Feedback $feedback, ReleaseNote $releaseNote)
    {
        $this->ensureAuthorized($request);

        abort_unless((int) $releaseNote->feedback_id === (int) $feedback->id, 404);
        $releaseNote->delete();

        return back()->with('status', '更新情報の公開を取り消しました。元のフィードバックは残っています。');
    }

    private function ensureAuthorized(Request $request): void
    {
        if (! $this->authorized($request)) {
            abort(403);
        }
    }

    private function authorized(Request $request): bool
    {
        return $this->adminAccess->authorized($request);
    }
}
