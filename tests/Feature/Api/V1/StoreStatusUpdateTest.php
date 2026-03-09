<?php

declare(strict_types=1);

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed();
});

test('store user can update their store status via profile endpoint', function (): void {
    $store = Store::first();
    $user = $store->user;

    $token = $user->createToken('test')->plainTextToken;

    $initialStatus = $store->active;
    $newStatus = !$initialStatus;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->putJson('/api/v1/profile/store', [
            'active' => $newStatus,
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.active', $newStatus);

    $store->refresh();
    expect($store->active)->toBe($newStatus);
});

test('store user can toggle their store status multiple times', function (): void {
    $store = Store::first();
    $user = $store->user;
    $token = $user->createToken('test')->plainTextToken;

    // First toggle: inactive
    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->putJson('/api/v1/profile/store', ['active' => false]);
    $response->assertOk()
        ->assertJsonPath('data.active', false);

    // Second toggle: active
    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->putJson('/api/v1/profile/store', ['active' => true]);
    $response->assertOk()
        ->assertJsonPath('data.active', true);
});

test('store user cannot update another stores status', function (): void {
    $store1 = Store::first();
    $store2 = Store::find(Store::count() > 1 ? 2 : 1);

    if ($store1->id === $store2->id) {
        $this->markTestSkipped('Need at least 2 stores for this test');
    }

    $user1 = $store1->user;
    $token = $user1->createToken('test')->plainTextToken;

    $newStatus = !$store2->active;

    // Try to update store2 from store1's user
    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->putJson("/api/v1/stores/{$store2->id}", [
            'active' => $newStatus,
        ]);

    // Store users cannot access PUT /api/v1/stores/{id} - should be 403
    $response->assertStatus(403);
});

test('admin user can update any store status via stores endpoint', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->assignRole('admin');
    $admin->branches()->attach(\App\Models\Branch::first());

    $store = Store::first();
    $initialStatus = $store->active;
    $newStatus = !$initialStatus;

    $token = $admin->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->putJson("/api/v1/stores/{$store->id}", [
            'active' => $newStatus,
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.active', $newStatus);

    $store->refresh();
    expect($store->active)->toBe($newStatus);
});

test('root user can update any store status', function (): void {
    $root = User::factory()->create(['active' => true]);
    $root->assignRole('root');

    $store = Store::first();
    $initialStatus = $store->active;
    $newStatus = !$initialStatus;

    $token = $root->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->putJson("/api/v1/stores/{$store->id}", [
            'active' => $newStatus,
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.active', $newStatus);

    $store->refresh();
    expect($store->active)->toBe($newStatus);
});

test('root can view all stores including inactive ones with with_inactive parameter', function (): void {
    $root = User::factory()->create(['active' => true]);
    $root->assignRole('root');
    $token = $root->createToken('test')->plainTextToken;

    // Deactivate one store
    $inactiveStore = Store::first();
    $inactiveStore->update(['active' => false]);

    // Without with_inactive - should only get active stores
    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/v1/stores');

    $response->assertOk();
    $activeCount = $response->json('data');
    expect($activeCount)->toBeArray();

    // All returned stores should be active
    foreach ($activeCount as $store) {
        expect($store['active'])->toBe(true);
    }

    // With with_inactive=true - should get all stores (active and inactive)
    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->getJson('/api/v1/stores?with_inactive=true');

    $response->assertOk();
    $allStores = $response->json('data');

    // Should have at least one inactive store
    $hasInactive = false;
    foreach ($allStores as $store) {
        if (!$store['active']) {
            $hasInactive = true;
            break;
        }
    }
    expect($hasInactive)->toBe(true);
});

test('admin can view all stores in their branch including inactive ones with with_inactive parameter', function (): void {
    $admin = User::factory()->create(['active' => true]);
    $admin->assignRole('admin');
    $branch = \App\Models\Branch::first();
    $admin->branches()->attach($branch);
    $token = $admin->createToken('test')->plainTextToken;

    // Deactivate one store in the branch
    $inactiveStore = Store::where('branch_id', $branch->id)->first();
    $inactiveStore->update(['active' => false]);

    // Without with_inactive - should only get active stores
    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->withHeader('X-Branch-Id', (string) $branch->id)
        ->getJson('/api/v1/stores');

    $response->assertOk();
    $activeStores = $response->json('data');

    // All returned stores should be active
    foreach ($activeStores as $store) {
        expect($store['active'])->toBe(true);
    }

    // With with_inactive=true - should get all stores
    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->withHeader('X-Branch-Id', (string) $branch->id)
        ->getJson('/api/v1/stores?with_inactive=true');

    $response->assertOk();
    $allStores = $response->json('data');

    // Should have at least one inactive store
    $hasInactive = false;
    foreach ($allStores as $store) {
        if (!$store['active']) {
            $hasInactive = true;
            break;
        }
    }
    expect($hasInactive)->toBe(true);
});
