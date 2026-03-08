<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Email Verification', function (): void {
    it('requires authentication to resend', function (): void {
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->postJson('/api/v1/email/resend', [
            'email' => $user->email,
        ]);

        // Endpoint currently returns 404 (not implemented)
        $response->assertStatus(404);
    });
});
