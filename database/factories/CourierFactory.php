<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Courier>
 */
class CourierFactory extends Factory
{
    protected $model = Courier::class;

    public function definition(): array
    {
        $vehicles = ['Moto', 'Carro', 'Bicicleta', 'Scooter'];

        return [
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'name' => $this->faker->name(),
            'phone' => '+1809' . $this->faker->numerify('#######'),
            'vehicle' => $this->faker->randomElement($vehicles),
            'rating_avg' => $this->faker->randomFloat(2, 3.0, 5.0),
            'rating_count' => $this->faker->numberBetween(0, 50),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
