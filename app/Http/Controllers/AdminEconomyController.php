<?php

namespace App\Http\Controllers;

use App\Enums\FeatureKey;
use App\Enums\ProductKey;
use App\Models\User;
use App\Models\UserProductGrant;
use App\Services\AdminAccessService;
use App\Services\AiCapacityService;
use App\Services\EconomyCatalogService;
use App\Services\EconomyRecommendationService;
use App\Services\FeatureAccessService;
use App\Services\ProductGrantService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminEconomyController extends Controller
{
    public function __construct(
        private readonly AdminAccessService $access,
        private readonly EconomyCatalogService $catalog,
        private readonly ProductGrantService $grants,
        private readonly EconomyRecommendationService $recommendations,
        private readonly AiCapacityService $capacity,
        private readonly FeatureAccessService $featureAccess,
    ) {}

    public function index(Request $request)
    {
        if (! $this->access->authorized($request)) {
            return redirect()->route('admin.login');
        }

        $users = User::query()->orderBy('id')->limit(100)->get();
        $selectedUser = $request->integer('user_id')
            ? User::query()->find($request->integer('user_id'))
            : $users->first();

        $activeGrants = collect();
        $effectiveProducts = collect();
        $featureDecisions = collect();
        $recommendation = null;
        $capacityPolicy = null;

        if ($selectedUser) {
            $activeGrants = $this->grants->activeGrants($selectedUser);
            $effectiveProducts = $this->grants->effectiveProducts($selectedUser);
            $featureDecisions = collect(FeatureKey::cases())->map(fn (FeatureKey $feature) => [
                'feature' => $feature,
                'decision' => $this->featureAccess->resolveAccess($selectedUser, $feature),
            ]);
            $recommendation = $this->recommendations->recommend($selectedUser);
            $capacityPolicy = $this->capacity->policyFor($selectedUser);
        }

        return view('admin.economy.index', [
            'users' => $users,
            'selectedUser' => $selectedUser,
            'activeGrants' => $activeGrants,
            'effectiveProducts' => $effectiveProducts,
            'featureDecisions' => $featureDecisions,
            'recommendation' => $recommendation,
            'capacityPolicy' => $capacityPolicy,
            'productCases' => ProductKey::cases(),
            'catalog' => $this->catalog,
        ]);
    }

    public function storeGrant(Request $request)
    {
        if (! $this->access->authorized($request)) {
            return redirect()->route('admin.login');
        }

        $expiresRules = ['nullable', 'date'];
        if ($request->filled('starts_at')) {
            $expiresRules[] = 'after:starts_at';
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'product_key' => ['required', Rule::enum(ProductKey::class)],
            'source' => ['required', Rule::in(['manual', 'subscription', 'gift', 'sponsor', 'migration'])],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => $expiresRules,
        ]);

        UserProductGrant::create([
            ...$validated,
            'metadata' => [
                'granted_via' => 'admin_economy_inspector',
            ],
        ]);

        return redirect()
            ->route('admin.economy.index', ['user_id' => $validated['user_id']])
            ->with('success', 'テスト用Product Grantを追加しました。');
    }

    public function destroyGrant(Request $request, UserProductGrant $grant)
    {
        if (! $this->access->authorized($request)) {
            return redirect()->route('admin.login');
        }

        $userId = (int) $grant->user_id;
        $grant->delete();

        return redirect()
            ->route('admin.economy.index', ['user_id' => $userId])
            ->with('success', 'Product Grantを解除しました。');
    }
}
