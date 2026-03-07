<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\Stop;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class ShipmentSeeder extends Seeder
{
    public function run(): void
    {
        $rootUser = User::role('root')->firstOrFail();
        $stores = Store::all();
        $couriers = Courier::all();

        if ($stores->isEmpty() || $couriers->isEmpty()) {
            $this->command->warn('⚠️  No hay tiendas o mensajeros. Ejecuta StoreSeeder y CourierSeeder primero.');

            return;
        }

        $shipmentsData = $this->getShipmentsData();

        foreach ($shipmentsData as $data) {
            $store = $stores->random();
            $courier = $couriers->where('branch_id', $store->branch_id)->first()
                ?? $couriers->first();

            $shipment = Shipment::create([
                'store_id' => $store->id,
                'branch_id' => $store->branch_id,
                'courier_id' => in_array($data['status'], ['pending']) ? null : $courier->id,
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'],
                'address' => $data['address'],
                'sector' => $data['sector'],
                'amount_to_collect' => $data['amount_to_collect'],
                'payment_method' => $data['payment_method'],
                'payer' => $data['payer'],
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'weight_size' => $data['weight_size'],
            ]);

            // Create pickup stop
            $pickupStop = Stop::create([
                'shipment_id' => $shipment->id,
                'type' => 'pickup',
                'suggested_order' => 1,
                'status' => $this->resolveStopStatus($data['status'], 'pickup'),
                'completed_at' => $this->resolveCompletedAt($data['status'], 'pickup'),
            ]);

            // Create delivery stop
            $deliveryStop = Stop::create([
                'shipment_id' => $shipment->id,
                'type' => 'delivery',
                'suggested_order' => 2,
                'status' => $this->resolveStopStatus($data['status'], 'delivery'),
                'completed_at' => $this->resolveCompletedAt($data['status'], 'delivery'),
                'photo_s3_key' => $data['status'] === 'delivered' ? "deliveries/{$store->branch_id}/" . today()->format('Y-m-d') . "/{$shipment->id}/sample.jpg" : null,
                'amount_collected' => $data['status'] === 'delivered' ? $data['amount_to_collect'] : null,
                'fail_reason' => $data['status'] === 'not_delivered' ? 'Cliente no estaba en casa' : null,
            ]);

            // Record initial status history (null → pending)
            ShipmentStatusHistory::create([
                'shipment_id' => $shipment->id,
                'from_status' => null,
                'to_status' => 'pending',
                'changed_by' => $store->user_id,
                'role_at_time' => 'store',
            ]);

            // Add intermediate status history entries based on final status
            $this->recordStatusHistory($shipment, $data['status'], $rootUser, $courier);
        }

        $this->command->info('✅ 20 órdenes creadas con historial de estados y paradas');
    }

    /**
     * Record intermediate status history for a shipment based on its final status.
     */
    private function recordStatusHistory(
        Shipment $shipment,
        string $finalStatus,
        User $rootUser,
        Courier $courier
    ): void {
        $transitions = match ($finalStatus) {
            'assigned' => [
                ['from' => 'pending', 'to' => 'assigned', 'user' => $rootUser, 'role' => 'admin'],
            ],
            'picked_up' => [
                ['from' => 'pending', 'to' => 'assigned', 'user' => $rootUser, 'role' => 'admin'],
                ['from' => 'assigned', 'to' => 'picked_up', 'user' => $courier->user, 'role' => 'courier'],
            ],
            'in_route' => [
                ['from' => 'pending', 'to' => 'assigned', 'user' => $rootUser, 'role' => 'admin'],
                ['from' => 'assigned', 'to' => 'picked_up', 'user' => $courier->user, 'role' => 'courier'],
                ['from' => 'picked_up', 'to' => 'in_route', 'user' => $courier->user, 'role' => 'courier'],
            ],
            'delivered' => [
                ['from' => 'pending', 'to' => 'assigned', 'user' => $rootUser, 'role' => 'admin'],
                ['from' => 'assigned', 'to' => 'picked_up', 'user' => $courier->user, 'role' => 'courier'],
                ['from' => 'picked_up', 'to' => 'in_route', 'user' => $courier->user, 'role' => 'courier'],
                ['from' => 'in_route', 'to' => 'delivered', 'user' => $courier->user, 'role' => 'courier'],
            ],
            'not_delivered' => [
                ['from' => 'pending', 'to' => 'assigned', 'user' => $rootUser, 'role' => 'admin'],
                ['from' => 'assigned', 'to' => 'picked_up', 'user' => $courier->user, 'role' => 'courier'],
                ['from' => 'picked_up', 'to' => 'not_delivered', 'user' => $courier->user, 'role' => 'courier'],
            ],
            default => [],
        };

        foreach ($transitions as $transition) {
            ShipmentStatusHistory::create([
                'shipment_id' => $shipment->id,
                'from_status' => $transition['from'],
                'to_status' => $transition['to'],
                'changed_by' => $transition['user']->id,
                'role_at_time' => $transition['role'],
            ]);
        }
    }

    private function resolveStopStatus(string $shipmentStatus, string $stopType): string
    {
        if ($stopType === 'pickup') {
            return in_array($shipmentStatus, ['pending', 'assigned']) ? 'pending' : 'completed';
        }

        // delivery stop
        return match ($shipmentStatus) {
            'delivered' => 'completed',
            'not_delivered' => 'failed',
            default => 'pending',
        };
    }

    private function resolveCompletedAt(string $shipmentStatus, string $stopType): ?string
    {
        if ($stopType === 'pickup' && in_array($shipmentStatus, ['picked_up', 'in_route', 'delivered', 'not_delivered'])) {
            return now()->subHours(rand(1, 5))->toDateTimeString();
        }

        if ($stopType === 'delivery' && in_array($shipmentStatus, ['delivered', 'not_delivered'])) {
            return now()->subHours(rand(0, 2))->toDateTimeString();
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function getShipmentsData(): array
    {
        $recipientNames = [
            'María García',
            'José Rodríguez',
            'Ana Martínez',
            'Luis González',
            'Carmen López',
            'Pablo Ramírez',
            'Laura Sánchez',
            'Miguel Torres',
            'Sandra Díaz',
            'Antonio Vargas',
            'Patricia Castro',
            'Juan Morales',
            'Claudia Reyes',
            'Roberto Flores',
            'Gabriela Herrera',
            'David Cruz',
            'Isabella Ortiz',
            'Fernando Medina',
            'Valeria Jiménez',
            'Ricardo Ruiz',
        ];

        $sectors = ['Piantini', 'Naco', 'Los Prados', 'Gazcue', 'Bella Vista', 'Evaristo Morales', 'La Esperilla', 'Quisqueya', 'Ciudad Nueva'];
        $statuses = ['pending', 'pending', 'assigned', 'assigned', 'picked_up', 'in_route', 'in_route', 'delivered', 'delivered', 'delivered', 'delivered', 'delivered', 'delivered', 'delivered', 'not_delivered', 'not_delivered', 'delivered', 'assigned', 'pending', 'in_route'];

        $shipments = [];
        foreach ($recipientNames as $i => $name) {
            $sector = $sectors[array_rand($sectors)];
            $shipments[] = [
                'recipient_name' => $name,
                'recipient_phone' => '+1809' . str_pad((string) (5550000 + $i), 7, '0', STR_PAD_LEFT),
                'address' => 'Calle ' . ($i + 1) . ' #' . rand(10, 200) . ', ' . $sector . ', Santo Domingo',
                'sector' => $sector,
                'amount_to_collect' => round(rand(500, 15000) / 100, 2) * 100,
                'payment_method' => ['cash', 'transfer', 'card'][array_rand(['cash', 'transfer', 'card'])],
                'payer' => ['store', 'customer'][array_rand(['store', 'customer'])],
                'status' => $statuses[$i],
                'weight_size' => ['small', 'medium', 'large'][array_rand(['small', 'medium', 'large'])],
                'notes' => $i % 3 === 0 ? 'Llamar antes de llegar.' : null,
            ];
        }

        return $shipments;
    }
}
