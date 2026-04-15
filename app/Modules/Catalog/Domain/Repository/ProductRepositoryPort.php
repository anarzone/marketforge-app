<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repository;

use App\Modules\Catalog\Infrastructure\Product;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryPort
{
    /** @return LengthAwarePaginator<int, Product> */
    public function findActiveProducts(int $page): LengthAwarePaginator;

    public function findActiveById(int $id): Product;

    /** @param array<string, mixed> $data */
    public function store(int $sellerId, array $data, ?array $categoryIds = null): Product;

    /** @param array<string, mixed> $data */
    public function update(Product $product, array $data, ?array $categoryIds = null): Product;

    public function archive(Product $product): void;
}
