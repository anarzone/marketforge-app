<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\Catalog\Infrastructure\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'seller_id' => UserFactory::new(),
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'price_minor' => fake()->numberBetween(100, 100000),
            'currency' => 'EUR',
            'status' => ProductStatus::Active,
            'attributes' => ['color' => fake()->safeColorName(), 'size' => fake()->randomElement(['S', 'M', 'L', 'XL'])],
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ProductStatus::Draft]);
    }

    public function archived(): static
    {
        return $this->state(['status' => ProductStatus::Archived]);
    }
}
