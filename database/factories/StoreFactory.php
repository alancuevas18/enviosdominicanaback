<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'name' => $this->faker->company(),
            'legal_name' => $this->faker->optional()->company() . ' S.R.L.',
            'rnc' => $this->faker->optional()->numerify('#########'),
            'phone' => '+1809' . $this->faker->numerify('#######'),
            'whatsapp' => '+1809' . $this->faker->numerify('#######'),
            'email' => $this->faker->unique()->companyEmail(),
            'address' => $this->faker->address(),
            'sector' => $this->faker->optional()->word(),
            'city' => 'Santo Domingo',
            'province' => 'Distrito Nacional',
            'contact_person' => $this->faker->name(),
            'default_notification_message' => 'Tu pedido de {store_name} fue entregado a {recipient_name}. Orden #{order_id}.',
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
