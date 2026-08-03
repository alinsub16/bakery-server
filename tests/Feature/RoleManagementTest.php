<?php

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->withRole('admin')->create();
    $this->manager = User::factory()->withRole('manager')->create();
    $this->baker = User::factory()->withRole('baker')->create();
    User::factory()->withRole('inventory_clerk')->create();
});

it('allows an admin to list all roles', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/v1/roles');

    $response->assertStatus(200)
        ->assertJsonCount(4); // admin, manager, baker, inventory_clerk seeded by RoleSeeder/factories
});

it('allows an admin to list users with their roles', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/v1/users');

    $response->assertStatus(200);
});

it('denies a non-admin from listing users', function () {
    $response = $this->actingAs($this->baker)->getJson('/api/v1/users');

    $response->assertStatus(403);
});

it('allows an admin to change another users role', function () {
    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/users/{$this->baker->id}/role", [
            'role' => 'manager',
        ]);

    $response->assertStatus(200)->assertJsonPath('role', 'manager');

    expect($this->baker->fresh()->hasRole('manager'))->toBeTrue();
});

it('rejects an invalid role name', function () {
    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/users/{$this->baker->id}/role", [
            'role' => 'supervisor',
        ]);

    $response->assertStatus(422);
});

it('prevents an admin from changing their own role', function () {
    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/users/{$this->admin->id}/role", [
            'role' => 'baker',
        ]);

    $response->assertStatus(403)
        ->assertJsonPath('message', 'You cannot change your own role.');
});


it('denies a non-admin from changing roles', function () {
    $response = $this->actingAs($this->baker)
        ->putJson("/api/v1/users/{$this->manager->id}/role", ['role' => 'baker']);

    $response->assertStatus(403);
});