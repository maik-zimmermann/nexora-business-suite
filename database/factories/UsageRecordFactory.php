<?php

namespace Database\Factories;

use App\Enums\UsageType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UsageRecord>
 */
class UsageRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => UsageType::ApiCall,
            'quantity' => fake()->numberBetween(1, 100),
            'recorded_at' => now(),
        ];
    }
}
