<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Stop;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stop>
 */
class StopFactory extends Factory
{
    protected $model = Stop::class;

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'type' => $this->faker->randomElement(['pickup', 'delivery']),
            'suggested_order' => $this->faker->numberBetween(1, 10),
            'status' => 'pending',
            'completed_at' => null,
            'photo_s3_key' => null,
            'amount_collected' => null,
            'fail_reason' => null,
        ];
    }

    public function pickup(): static
    {
        return $this->state(['type' => 'pickup', 'suggested_order' => 1]);
    }

    public function delivery(): static
    {
        return $this->state(['type' => 'delivery', 'suggested_order' => 2]);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed', 'completed_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'fail_reason' => $this->faker->sentence(),
            'completed_at' => now(),
        ]);
    }
}
