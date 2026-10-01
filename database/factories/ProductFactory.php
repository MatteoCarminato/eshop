<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'recno' => null,
            'brand_id' => null,
            'name' => $name,
            'short_name' => fake()->optional()->word(),
            'price' => fake()->randomFloat(2, 10, 1000),
            'wholesale_price' => null,
            'web_price' => null,
            'sale_price' => null,
            'min_price' => null,
            'stock' => fake()->randomFloat(4, 0, 100),
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'description' => fake()->optional()->paragraph(),
            'image_url' => null,
            'active' => true,
            'featured' => false,
            'synced_at' => null,
        ];
    }

    /**
     * Indicate that the product was imported/synced from the ERP.
     */
    public function fromErp(): static
    {
        return $this->state(fn (array $attributes) => [
            'recno' => fake()->unique()->numberBetween(1000, 999999),
            'synced_at' => now(),
        ]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the product is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }
}
