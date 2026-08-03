<?php

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->withRole('baker')->create([
        'email' => 'baker@bakery.test',
        'password' => bcrypt('password123'),
    ]);
});

it('logs in with valid credentials and returns a token plus role', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'baker@bakery.test',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']])
        ->assertJsonPath('user.role', 'baker');
});

it('rejects an incorrect password with a generic message', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'baker@bakery.test',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Invalid credentials.');
});

it('rejects a login attempt for a nonexistent email with the same generic message', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'nobody@bakery.test',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Invalid credentials.');
});

it('blocks login for a deactivated (soft-deleted) account', function () {
    $this->user->delete(); // soft delete

    $response = $this->postJson('/api/v1/login', [
        'email' => 'baker@bakery.test',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'This account has been deactivated.');
});

it('returns the authenticated user on /me', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/me');

    $response->assertStatus(200)
        ->assertJsonPath('email', 'baker@bakery.test')
        ->assertJsonPath('role', 'baker');
});

it('rejects /me without authentication', function () {
    $response = $this->getJson('/api/v1/me');

    $response->assertStatus(401);
});

it('revokes the current token on logout', function () {
    $tokenResult = $this->user->createToken('test-token');
    $plainTextToken = $tokenResult->plainTextToken;
    $tokenId = $tokenResult->accessToken->id;

    $this->withHeader('Authorization', "Bearer {$plainTextToken}")
        ->postJson('/api/v1/logout')
        ->assertStatus(200);

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
});