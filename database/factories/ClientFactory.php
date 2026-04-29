<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Client::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->numerify('###########'),
        ];
    }

    /**
     * Indicate that the client has no phone.
     */
    public function withoutPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => null,
        ]);
    }

    /**
     * Indicate that the client has a Brazilian phone format.
     */
    public function withBrazilianPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => fake()->numerify('###########'),
        ]);
    }
}
