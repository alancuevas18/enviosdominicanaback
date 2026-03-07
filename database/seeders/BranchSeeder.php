<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Santo Domingo Norte',
                'city' => 'Santo Domingo Norte',
                'address' => 'Av. Hermanas Mirabal, Santo Domingo Norte',
                'phone' => '+18091111001',
                'active' => true,
                'notes' => 'Sucursal principal de la zona norte.',
            ],
            [
                'name' => 'Santiago',
                'city' => 'Santiago de los Caballeros',
                'address' => 'Av. Juan Pablo Duarte, Santiago',
                'phone' => '+18091111002',
                'active' => true,
                'notes' => 'Sucursal de Santiago.',
            ],
            [
                'name' => 'Santo Domingo Este',
                'city' => 'Santo Domingo Este',
                'address' => 'Av. San Vicente de Paúl, SD Este',
                'phone' => '+18091111003',
                'active' => true,
                'notes' => 'Sucursal de la zona este.',
            ],
        ];

        foreach ($branches as $branchData) {
            Branch::firstOrCreate(['name' => $branchData['name']], $branchData);
        }

        $this->command->info('✅ Sucursales creadas: Santo Domingo Norte, Santiago, Santo Domingo Este');
    }
}
