<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure;

use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\SharedKernel\Domain\Currency;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'name',
        'description',
        'price_minor',
        'currency',
        'status',
        'attributes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'currency' => Currency::class,
            'status' => ProductStatus::class,
            'attributes' => 'array',
        ];
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_category');
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
