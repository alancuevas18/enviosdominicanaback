<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Courier;
use App\Models\Shipment;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        $statuses = ['pending', 'assigned', 'picked_up', 'in_route', 'delivered', 'not_delivered'];
        $paymentMethods = ['cash', 'transfer', 'card'];
        $weightSizes = ['small', 'medium', 'large'];
        $sectors = ['Piantini', 'Naco', 'Evaristo Morales', 'Los Prados', 'Gazcue', 'Bella Vista', 'Alma Rosa'];

        return [
            'store_id' => Store::factory(),
            'branch_id' => Branch::factory(),
            'courier_id' => null,
            'recipient_name' => $this->faker->name(),
            'recipient_phone' => '+1809' . $this->faker->numerify('#######'),
            'address' => $this->faker->address(),
            'maps_url' => $this->faker->optional()->url(),
            'sector' => $this->faker->randomElement($sectors),
            'amount_to_collect' => $this->faker->randomFloat(2, 0, 5000),
            'payment_method' => $this->faker->randomElement($paymentMethods),
            'payer' => $this->faker->randomElement(['store', 'customer']),
            'status' => 'pending',
            'notes' => $this->faker->optional()->sentence(),
            'weight_size' => $this->faker->randomElement($weightSizes),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'courier_id' => null]);
    }

    public function assigned(): static
    {
        return $this->state(['status' => 'assigned', 'courier_id' => Courier::factory()]);
    }

    public function delivered(): static
    {
        return $this->state(['status' => 'delivered', 'courier_id' => Courier::factory()]);
    }

    public function notDelivered(): static
    {
        return $this->state(['status' => 'not_delivered', 'courier_id' => Courier::factory()]);
    }
}
