<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Create the four required roles
        $roles = ['root', 'admin', 'store', 'courier'];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web']
            );
        }

        $this->command->info('✅ Roles creados: ' . implode(', ', $roles));
    }
}
