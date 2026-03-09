<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * @return array{0: User, 1: string}
 */
function createRootWithToken(): array
{
    $root = User::factory()->create();
    $root->assignRole('root');
    $token = $root->createToken('api-token')->plainTextToken;

    return [$root, $token];
}

beforeEach(function (): void {
    foreach (['root', 'admin', 'store', 'courier'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

describe('User management', function (): void {
    it('allows root to list users with filters', function (): void {
        [, $rootToken] = createRootWithToken();

        $branch = Branch::factory()->create();

        $admin = User::factory()->create([
            'email' => 'admin-panel@mensajeria.com',
            'active' => true,
        ]);
        $admin->assignRole('admin');
        $admin->branches()->attach($branch->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $rootToken)
            ->getJson('/api/v1/users?role=admin&active=1');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'admin-panel@mensajeria.com')
            ->assertJsonPath('data.0.role', 'admin');
    });

    it('forbids admin users from accessing user management endpoints', function (): void {
        $branch = Branch::factory()->create();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->branches()->attach($branch->id);
        $adminToken = $admin->createToken('branch:' . $branch->id)->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/v1/users')
            ->assertForbidden();
    });

    it('allows root to create admin users and assign branches', function (): void {
        [, $rootToken] = createRootWithToken();

        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();

        $response = $this->withHeader('Authorization', 'Bearer ' . $rootToken)
            ->postJson('/api/v1/users/admins', [
                'name' => 'Admin Cliente',
                'email' => 'admin.cliente@mensajeria.com',
                'phone' => '+18091230000',
                'branch_ids' => [$branch1->id, $branch2->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin.cliente@mensajeria.com')
            ->assertJsonPath('data.user.role', 'admin');

        expect(
            User::query()
                ->where('email', 'admin.cliente@mensajeria.com')
                ->where('active', true)
                ->exists()
        )->toBeTrue();

        /** @var User $createdAdmin */
        $createdAdmin = User::query()->where('email', 'admin.cliente@mensajeria.com')->firstOrFail();

        expect($createdAdmin->hasRole('admin'))->toBeTrue();
        expect(
            DB::table('branch_user')
                ->where('user_id', $createdAdmin->id)
                ->where('branch_id', $branch1->id)
                ->exists()
        )->toBeTrue();
        expect(
            DB::table('branch_user')
                ->where('user_id', $createdAdmin->id)
                ->where('branch_id', $branch2->id)
                ->exists()
        )->toBeTrue();
    });

    it('allows root to deactivate and reactivate users', function (): void {
        [, $rootToken] = createRootWithToken();

        $user = User::factory()->create([
            'name' => 'Usuario Inicial',
            'email' => 'usuario.inicial@mensajeria.com',
            'phone' => '+18091110000',
            'active' => true,
        ]);
        $user->assignRole('store');

        $response = $this->withHeader('Authorization', 'Bearer ' . $rootToken)
            ->patchJson("/api/v1/users/{$user->id}", [
                'name' => 'Usuario Actualizado',
                'phone' => '+18091119999',
                'active' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Usuario Actualizado')
            ->assertJsonPath('data.active', false);

        expect(
            User::query()
                ->where('id', $user->id)
                ->where('name', 'Usuario Actualizado')
                ->where('phone', '+18091119999')
                ->where('active', false)
                ->exists()
        )->toBeTrue();

        $reactivateResponse = $this->withHeader('Authorization', 'Bearer ' . $rootToken)
            ->patchJson("/api/v1/users/{$user->id}", [
                'active' => true,
            ]);

        $reactivateResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.active', true);

        expect(
            User::query()
                ->where('id', $user->id)
                ->where('active', true)
                ->exists()
        )->toBeTrue();
    });

    it('prevents deactivating the only active root user', function (): void {
        /** @var User $root */
        [$root, $rootToken] = createRootWithToken();

        $response = $this->withHeader('Authorization', 'Bearer ' . $rootToken)
            ->patchJson("/api/v1/users/{$root->id}", [
                'active' => false,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    });

    it('soft-deletes users when root destroys them', function (): void {
        [, $rootToken] = createRootWithToken();

        $managedUser = User::factory()->create([
            'active' => true,
        ]);
        $managedUser->assignRole('store');

        $response = $this->withHeader('Authorization', 'Bearer ' . $rootToken)
            ->deleteJson("/api/v1/users/{$managedUser->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        expect(User::query()->whereKey($managedUser->id)->exists())->toBeFalse();

        expect(
            User::withTrashed()
                ->whereKey($managedUser->id)
                ->where('active', false)
                ->whereNotNull('deleted_at')
                ->exists()
        )->toBeTrue();
    });

    it('allows root to sync branches for admin users only', function (): void {
        [, $rootToken] = createRootWithToken();

        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $admin->branches()->attach($branch1->id);

        $response = $this->withHeader('Authorization', 'Bearer ' . $rootToken)
            ->putJson("/api/v1/users/{$admin->id}/branches", [
                'branch_ids' => [$branch2->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $admin->id);

        expect(
            DB::table('branch_user')
                ->where('user_id', $admin->id)
                ->where('branch_id', $branch2->id)
                ->exists()
        )->toBeTrue();
        expect(
            DB::table('branch_user')
                ->where('user_id', $admin->id)
                ->where('branch_id', $branch1->id)
                ->exists()
        )->toBeFalse();

        $storeUser = User::factory()->create();
        $storeUser->assignRole('store');

        $this->withHeader('Authorization', 'Bearer ' . $rootToken)
            ->putJson("/api/v1/users/{$storeUser->id}/branches", [
                'branch_ids' => [$branch1->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    });
});
