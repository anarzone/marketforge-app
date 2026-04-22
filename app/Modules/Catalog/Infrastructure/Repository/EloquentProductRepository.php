<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Repository;

use App\Modules\Catalog\Domain\Enums\ProductStatus;
use App\Modules\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentProductRepository implements ProductRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Product> */
    public function findActiveProducts(int $page): LengthAwarePaginator
    {
        return Product::query()
            ->where('status', ProductStatus::Active)
            ->with('categories')
            ->latest()
            ->paginate(20, ['*'], 'page', $page);
    }

    public function findActiveById(int $id): Product
    {
        return Product::query()
            ->where('status', ProductStatus::Active)
            ->with('categories')
            ->findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function store(int $sellerId, array $data, ?array $categoryIds = null): Product
    {
        $product = Product::create([
            'seller_id' => $sellerId,
            ...$data,
        ]);

        if ($categoryIds) {
            $product->categories()->sync($categoryIds);
        }

        return $product->load('categories');
    }

    /** @param array<string, mixed> $data */
    public function update(Product $product, array $data, ?array $categoryIds = null): Product
    {
        $product->update($data);

        if ($categoryIds !== null) {
            $product->categories()->sync($categoryIds);
        }

        return $product->load('categories');
    }

    public function archive(Product $product): void
    {
        $product->update(['status' => ProductStatus::Archived]);
    }
}
