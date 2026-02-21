<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Module>
 */
class ModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'stripe_monthly_price_id' => 'price_'.fake()->unique()->bothify('??????????'),
            'stripe_annual_price_id' => 'price_'.fake()->unique()->bothify('??????????'),
            'monthly_price_cents' => fake()->randomElement([1999, 2999, 4999, 9999]),
            'annual_price_cents' => fake()->randomElement([19990, 29990, 49990, 99990]),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the module is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the module has no Stripe prices yet (pre-sync state).
     */
    public function withoutStripePrices(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_monthly_price_id' => null,
            'stripe_annual_price_id' => null,
        ]);
    }
}
