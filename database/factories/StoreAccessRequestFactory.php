<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StoreAccessRequest>
 */
class StoreAccessRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'store_name' => fake()->company(),
            'owner_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+1809' . fake()->numerify('#######'),
            'business_type' => fake()->randomElement(['fashion', 'electronics', 'pharmacy', 'food', 'general']),
            'address' => fake()->address(),
            'notes' => fake()->optional(0.4)->sentence(),
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'approved',
            'reviewed_by' => User::role('root')->value('id') ?? User::factory()->create()->id,
            'reviewed_at' => now()->subDays(rand(1, 7)),
            'rejection_reason' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'rejected',
            'reviewed_by' => User::role('root')->value('id') ?? User::factory()->create()->id,
            'reviewed_at' => now()->subDays(rand(1, 7)),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
