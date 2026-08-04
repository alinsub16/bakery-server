<?php

use App\Models\User;

it('allows public registration and creates a pending account', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'New Baker',
        'email' => 'newbaker@bakery.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('users', [
        'email' => 'newbaker@bakery.test',
        'status' => 'pending',
    ]);
});

it('rejects registration with a duplicate email', function () {
    User::factory()->create(['email' => 'taken@bakery.test']);

    $response = $this->postJson('/api/v1/register', [
        'name' => 'Someone',
        'email' => 'taken@bakery.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

it('blocks login for a pending account with an informative message', function () {
    User::factory()->create([
        'email' => 'pending@bakery.test',
        'password' => bcrypt('password123'),
        'status' => 'pending',
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'pending@bakery.test',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Your account is pending admin approval.');
});

it('blocks login for a rejected account', function () {
    User::factory()->create([
        'email' => 'rejected@bakery.test',
        'password' => bcrypt('password123'),
        'status' => 'rejected',
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'rejected@bakery.test',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'This account registration was not approved.');
});

it('does not issue a token on registration', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'New Baker',
        'email' => 'newbaker2@bakery.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertJsonMissing(['token']);
});