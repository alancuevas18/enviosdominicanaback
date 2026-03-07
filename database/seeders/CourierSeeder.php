<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CourierSeeder extends Seeder
{
    public function run(): void
    {
        $sdn = Branch::where('name', 'Santo Domingo Norte')->firstOrFail();
        $santiago = Branch::where('name', 'Santiago')->firstOrFail();
        $sdEste = Branch::where('name', 'Santo Domingo Este')->firstOrFail();

        $couriersData = [
            // 2 couriers in SDN
            [
                'branch' => $sdn,
                'user' => ['name' => 'Juan Jiménez', 'email' => 'courier1@mensajeria.com'],
                'courier' => ['name' => 'Juan Jiménez', 'phone' => '+18094441001', 'vehicle' => 'Moto'],
            ],
            [
                'branch' => $sdn,
                'user' => ['name' => 'Pedro Herrera', 'email' => 'courier2@mensajeria.com'],
                'courier' => ['name' => 'Pedro Herrera', 'phone' => '+18094441002', 'vehicle' => 'Scooter'],
            ],
            // 1 courier in Santiago
            [
                'branch' => $santiago,
                'user' => ['name' => 'Carlos Ramírez', 'email' => 'courier3@mensajeria.com'],
                'courier' => ['name' => 'Carlos Ramírez', 'phone' => '+18094441003', 'vehicle' => 'Moto'],
            ],
            // 1 courier in SD Este
            [
                'branch' => $sdEste,
                'user' => ['name' => 'Luis Torres', 'email' => 'courier4@mensajeria.com'],
                'courier' => ['name' => 'Luis Torres', 'phone' => '+18094441004', 'vehicle' => 'Carro'],
            ],
        ];

        foreach ($couriersData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name' => $data['user']['name'],
                    'password' => Hash::make('Courier1234!'),
                    'active' => true,
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->hasRole('courier')) {
                $user->assignRole('courier');
            }

            Courier::firstOrCreate(
                ['user_id' => $user->id],
                array_merge($data['courier'], [
                    'branch_id' => $data['branch']->id,
                    'active' => true,
                    'rating_avg' => 0.00,
                    'rating_count' => 0,
                ])
            );
        }

        $this->command->info('✅ 4 mensajeros creados: 2 en SDN, 1 en Santiago, 1 en SD Este');
    }
}
