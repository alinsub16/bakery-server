<?php

use App\Models\Category;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->withRole('admin')->create();
    $this->manager = User::factory()->withRole('manager')->create();
    $this->baker = User::factory()->withRole('baker')->create();
});

it('allows any authenticated user to list categories', function () {
    Category::factory()->count(3)->create();

    $response = $this->actingAs($this->baker)->getJson('/api/v1/categories');

    $response->assertStatus(200)->assertJsonCount(3, 'data');
});

it('allows admin/manager to create a category', function () {
    $response = $this->actingAs($this->manager)
        ->postJson('/api/v1/categories', [
            'name' => 'Pastries',
            'description' => 'Flaky baked goods',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Pastries')
        ->assertJsonPath('data.slug', 'pastries')
        ->assertJsonPath('data.is_active', true);
});

it('rejects a duplicate category name', function () {
    Category::factory()->create(['name' => 'Pastries']);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/categories', ['name' => 'Pastries']);

    $response->assertStatus(422)->assertJsonValidationErrors('name');
});

it('denies a baker from creating a category', function () {
    $response = $this->actingAs($this->baker)
        ->postJson('/api/v1/categories', ['name' => 'Pastries']);

    $response->assertStatus(403);
});

it('regenerates the slug when the name is updated', function () {
    $category = Category::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/categories/{$category->id}", [
            'name' => 'New Name',
            'description' => 'Updated',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.slug', 'new-name');
});

it('deactivates and reactivates a category', function () {
    $category = Category::factory()->create();

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/categories/{$category->id}/deactivate")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/categories/{$category->id}/activate")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', true);
});

it('filters categories by is_active', function () {
    Category::factory()->count(2)->create(['is_active' => true]);
    Category::factory()->inactive()->create();

    $response = $this->actingAs($this->baker)
        ->getJson('/api/v1/categories?is_active=true');

    $response->assertStatus(200)->assertJsonCount(2, 'data');
});