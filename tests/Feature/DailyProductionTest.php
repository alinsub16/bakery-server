<?php

use App\Models\ActivityLog;
use App\Models\Bread;
use App\Models\DailyInventory;
use App\Models\DailyProduction;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-08-01');
    $this->bread = Bread::factory()->create();
    $this->baker = User::factory()->withRole('baker')->create();
    $this->manager = User::factory()->withRole('manager')->create();
    $this->admin = User::factory()->withRole('admin')->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('allows a baker to submit production for today', function () {
    $response = $this->actingAs($this->baker)
        ->postJson('/api/v1/production', [
            'bread_id' => $this->bread->id,
            'quantity_produced' => 50,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.quantity_produced', 50)
        ->assertJsonPath('data.bread.id', $this->bread->id);

    $this->assertDatabaseHas('daily_productions', [
        'bread_id' => $this->bread->id,
        'quantity_produced' => 50,
        'production_date' => '2026-08-01',
        'produced_by' => $this->baker->id,
    ]);
});

it('rejects a second production submission for the same bread on the same day', function () {
    DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->baker->id,
        'production_date' => '2026-08-01',
        'quantity_produced' => 50,
    ]);

    $response = $this->actingAs($this->baker)
        ->postJson('/api/v1/production', [
            'bread_id' => $this->bread->id,
            'quantity_produced' => 20,
        ]);

    $response->assertStatus(409);
});

it('rejects production for an inactive bread', function () {
    $inactiveBread = Bread::factory()->inactive()->create();

    $response = $this->actingAs($this->baker)
        ->postJson('/api/v1/production', [
            'bread_id' => $inactiveBread->id,
            'quantity_produced' => 10,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('bread_id');
});

it('rejects zero or negative quantity', function () {
    $response = $this->actingAs($this->baker)
        ->postJson('/api/v1/production', [
            'bread_id' => $this->bread->id,
            'quantity_produced' => 0,
        ]);

    $response->assertStatus(422)->assertJsonValidationErrors('quantity_produced');
});

it('allows a manager to correct todays production and logs the change', function () {
    $production = DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->baker->id,
        'production_date' => '2026-08-01',
        'quantity_produced' => 500,
    ]);

    $response = $this->actingAs($this->manager)
        ->putJson("/api/v1/production/{$production->id}", [
            'quantity_produced' => 50,
        ]);

    $response->assertStatus(200)->assertJsonPath('data.quantity_produced', 50);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'production.corrected',
        'subject_id' => $production->id,
    ]);
});

it('prevents a baker from correcting production', function () {
    $production = DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->baker->id,
        'production_date' => '2026-08-01',
        'quantity_produced' => 50,
    ]);

    $response = $this->actingAs($this->baker)
        ->putJson("/api/v1/production/{$production->id}", [
            'quantity_produced' => 60,
        ]);

    $response->assertStatus(403);
});

it('prevents correcting production from a previous day', function () {
    $production = DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->baker->id,
        'production_date' => '2026-07-31',
        'quantity_produced' => 50,
    ]);

    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/production/{$production->id}", [
            'quantity_produced' => 60,
        ]);

    $response->assertStatus(403);
});

it('prevents correcting production once closing inventory exists for that day', function () {
    $production = DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->baker->id,
        'production_date' => '2026-08-01',
        'quantity_produced' => 50,
    ]);

    DailyInventory::create([
        'bread_id' => $this->bread->id,
        'recorded_by' => $this->admin->id,
        'inventory_date' => '2026-08-01',
        'opening_stock' => 50,
        'closing_stock' => 10,
        'sold_quantity' => 40,
        'revenue' => 1800.00,
    ]);

    $response = $this->actingAs($this->admin)
        ->putJson("/api/v1/production/{$production->id}", [
            'quantity_produced' => 60,
        ]);

    $response->assertStatus(409);
});

it('rejects an unauthenticated request', function () {
    $response = $this->postJson('/api/v1/production', [
        'bread_id' => $this->bread->id,
        'quantity_produced' => 10,
    ]);

    $response->assertStatus(401);
});