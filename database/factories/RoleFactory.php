<?php

namespace Database\Factories;

use App\Enums\RoleContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(2),
            'context' => RoleContext::Tenant,
            'is_default' => false,
        ];
    }

    /**
     * Indicate a tenant-context role.
     */
    public function tenant(): static
    {
        return $this->state(fn (array $attributes) => [
            'context' => RoleContext::Tenant,
        ]);
    }

    /**
     * Indicate an administration-context role.
     */
    public function administration(): static
    {
        return $this->state(fn (array $attributes) => [
            'context' => RoleContext::Administration,
        ]);
    }

    /**
     * Create an owner role.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Owner',
            'slug' => 'owner',
            'context' => RoleContext::Tenant,
            'is_default' => true,
        ]);
    }

    /**
     * Create an admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Admin',
            'slug' => 'admin',
            'context' => RoleContext::Tenant,
            'is_default' => true,
        ]);
    }

    /**
     * Create a member role.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Member',
            'slug' => 'member',
            'context' => RoleContext::Tenant,
            'is_default' => true,
        ]);
    }

    /**
     * Create a viewer role.
     */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Viewer',
            'slug' => 'viewer',
            'context' => RoleContext::Tenant,
            'is_default' => true,
        ]);
    }

    /**
     * Create a super-admin role.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'context' => RoleContext::Administration,
            'is_default' => true,
        ]);
    }

    /**
     * Create a support role.
     */
    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Support',
            'slug' => 'support',
            'context' => RoleContext::Administration,
            'is_default' => true,
        ]);
    }
}
