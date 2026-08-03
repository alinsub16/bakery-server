<?php

use App\Models\Bread;
use App\Models\Category;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->withRole('admin')->create();
    $this->manager = User::factory()->withRole('manager')->create();
    $this->inventoryClerk = User::factory()->withRole('inventory_clerk')->create();
    $this->category = Category::factory()->create();
});

it('allows any authenticated user to list breads', function () {
    Bread::factory()->count(2)->create(['category_id' => $this->category->id]);

    $response = $this->actingAs($this->inventoryClerk)->getJson('/api/v1/breads');

    $response->assertStatus(200)->assertJsonCount(2, 'data');
});

it('allows admin/manager to create a bread', function () {
    $response = $this->actingAs($this->manager)
        ->postJson('/api/v1/breads', [
            'category_id' => $this->category->id,
            'name' => 'White Sandwich Loaf',
            'sku' => 'BRD-001',
            'unit' => 'pcs',
            'selling_price' => 45.00,
            'cost_price' => 20.00,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'White Sandwich Loaf')
        ->assertJsonPath('data.category.id', $this->category->id);
});

it('rejects a duplicate sku', function () {
    Bread::factory()->create(['category_id' => $this->category->id, 'sku' => 'BRD-001']);

    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/breads', [
            'category_id' => $this->category->id,
            'name' => 'Another Loaf',
            'sku' => 'BRD-001',
            'selling_price' => 30,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('sku');
});

it('rejects a nonexistent category', function () {
    $response = $this->actingAs($this->admin)
        ->postJson('/api/v1/breads', [
            'category_id' => 999999,
            'name' => 'Ghost Bread',
            'sku' => 'BRD-999',
            'selling_price' => 10,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('category_id');
});

it('denies an inventory clerk from creating a bread', function () {
    $response = $this->actingAs($this->inventoryClerk)
        ->postJson('/api/v1/breads', [
            'category_id' => $this->category->id,
            'name' => 'White Loaf',
            'sku' => 'BRD-002',
            'selling_price' => 40,
        ]);

    $response->assertStatus(403);
});

it('deactivates and reactivates a bread', function () {
    $bread = Bread::factory()->create(['category_id' => $this->category->id]);

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/breads/{$bread->id}/deactivate")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', false);

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/breads/{$bread->id}/activate")
        ->assertStatus(200)
        ->assertJsonPath('data.is_active', true);
});

it('filters breads by category and search term', function () {
    $otherCategory = Category::factory()->create();
    Bread::factory()->create(['category_id' => $this->category->id, 'name' => 'Chocolate Croissant']);
    Bread::factory()->create(['category_id' => $otherCategory->id, 'name' => 'Cinnamon Bun']);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/breads?category_id={$this->category->id}&search=croissant");

    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Chocolate Croissant');
});