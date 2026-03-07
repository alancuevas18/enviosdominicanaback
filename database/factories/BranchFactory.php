<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $cities = ['Santo Domingo', 'Santiago', 'San Pedro de Macorís', 'La Romana', 'Puerto Plata', 'San Francisco de Macorís'];

        return [
            'name' => $this->faker->company() . ' - Sucursal',
            'city' => $this->faker->randomElement($cities),
            'address' => $this->faker->address(),
            'phone' => '+1809' . $this->faker->numerify('#######'),
            'active' => true,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
