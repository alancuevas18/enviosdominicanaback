<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Courier;
use App\Models\Route;
use App\Models\Shipment;
use App\Models\Stop;
use App\Models\Store;
use App\Models\User;
use App\Notifications\ShipmentAssignedNotification;
use App\Notifications\ShipmentCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Create a branch with an admin user who has branch context in their token.
 * Returns [branch, adminUser, adminToken].
 *
 * @return array{0: Branch, 1: User, 2: string}
 */
function createAdminWithBranch(): array
{
    $branch    = Branch::factory()->create();
    $adminUser = User::factory()->create();
    $adminUser->assignRole('admin');
    $branch->admins()->attach($adminUser->id);
    $token = $adminUser->createToken('branch:' . $branch->id)->plainTextToken;

    return [$branch, $adminUser, $token];
}

/**
 * Create a store with a linked store user within a given branch.
 * Returns [store, storeUser, storeToken].
 *
 * @return array{0: Store, 1: User, 2: string}
 */
function createStoreWithUser(Branch $branch): array
{
    $storeUser = User::factory()->create();
    $storeUser->assignRole('store');
    $store = Store::factory()->for($branch)->create(['user_id' => $storeUser->id]);
    $token = $storeUser->createToken('api-token')->plainTextToken;

    return [$store, $storeUser, $token];
}

/**
 * Create a courier with a linked courier user within a given branch.
 * Returns [courier, courierUser, courierToken].
 *
 * @return array{0: Courier, 1: User, 2: string}
 */
function createCourierWithUser(Branch $branch): array
{
    $courierUser = User::factory()->create();
    $courierUser->assignRole('courier');
    $courier = Courier::factory()->for($branch)->create(['user_id' => $courierUser->id]);
    $token   = $courierUser->createToken('api-token')->plainTextToken;

    return [$courier, $courierUser, $token];
}

// ─────────────────────────────────────────────────────────────────────────────
// Role seeding
// ─────────────────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    foreach (['root', 'admin', 'store', 'courier'] as $role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// 1. Store creates a shipment
// ─────────────────────────────────────────────────────────────────────────────

describe('Shipment creation', function (): void {
    it('allows a store user to create a shipment and auto-creates 2 stops', function (): void {
        Notification::fake();

        [$branch,,] = createAdminWithBranch();
        [$store,, $storeToken] = createStoreWithUser($branch);

        $response = $this->withToken($storeToken)
            ->postJson('/api/v1/shipments', [
                'recipient_name'  => 'Juan Pérez',
                'recipient_phone' => '+18091234567',
                'address'         => 'Calle Falsa 123, Santo Domingo',
                'sector'          => 'Piantini',
                'amount_to_collect' => 500.00,
                'payment_method'  => 'cash',
                'payer'           => 'customer',
                'weight_size'     => 'medium',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.store_id', $store->id)
            ->assertJsonPath('data.branch_id', $branch->id);

        $shipmentId = $response->json('data.id');

        // 2 stops auto-created
        $this->assertDatabaseCount('stops', 2);
        $this->assertDatabaseHas('stops', ['shipment_id' => $shipmentId, 'type' => 'pickup']);
        $this->assertDatabaseHas('stops', ['shipment_id' => $shipmentId, 'type' => 'delivery']);

        // Status history recorded
        $this->assertDatabaseHas('shipment_status_history', [
            'shipment_id' => $shipmentId,
            'from_status' => null,
            'to_status'   => 'pending',
        ]);
    });

    it('notifies branch admins when a shipment is created', function (): void {
        Notification::fake();

        [$branch, $adminUser,] = createAdminWithBranch();
        [,, $storeToken] = createStoreWithUser($branch);

        $this->withToken($storeToken)
            ->postJson('/api/v1/shipments', [
                'recipient_name'  => 'María García',
                'recipient_phone' => '+18099876543',
                'address'         => 'Av. Independencia 55',
                'weight_size'     => 'small',
            ])
            ->assertStatus(201);

        Notification::assertSentTo($adminUser, ShipmentCreatedNotification::class);
    });

    it('rejects creation with invalid phone format', function (): void {
        [$branch,,] = createAdminWithBranch();
        [,, $storeToken] = createStoreWithUser($branch);

        $this->withToken($storeToken)
            ->postJson('/api/v1/shipments', [
                'recipient_name'  => 'Test',
                'recipient_phone' => '8091234567', // missing country code
                'address'         => 'Calle Test 1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Admin assigns courier
// ─────────────────────────────────────────────────────────────────────────────

describe('Shipment assignment', function (): void {
    it('allows admin to assign a courier and creates today\'s route', function (): void {
        Notification::fake();

        [$branch,, $adminToken] = createAdminWithBranch();
        [$store,,] = createStoreWithUser($branch);
        [$courier,,] = createCourierWithUser($branch);

        $shipment = Shipment::factory()
            ->for($branch)
            ->for($store)
            ->pending()
            ->create();

        $pickupStop   = Stop::factory()->pickup()->create(['shipment_id' => $shipment->id]);
        $deliveryStop = Stop::factory()->delivery()->create(['shipment_id' => $shipment->id]);

        $response = $this->withToken($adminToken)
            ->postJson("/api/v1/shipments/{$shipment->id}/assign", [
                'courier_id' => $courier->id,
                'stop_order' => [
                    0 => $pickupStop->id,
                    1 => $deliveryStop->id,
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.courier.id', $courier->id);

        $shipment->refresh();
        expect($shipment->status)->toBe('assigned')
            ->and($shipment->courier_id)->toBe($courier->id);

        // Route created for today (date assertion uses LIKE to handle SQLite datetime format)
        expect(
            DB::table('routes')
                ->where('courier_id', $courier->id)
                ->whereRaw("date LIKE ?", [today()->toDateString() . '%'])
                ->exists()
        )->toBeTrue();
    });

    it('notifies the store when courier is assigned', function (): void {
        Notification::fake();

        [$branch,, $adminToken] = createAdminWithBranch();
        [$store, $storeUser,] = createStoreWithUser($branch);
        [$courier,,] = createCourierWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->pending()->create();
        $pickup   = Stop::factory()->pickup()->create(['shipment_id' => $shipment->id]);
        $delivery = Stop::factory()->delivery()->create(['shipment_id' => $shipment->id]);

        $this->withToken($adminToken)
            ->postJson("/api/v1/shipments/{$shipment->id}/assign", [
                'courier_id' => $courier->id,
                'stop_order' => [0 => $pickup->id, 1 => $delivery->id],
            ])
            ->assertStatus(200);

        Notification::assertSentTo($storeUser, ShipmentAssignedNotification::class);
    });

    it('prevents admin from assigning a courier from a different branch', function (): void {
        [$branch,, $adminToken] = createAdminWithBranch();
        [$store,,] = createStoreWithUser($branch);

        $otherBranch  = Branch::factory()->create();
        [$otherCourier,,] = createCourierWithUser($otherBranch);

        $shipment = Shipment::factory()->for($branch)->for($store)->pending()->create();
        Stop::factory()->pickup()->create(['shipment_id' => $shipment->id]);
        Stop::factory()->delivery()->create(['shipment_id' => $shipment->id]);

        $this->withToken($adminToken)
            ->postJson("/api/v1/shipments/{$shipment->id}/assign", [
                'courier_id' => $otherCourier->id,
                'stop_order' => [],
            ])
            ->assertStatus(422); // courier_id must exist in branch scope
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Courier completes pickup
// ─────────────────────────────────────────────────────────────────────────────

describe('Pickup completion', function (): void {
    it('marks shipment as picked_up when courier completes the pickup stop', function (): void {
        Notification::fake();

        [$branch,, $adminToken] = createAdminWithBranch();
        [$store,,] = createStoreWithUser($branch);
        [$courier,, $courierToken] = createCourierWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->create([
            'courier_id' => $courier->id,
            'status'     => 'assigned',
        ]);

        $pickupStop = Stop::factory()->pickup()->create([
            'shipment_id' => $shipment->id,
            'status'      => 'pending',
        ]);

        // Create route so courier authorization passes
        $route = Route::create([
            'courier_id' => $courier->id,
            'branch_id'  => $branch->id,
            'date'       => today()->toDateString(),
            'status'     => 'open',
        ]);
        $route->stops()->attach($pickupStop->id, ['suggested_order' => 1]);

        $response = $this->withToken($courierToken)
            ->patchJson("/api/v1/stops/{$pickupStop->id}/status", [
                'action' => 'complete_pickup',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $shipment->refresh();
        expect($shipment->status)->toBe('picked_up');

        $this->assertDatabaseHas('stops', ['id' => $pickupStop->id, 'status' => 'completed']);
        $this->assertDatabaseHas('shipment_status_history', [
            'shipment_id' => $shipment->id,
            'from_status' => 'assigned',
            'to_status'   => 'picked_up',
        ]);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. Courier completes delivery
// ─────────────────────────────────────────────────────────────────────────────

describe('Delivery completion', function (): void {
    it('marks shipment as delivered when courier completes delivery stop with photo', function (): void {
        Notification::fake();

        [$branch,,] = createAdminWithBranch();
        [$store,,] = createStoreWithUser($branch);
        [$courier,, $courierToken] = createCourierWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->create([
            'courier_id' => $courier->id,
            'status'     => 'picked_up',
        ]);

        $deliveryStop = Stop::factory()->delivery()->create([
            'shipment_id'  => $shipment->id,
            'status'       => 'pending',
        ]);

        // Set a valid photo_s3_key so delivery can be completed
        $deliveryStop->update([
            'photo_s3_key' => "deliveries/{$branch->id}/" . today()->toDateString() . "/{$deliveryStop->id}/photo.jpg",
        ]);


        $route = Route::create([
            'courier_id' => $courier->id,
            'branch_id'  => $branch->id,
            'date'       => today()->toDateString(),
            'status'     => 'open',
        ]);
        $route->stops()->attach($deliveryStop->id, ['suggested_order' => 1]);

        $response = $this->withToken($courierToken)
            ->patchJson("/api/v1/stops/{$deliveryStop->id}/status", [
                'action'           => 'complete_delivery',
                'amount_collected' => 500.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $shipment->refresh();
        expect($shipment->status)->toBe('delivered');

        $this->assertDatabaseHas('shipment_status_history', [
            'shipment_id' => $shipment->id,
            'from_status' => 'picked_up',
            'to_status'   => 'delivered',
        ]);
    });

    it('rejects delivery completion without a photo', function (): void {
        [$branch,,] = createAdminWithBranch();
        [$store,,] = createStoreWithUser($branch);
        [$courier,, $courierToken] = createCourierWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->create([
            'courier_id' => $courier->id,
            'status'     => 'picked_up',
        ]);

        $deliveryStop = Stop::factory()->delivery()->create([
            'shipment_id'  => $shipment->id,
            'status'       => 'pending',
            'photo_s3_key' => null, // no photo
        ]);

        $route = Route::create([
            'courier_id' => $courier->id,
            'branch_id'  => $branch->id,
            'date'       => today()->toDateString(),
            'status'     => 'open',
        ]);
        $route->stops()->attach($deliveryStop->id, ['suggested_order' => 1]);

        $this->withToken($courierToken)
            ->patchJson("/api/v1/stops/{$deliveryStop->id}/status", [
                'action' => 'complete_delivery',
            ])
            ->assertStatus(422);
    });

    it('marks shipment as not_delivered on fail action', function (): void {
        Notification::fake();

        [$branch,,] = createAdminWithBranch();
        [$store,,] = createStoreWithUser($branch);
        [$courier,, $courierToken] = createCourierWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->create([
            'courier_id' => $courier->id,
            'status'     => 'picked_up',
        ]);

        $deliveryStop = Stop::factory()->delivery()->create([
            'shipment_id' => $shipment->id,
            'status'      => 'pending',
        ]);

        $route = Route::create([
            'courier_id' => $courier->id,
            'branch_id'  => $branch->id,
            'date'       => today()->toDateString(),
            'status'     => 'open',
        ]);
        $route->stops()->attach($deliveryStop->id, ['suggested_order' => 1]);

        $this->withToken($courierToken)
            ->patchJson("/api/v1/stops/{$deliveryStop->id}/status", [
                'action'      => 'fail',
                'fail_reason' => 'Nadie en casa',
            ])
            ->assertStatus(200);

        $shipment->refresh();
        expect($shipment->status)->toBe('not_delivered');
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. Store rates a delivered shipment
// ─────────────────────────────────────────────────────────────────────────────

describe('Shipment rating', function (): void {
    it('allows the store to rate a delivered shipment', function (): void {
        Notification::fake();

        [$branch,,] = createAdminWithBranch();
        [$store,, $storeToken] = createStoreWithUser($branch);
        [$courier,,] = createCourierWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->create([
            'courier_id' => $courier->id,
            'status'     => 'delivered',
        ]);

        $this->withToken($storeToken)
            ->postJson("/api/v1/shipments/{$shipment->id}/rate", [
                'score'   => 5,
                'comment' => '¡Excellent service!',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('shipment_ratings', [
            'shipment_id' => $shipment->id,
            'store_id'    => $store->id,
            'courier_id'  => $courier->id,
            'score'       => 5,
        ]);
    });

    it('prevents rating a shipment that is not delivered', function (): void {
        [$branch,,] = createAdminWithBranch();
        [$store,, $storeToken] = createStoreWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->pending()->create();

        $this->withToken($storeToken)
            ->postJson("/api/v1/shipments/{$shipment->id}/rate", [
                'score' => 4,
            ])
            ->assertStatus(403);
    });

    it('prevents rating a shipment twice', function (): void {
        Notification::fake();

        [$branch,,] = createAdminWithBranch();
        [$store,, $storeToken] = createStoreWithUser($branch);
        [$courier,,] = createCourierWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->create([
            'courier_id' => $courier->id,
            'status'     => 'delivered',
        ]);

        // First rating
        $this->withToken($storeToken)
            ->postJson("/api/v1/shipments/{$shipment->id}/rate", ['score' => 5])
            ->assertStatus(201);

        // Second rating
        $this->withToken($storeToken)
            ->postJson("/api/v1/shipments/{$shipment->id}/rate", ['score' => 3])
            ->assertStatus(403);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// 6. Update shipment (FormRequest)
// ─────────────────────────────────────────────────────────────────────────────

describe('Shipment update', function (): void {
    it('allows store to edit a pending shipment', function (): void {
        [$branch,,] = createAdminWithBranch();
        [$store,, $storeToken] = createStoreWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->pending()->create();

        $this->withToken($storeToken)
            ->patchJson("/api/v1/shipments/{$shipment->id}", [
                'recipient_name' => 'Nombre Actualizado',
                'sector'         => 'Naco',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.recipient_name', 'Nombre Actualizado');
    });

    it('prevents editing a non-pending shipment', function (): void {
        [$branch,,] = createAdminWithBranch();
        [$store,, $storeToken] = createStoreWithUser($branch);
        [$courier,,] = createCourierWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->create([
            'courier_id' => $courier->id,
            'status'     => 'assigned',
        ]);

        $this->withToken($storeToken)
            ->patchJson("/api/v1/shipments/{$shipment->id}", [
                'recipient_name' => 'Intento Fallido',
            ])
            ->assertStatus(403);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// 7. Soft deletes
// ─────────────────────────────────────────────────────────────────────────────

describe('Soft deletes', function (): void {
    it('soft-deletes a shipment — record remains in DB with deleted_at set', function (): void {
        [$branch,,] = createAdminWithBranch();
        [$store,,] = createStoreWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->pending()->create();
        $shipment->delete();

        $this->assertSoftDeleted('shipments', ['id' => $shipment->id]);
        expect(Shipment::find($shipment->id))->toBeNull();
        expect(Shipment::withTrashed()->find($shipment->id))->not->toBeNull();
    });

    it('soft-deletes a store while preserving shipment history', function (): void {
        [$branch,,] = createAdminWithBranch();
        [$store,,] = createStoreWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->pending()->create();
        $store->delete();

        $this->assertSoftDeleted('stores', ['id' => $store->id]);
        // Shipment still exists with reference to store
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id]);
    });

    it('soft-deletes a courier while preserving shipment history', function (): void {
        [$branch,,] = createAdminWithBranch();
        [$store,,] = createStoreWithUser($branch);
        [$courier,,] = createCourierWithUser($branch);

        $shipment = Shipment::factory()->for($branch)->for($store)->create([
            'courier_id' => $courier->id,
            'status'     => 'assigned',
        ]);

        $courier->delete();

        $this->assertSoftDeleted('couriers', ['id' => $courier->id]);
        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'courier_id' => $courier->id]);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// 8. Authorization / role isolation
// ─────────────────────────────────────────────────────────────────────────────

describe('Authorization', function (): void {
    it('prevents a courier from creating shipments', function (): void {
        [$branch,,] = createAdminWithBranch();
        [,, $courierToken] = createCourierWithUser($branch);

        $this->withToken($courierToken)
            ->postJson('/api/v1/shipments', [
                'recipient_name'  => 'Test',
                'recipient_phone' => '+18091234567',
                'address'         => 'Test',
            ])
            ->assertStatus(403);
    });

    it('prevents unauthenticated access to shipments', function (): void {
        $this->getJson('/api/v1/shipments')
            ->assertStatus(401);
    });

    it('prevents admin from viewing shipments of another branch', function (): void {
        [$branch1,, $adminToken] = createAdminWithBranch();
        [$branch2,,] = createAdminWithBranch();
        [$store2,,] = createStoreWithUser($branch2);

        $shipment = Shipment::factory()->for($branch2)->for($store2)->pending()->create();

        // admin of branch1 tries to view shipment in branch2
        $this->withToken($adminToken)
            ->getJson("/api/v1/shipments/{$shipment->id}")
            ->assertStatus(404); // BranchScope filters it out → ModelNotFoundException → 404
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// 9. Queue: notifications dispatched to queue
// ─────────────────────────────────────────────────────────────────────────────

describe('Queued notifications', function (): void {
    it('queues ShipmentCreatedNotification instead of sending synchronously', function (): void {
        Queue::fake();
        Notification::fake();

        [$branch,,] = createAdminWithBranch();
        [,, $storeToken] = createStoreWithUser($branch);

        $this->withToken($storeToken)
            ->postJson('/api/v1/shipments', [
                'recipient_name'  => 'Queue Test',
                'recipient_phone' => '+18091112233',
                'address'         => 'Test Street 1',
            ])
            ->assertStatus(201);

        // ShouldQueue means it lands on a queue job, not sent inline
        Notification::assertCount(1);
        Notification::assertSentTo(
            User::role('admin')->first(),
            ShipmentCreatedNotification::class
        );
    });
});
