<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $sdn = Branch::where('name', 'Santo Domingo Norte')->firstOrFail();
        $santiago = Branch::where('name', 'Santiago')->firstOrFail();

        // Admin 1: assigned to Santo Domingo Norte only
        $admin1 = User::firstOrCreate(
            ['email' => 'admin1@mensajeria.com'],
            [
                'name' => 'Admin SDN',
                'password' => Hash::make('Admin1234!'),
                'phone' => '+18092221001',
                'active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! $admin1->hasRole('admin')) {
            $admin1->assignRole('admin');
        }
        $admin1->branches()->syncWithoutDetaching([$sdn->id]);

        // Admin 2: assigned to SDN and Santiago (multi-branch)
        $admin2 = User::firstOrCreate(
            ['email' => 'admin2@mensajeria.com'],
            [
                'name' => 'Admin SDN & Santiago',
                'password' => Hash::make('Admin1234!'),
                'phone' => '+18092221002',
                'active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! $admin2->hasRole('admin')) {
            $admin2->assignRole('admin');
        }
        $admin2->branches()->syncWithoutDetaching([$sdn->id, $santiago->id]);

        $this->command->info('✅ Admins creados: admin1@mensajeria.com (SDN), admin2@mensajeria.com (SDN + Santiago)');
    }
}
