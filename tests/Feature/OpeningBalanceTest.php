<?php

use App\Models\Bread;
use App\Models\DailyInventory;
use App\Models\DailyProduction;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-08-04');
    $this->admin = User::factory()->withRole('admin')->create();
    $this->baker = User::factory()->withRole('baker')->create();
    $this->bread = Bread::factory()->create(['selling_price' => 45.00]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows an admin to set an opening balance for a bread with no history', function () {
    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/breads/{$this->bread->id}/opening-balance", [
            'quantity' => 15,
            'note' => 'Physical count during system migration',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('quantity', 15)
        ->assertJsonPath('bread_id', $this->bread->id);

    $this->assertDatabaseHas('bread_opening_balances', [
        'bread_id' => $this->bread->id,
        'quantity' => 15,
    ]);
});

it('rejects setting an opening balance twice for the same bread', function () {
    $this->actingAs($this->admin)
        ->postJson("/api/v1/breads/{$this->bread->id}/opening-balance", [
            'quantity' => 15,
            'note' => 'Initial migration count',
        ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/breads/{$this->bread->id}/opening-balance", [
            'quantity' => 20,
            'note' => 'Trying again',
        ]);

    $response->assertStatus(409);
});

it('rejects setting an opening balance for a bread that already has production history', function () {
    DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->baker->id,
        'production_date' => '2026-08-04',
        'quantity_produced' => 30,
    ]);

    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/breads/{$this->bread->id}/opening-balance", [
            'quantity' => 10,
            'note' => 'Should not be allowed now',
        ]);

    $response->assertStatus(409);
});

it('denies a baker from setting an opening balance', function () {
    $response = $this->actingAs($this->baker)
        ->postJson("/api/v1/breads/{$this->bread->id}/opening-balance", [
            'quantity' => 10,
            'note' => 'Testing permission',
        ]);

    $response->assertStatus(403);
});

it('rejects a note shorter than the minimum length', function () {
    $response = $this->actingAs($this->admin)
        ->postJson("/api/v1/breads/{$this->bread->id}/opening-balance", [
            'quantity' => 10,
            'note' => 'hi',
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('note');
});

it('factors the opening balance into the first days opening stock calculation', function () {
    $this->actingAs($this->admin)
        ->postJson("/api/v1/breads/{$this->bread->id}/opening-balance", [
            'quantity' => 15,
            'note' => 'Migration day physical count',
        ]);

    DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->baker->id,
        'production_date' => '2026-08-04',
        'quantity_produced' => 20,
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/inventory/opening-stock/{$this->bread->id}");

    // 15 (opening balance) + 20 (today's production) = 35
    $response->assertStatus(200)->assertJsonPath('opening_stock', 35);
});

it('does not re-apply the opening balance on subsequent days', function () {
    $this->actingAs($this->admin)
        ->postJson("/api/v1/breads/{$this->bread->id}/opening-balance", [
            'quantity' => 15,
            'note' => 'Migration day physical count',
        ]);

    DailyInventory::create([
        'bread_id' => $this->bread->id,
        'recorded_by' => $this->admin->id,
        'inventory_date' => '2026-08-04',
        'opening_stock' => 15,
        'closing_stock' => 5,
        'sold_quantity' => 10,
        'revenue' => 450.00,
    ]);

    Carbon::setTestNow('2026-08-05');

    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/inventory/opening-stock/{$this->bread->id}");

    // Should be just yesterday's closing (5), NOT 15 (balance) + 5 again
    $response->assertStatus(200)->assertJsonPath('opening_stock', 5);
});

it('returns 404 when no opening balance has been set', function () {
    $response = $this->actingAs($this->admin)
        ->getJson("/api/v1/breads/{$this->bread->id}/opening-balance");

    $response->assertStatus(404);
});