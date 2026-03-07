<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: roles → root user → branches → admins → stores → couriers → shipments
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            RootUserSeeder::class,
            BranchSeeder::class,
            AdminSeeder::class,
            StoreSeeder::class,
            CourierSeeder::class,
            ShipmentSeeder::class,
        ]);
    }
}
