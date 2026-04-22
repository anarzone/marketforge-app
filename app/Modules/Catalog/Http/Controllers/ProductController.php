<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Modules\Catalog\Http\Requests\UpsertProductRequest;
use App\Modules\Catalog\Infrastructure\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private ProductRepositoryInterface $products,
    ) {}

    /** Public — list active products (paginated, cached). */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', '1');

        return new JsonResponse($this->products->findActiveProducts($page));
    }

    /** Public — single product detail (cached). */
    public function show(int $id): JsonResponse
    {
        return new JsonResponse($this->products->findActiveById($id));
    }

    /** Seller — create a product. */
    public function store(UpsertProductRequest $request): JsonResponse
    {
        $product = $this->products->store(
            $request->user()->id,
            $request->safe()->except(['category_ids']),
            $request->validated('category_ids'),
        );

        return new JsonResponse($product, JsonResponse::HTTP_CREATED);
    }

    /** Seller — update their product. */
    public function update(UpsertProductRequest $request, int $id): JsonResponse
    {
        $product = Product::query()
            ->where('seller_id', $request->user()->id)
            ->findOrFail($id);

        $updated = $this->products->update(
            $product,
            $request->safe()->except(['category_ids']),
            $request->has('category_ids') ? $request->validated('category_ids') : null,
        );

        return new JsonResponse($updated);
    }

    /** Seller — archive their product. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $product = Product::query()
            ->where('seller_id', $request->user()->id)
            ->findOrFail($id);

        $this->products->archive($product);

        return new JsonResponse(['message' => 'Product archived']);
    }
}
