<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RootUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ROOT_EMAIL', 'root@mensajeria.com');
        $password = env('ROOT_PASSWORD', 'RootPassword123!');
        $name = env('ROOT_NAME', 'Root Admin');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Only root can assign root role — never from public endpoint
        if (! $user->hasRole('root')) {
            $user->assignRole('root');
        }

        $this->command->info("✅ Usuario root creado: {$email}");
    }
}
