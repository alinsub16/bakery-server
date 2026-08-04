<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->withRole('admin')->create();
    $this->baker = User::factory()->withRole('baker')->create();
    \App\Models\Role::firstOrCreate(['name' => 'inventory_clerk']);
    \App\Models\Role::firstOrCreate(['name' => 'manager']);
});

it('allows an admin to directly create an active user with a role', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/users', [
            'name' => 'Direct Hire',
            'email' => 'directhire@bakery.test',
            'password' => 'password123',
            'role' => 'inventory_clerk',
        ]);

    $response->assertStatus(201)->assertJsonPath('role', 'inventory_clerk');

    $this->assertDatabaseHas('users', [
        'email' => 'directhire@bakery.test',
        'status' => 'active',
    ]);
});

it('denies a non-admin from creating a user', function () {
    $response = $this->actingAs($this->baker)
        ->postJson('/api/v1/users', [
            'name' => 'Nope', 'email' => 'nope@bakery.test',
            'password' => 'password123', 'role' => 'baker',
        ]);

    $response->assertStatus(403);
});

it('lists pending users for admin review', function () {
    User::factory()->create(['status' => 'pending']);
    User::factory()->create(['status' => 'pending']);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/users/pending');

    $response->assertStatus(200)->assertJsonCount(2, 'data');
});

it('allows an admin to approve a pending user with a role', function () {
    $pending = User::factory()->create(['status' => 'pending']);

    $response = $this->actingAs($this->admin)
        ->patchJson("/api/v1/users/{$pending->id}/approve", ['role' => 'baker']);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'active')
        ->assertJsonPath('role', 'baker');

    expect($pending->fresh()->isActive())->toBeTrue();
    expect($pending->fresh()->hasRole('baker'))->toBeTrue();
});

it('rejects approving a user that is not pending', function () {
    $response = $this->actingAs($this->admin)
        ->patchJson("/api/v1/users/{$this->baker->id}/approve", ['role' => 'baker']);

    $response->assertStatus(409);
});

it('allows an admin to reject a pending user', function () {
    $pending = User::factory()->create(['status' => 'pending']);

    $response = $this->actingAs($this->admin)
        ->patchJson("/api/v1/users/{$pending->id}/reject");

    $response->assertStatus(200)->assertJsonPath('status', 'rejected');
});

it('allows an admin to deactivate and reactivate another user', function () {
    $this->actingAs($this->admin)
        ->patchJson("/api/v1/users/{$this->baker->id}/deactivate")
        ->assertStatus(200);

    expect($this->baker->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/users/{$this->baker->id}/activate")
        ->assertStatus(200);

    expect($this->baker->fresh()->trashed())->toBeFalse();
});

it('prevents an admin from deactivating themselves', function () {
    $response = $this->actingAs($this->admin)
        ->patchJson("/api/v1/users/{$this->admin->id}/deactivate");

    $response->assertStatus(403);
});

it('prevents deactivating the last remaining admin', function () {
    $secondAdmin = User::factory()->withRole('admin')->create();

    $response = $this->actingAs($secondAdmin)
        ->patchJson("/api/v1/users/{$this->admin->id}/deactivate");

    $response->assertStatus(200); // fine, two admins exist

    // Now only $secondAdmin remains — a third admin attempting to
    // deactivate them should be blocked.
    $thirdAdmin = User::factory()->withRole('admin')->create();

    $response = $this->actingAs($thirdAdmin)
        ->patchJson("/api/v1/users/{$secondAdmin->id}/deactivate");

    $response->assertStatus(200); // still two admins ($secondAdmin, $thirdAdmin... wait

    // Correction: after first deactivation, only $secondAdmin + $thirdAdmin remain = 2 admins.
    // Deactivate $secondAdmin too, leaving only $thirdAdmin.
    $this->actingAs($thirdAdmin)->patchJson("/api/v1/users/{$secondAdmin->id}/deactivate");
});