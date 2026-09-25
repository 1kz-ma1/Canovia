<?php

namespace App\Services;

use App\Enums\FeatureKey;
use App\Enums\ProductKey;
use App\Models\User;
use App\Models\UserProductGrant;
use Illuminate\Support\Collection;

class ProductGrantService
{
    public function __construct(
        private readonly EconomyCatalogService $catalog,
    ) {}

    /**
     * @return Collection<int, UserProductGrant>
     */
    public function activeGrants(User $user): Collection
    {
        return $user->productGrants()->active()->orderBy('id')->get();
    }

    /**
     * @return Collection<int, ProductKey>
     */
    public function directProducts(User $user): Collection
    {
        return $this->activeGrants($user)
            ->pluck('product_key')
            ->filter(fn ($product) => $product instanceof ProductKey)
            ->unique(fn (ProductKey $product) => $product->value)
            ->values();
    }

    /**
     * @return Collection<int, ProductKey>
     */
    public function effectiveProducts(User $user): Collection
    {
        return $this->catalog->usableProducts($this->directProducts($user));
    }

    public function hasEffectiveProduct(User $user, ProductKey $product): bool
    {
        return $this->effectiveProducts($user)
            ->contains(fn (ProductKey $item) => $item === $product);
    }

    /**
     * @return array{grant:UserProductGrant,effective_product:ProductKey}|null
     */
    public function grantForFeature(User $user, FeatureKey $feature): ?array
    {
        $allDirect = $this->directProducts($user);
        $usable = $this->catalog->usableProducts($allDirect);

        $grants = $this->activeGrants($user)
            ->sortByDesc(fn (UserProductGrant $grant) => match ((string) $grant->source) {
                'sponsor' => 400,
                'gift' => 300,
                'subscription' => 200,
                'manual' => 150,
                'migration' => 100,
                default => 0,
            });

        foreach ($grants as $grant) {
            if (! $grant->product_key instanceof ProductKey) {
                continue;
            }

            $expanded = $this->catalog->expand([$grant->product_key]);
            $eligible = $expanded->filter(
                fn (ProductKey $product) => $usable->contains(fn (ProductKey $item) => $item === $product)
            );

            foreach ($eligible as $effectiveProduct) {
                if ($this->catalog->featureKeysFor($effectiveProduct)->contains($feature)) {
                    return [
                        'grant' => $grant,
                        'effective_product' => $effectiveProduct,
                    ];
                }
            }
        }

        return null;
    }
}
