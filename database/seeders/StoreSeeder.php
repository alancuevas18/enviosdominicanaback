<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $sdn = Branch::where('name', 'Santo Domingo Norte')->firstOrFail();

        $storesData = [
            [
                'user' => ['name' => 'Tienda Fashion RD', 'email' => 'tienda1@mensajeria.com'],
                'store' => [
                    'name' => 'Fashion RD',
                    'legal_name' => 'Fashion RD S.R.L.',
                    'phone' => '+18093331001',
                    'whatsapp' => '+18093331001',
                    'email' => 'ventas@fashionrd.com',
                    'address' => 'Calle Principal #23, Los Prados, SDN',
                    'sector' => 'Los Prados',
                    'city' => 'Santo Domingo Norte',
                    'province' => 'Santo Domingo',
                    'contact_person' => 'María García',
                    'default_notification_message' => 'Tu pedido de Fashion RD fue entregado a {recipient_name}. ¡Gracias por tu compra! Orden #{order_id}.',
                ],
            ],
            [
                'user' => ['name' => 'Tech Store SDN', 'email' => 'tienda2@mensajeria.com'],
                'store' => [
                    'name' => 'Tech Store SDN',
                    'phone' => '+18093331002',
                    'email' => 'info@techstoresdn.com',
                    'address' => 'Av. Independencia #100, SDN',
                    'sector' => 'Centro',
                    'city' => 'Santo Domingo Norte',
                    'province' => 'Santo Domingo',
                    'contact_person' => 'Carlos Pérez',
                    'default_notification_message' => 'Tu pedido de Tech Store ha llegado. ¡Disfrútalo! Orden #{order_id}.',
                ],
            ],
            [
                'user' => ['name' => 'Farmacia El Bienestar', 'email' => 'tienda3@mensajeria.com'],
                'store' => [
                    'name' => 'Farmacia El Bienestar',
                    'phone' => '+18093331003',
                    'address' => 'Calle Duarte #5, Villa Mella',
                    'sector' => 'Villa Mella',
                    'city' => 'Santo Domingo Norte',
                    'province' => 'Santo Domingo',
                    'contact_person' => 'Ana Martínez',
                ],
            ],
            [
                'user' => ['name' => 'Super Mercado El Barrio', 'email' => 'tienda4@mensajeria.com'],
                'store' => [
                    'name' => 'Super El Barrio',
                    'phone' => '+18093331004',
                    'address' => 'Av. Jacobo Majluta #200, Guaricano',
                    'sector' => 'Guaricano',
                    'city' => 'Santo Domingo Norte',
                    'province' => 'Santo Domingo',
                    'contact_person' => 'Pedro Rodríguez',
                ],
            ],
            [
                'user' => ['name' => 'Ropa Infantil Bambin', 'email' => 'tienda5@mensajeria.com'],
                'store' => [
                    'name' => 'Bambin Kids',
                    'phone' => '+18093331005',
                    'address' => 'Plaza Central, Km 9 Autopista Duarte',
                    'sector' => 'Km 9',
                    'city' => 'Santo Domingo Norte',
                    'province' => 'Santo Domingo',
                    'contact_person' => 'Rosa Jiménez',
                ],
            ],
        ];

        foreach ($storesData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name' => $data['user']['name'],
                    'password' => Hash::make('Store1234!'),
                    'active' => true,
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->hasRole('store')) {
                $user->assignRole('store');
            }

            Store::firstOrCreate(
                ['user_id' => $user->id],
                array_merge($data['store'], [
                    'branch_id' => $sdn->id,
                    'active' => true,
                ])
            );
        }

        $this->command->info('✅ 5 tiendas creadas en Santo Domingo Norte');
    }
}
