<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Repository;

use App\Modules\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Decorator: checks Redis first, delegates to the inner repository on miss.
 *
 * Cache-aside pattern: reads populate the cache lazily, writes invalidate it.
 * The inner repository (EloquentProductRepository) never knows caching exists.
 */
class CachedProductRepository implements ProductRepositoryInterface
{
    private const string KEY_LIST = 'products:list:page:';

    private const string KEY_DETAIL = 'products:detail:';

    public function __construct(
        private EloquentProductRepository $inner,
        private int $ttl,
    ) {}

    /** @return LengthAwarePaginator<int, Product> */
    public function findActiveProducts(int $page): LengthAwarePaginator
    {
        return Cache::remember(
            self::KEY_LIST.$page,
            $this->ttl,
            fn () => $this->inner->findActiveProducts($page),
        );
    }

    public function findActiveById(int $id): Product
    {
        return Cache::remember(
            self::KEY_DETAIL.$id,
            $this->ttl,
            fn () => $this->inner->findActiveById($id),
        );
    }

    /** @param array<string, mixed> $data */
    public function store(int $sellerId, array $data, ?array $categoryIds = null): Product
    {
        $product = $this->inner->store($sellerId, $data, $categoryIds);
        $this->invalidateListCache();

        return $product;
    }

    /** @param array<string, mixed> $data */
    public function update(Product $product, array $data, ?array $categoryIds = null): Product
    {
        $updated = $this->inner->update($product, $data, $categoryIds);
        $this->invalidateDetailCache($product->id);
        $this->invalidateListCache();

        return $updated;
    }

    public function archive(Product $product): void
    {
        $this->inner->archive($product);
        $this->invalidateDetailCache($product->id);
        $this->invalidateListCache();
    }

    /**
     * Flush all paginated list cache keys.
     *
     * Simple approach: delete pages 1-100. For a production system with
     * thousands of pages, you'd use Cache::tags(['products:list'])->flush()
     * — but tags require Redis (not file/database driver). Since we ARE on
     * Redis, tags would be cleaner. Keeping this simple for now.
     */
    private function invalidateListCache(): void
    {
        for ($page = 1; $page <= 100; $page++) {
            Cache::forget(self::KEY_LIST.$page);
        }
    }

    private function invalidateDetailCache(int $id): void
    {
        Cache::forget(self::KEY_DETAIL.$id);
    }
}
