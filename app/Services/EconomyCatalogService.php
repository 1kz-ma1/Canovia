<?php

namespace App\Services;

use App\Enums\FeatureKey;
use App\Enums\ProductKey;
use Illuminate\Support\Collection;

class EconomyCatalogService
{
    public function product(ProductKey|string $product): array
    {
        $key = $product instanceof ProductKey ? $product->value : $product;

        return (array) config('economy.products.'.$key, []);
    }

    /**
     * @param iterable<ProductKey|string> $products
     * @return Collection<int, ProductKey>
     */
    public function expand(iterable $products): Collection
    {
        $expanded = collect();

        $visit = function (ProductKey $product) use (&$visit, $expanded): void {
            if ($expanded->contains(fn (ProductKey $item) => $item === $product)) {
                return;
            }

            $expanded->push($product);

            foreach ((array) data_get($this->product($product), 'includes', []) as $included) {
                $includedKey = ProductKey::tryFrom((string) $included);
                if ($includedKey) {
                    $visit($includedKey);
                }
            }
        };

        foreach ($products as $product) {
            $key = $product instanceof ProductKey ? $product : ProductKey::tryFrom((string) $product);
            if ($key) {
                $visit($key);
            }
        }

        return $expanded->values();
    }

    /**
     * Commercial dependencies are enforced after bundle expansion.
     *
     * @param iterable<ProductKey|string> $products
     * @return Collection<int, ProductKey>
     */
    public function usableProducts(iterable $products): Collection
    {
        $expanded = $this->expand($products);

        return $expanded
            ->filter(function (ProductKey $product) use ($expanded) {
                $requires = collect((array) data_get($this->product($product), 'requires', []))
                    ->map(fn ($key) => ProductKey::tryFrom((string) $key))
                    ->filter();

                return $requires->every(
                    fn (ProductKey $required) => $expanded->contains(fn (ProductKey $item) => $item === $required)
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, FeatureKey>
     */
    public function featureKeysFor(ProductKey $product): Collection
    {
        return collect((array) data_get($this->product($product), 'feature_keys', []))
            ->map(fn ($key) => FeatureKey::tryFrom((string) $key))
            ->filter()
            ->values();
    }

    public function label(ProductKey $product): string
    {
        return (string) data_get($this->product($product), 'label', $product->value);
    }
}
