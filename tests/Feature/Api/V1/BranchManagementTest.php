<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::firstOrCreate(['name' => 'root', 'guard_name' => 'web']);
});

describe('Branch management', function (): void {
    it('allows root to deactivate a branch without deleting it', function (): void {
        $root = User::factory()->create(['active' => true]);
        $root->assignRole('root');
        $token = $root->createToken('api-token')->plainTextToken;

        $branch = Branch::factory()->create(['active' => true]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson("/api/v1/branches/{$branch->id}", [
                'active' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.active', false);

        expect(
            Branch::query()
                ->whereKey($branch->id)
                ->where('active', false)
                ->whereNull('deleted_at')
                ->exists()
        )->toBeTrue();
    });

    it('allows root to soft-delete a branch', function (): void {
        $root = User::factory()->create(['active' => true]);
        $root->assignRole('root');
        $token = $root->createToken('api-token')->plainTextToken;

        $branch = Branch::factory()->create(['active' => true]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson("/api/v1/branches/{$branch->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        expect(Branch::query()->whereKey($branch->id)->exists())->toBeFalse();

        expect(
            Branch::withTrashed()
                ->whereKey($branch->id)
                ->where('active', false)
                ->whereNotNull('deleted_at')
                ->exists()
        )->toBeTrue();
    });
});
