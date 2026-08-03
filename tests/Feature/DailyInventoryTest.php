<?php

use App\Models\Bread;
use App\Models\DailyInventory;
use App\Models\DailyProduction;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-08-01');
    $this->bread = Bread::factory()->create(['selling_price' => 45.00]);
    $this->clerk = User::factory()->withRole('inventory_clerk')->create();
    $this->baker = User::factory()->withRole('baker')->create();
    $this->manager = User::factory()->withRole('manager')->create();
    $this->admin = User::factory()->withRole('admin')->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('reports zero opening stock when there is no history', function () {
    $response = $this->actingAs($this->clerk)
        ->getJson("/api/v1/inventory/opening-stock/{$this->bread->id}");

    $response->assertStatus(200)->assertJsonPath('opening_stock', 0);
});

it('allows an inventory clerk to close out todays stock and auto-calculates sold quantity and revenue', function () {
    DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->baker->id,
        'production_date' => '2026-08-01',
        'quantity_produced' => 50,
    ]);

    $response = $this->actingAs($this->clerk)
        ->postJson('/api/v1/inventory', [
            'bread_id' => $this->bread->id,
            'closing_stock' => 10,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.opening_stock', 50)
        ->assertJsonPath('data.closing_stock', 10)
        ->assertJsonPath('data.sold_quantity', 40);

    expect((float) $response->json('data.revenue'))->toEqual(1800.0);
});

it('rejects closing stock greater than opening stock', function () {
    DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->baker->id,
        'production_date' => '2026-08-01',
        'quantity_produced' => 20,
    ]);

    $response = $this->actingAs($this->clerk)
        ->postJson('/api/v1/inventory', [
            'bread_id' => $this->bread->id,
            'closing_stock' => 999,
        ]);

    $response->assertStatus(422);
});

it('rejects a second closing submission for the same bread on the same day', function () {

    DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->clerk->id,
        'production_date' => '2026-08-01',
        'quantity_produced' => 50,
    ]);

    DailyInventory::create([
        'bread_id' => $this->bread->id,
        'recorded_by' => $this->clerk->id,
        'inventory_date' => '2026-08-01',
        'opening_stock' => 50,
        'closing_stock' => 10,
        'sold_quantity' => 40,
        'revenue' => 1800.00,
    ]);

    $response = $this->actingAs($this->clerk)
        ->postJson('/api/v1/inventory', [
            'bread_id' => $this->bread->id,
            'closing_stock' => 5,
        ]);

    $response->assertStatus(409);
});

it('prevents a baker from submitting closing stock', function () {
    $response = $this->actingAs($this->baker)
        ->postJson('/api/v1/inventory', [
            'bread_id' => $this->bread->id,
            'closing_stock' => 10,
        ]);

    $response->assertStatus(403);
});

it('carries todays closing stock into tomorrows opening stock', function () {
    DailyInventory::create([
        'bread_id' => $this->bread->id,
        'recorded_by' => $this->clerk->id,
        'inventory_date' => '2026-08-01',
        'opening_stock' => 50,
        'closing_stock' => 12,
        'sold_quantity' => 38,
        'revenue' => 1710.00,
    ]);

    Carbon::setTestNow('2026-08-02');

    $response = $this->actingAs($this->clerk)
        ->getJson("/api/v1/inventory/opening-stock/{$this->bread->id}");

    $response->assertStatus(200)->assertJsonPath('opening_stock', 12);
});

it('allows a manager to correct todays closing stock and recalculates sold quantity and revenue', function () {
    $inventory = DailyInventory::create([
        'bread_id' => $this->bread->id,
        'recorded_by' => $this->clerk->id,
        'inventory_date' => '2026-08-01',
        'opening_stock' => 50,
        'closing_stock' => 10,
        'sold_quantity' => 40,
        'revenue' => 1800.00,
    ]);

    $response = $this->actingAs($this->manager)
        ->putJson("/api/v1/inventory/{$inventory->id}", [
            'closing_stock' => 5,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.closing_stock', 5)
        ->assertJsonPath('data.sold_quantity', 45);

    expect((float) $response->json('data.revenue'))->toEqual(2025.0);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'inventory.corrected',
        'subject_id' => $inventory->id,
    ]);
});

it('prevents correcting closing stock from a previous day', function () {
    $inventory = DailyInventory::create([
        'bread_id' => $this->bread->id,
        'recorded_by' => $this->clerk->id,
        'inventory_date' => '2026-07-31',
        'opening_stock' => 50,
        'closing_stock' => 10,
        'sold_quantity' => 40,
        'revenue' => 1800.00,
    ]);

    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/inventory/{$inventory->id}", [
            'closing_stock' => 5,
        ]);

    $response->assertStatus(403);
});