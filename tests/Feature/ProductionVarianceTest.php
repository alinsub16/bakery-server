<?php

use App\Models\Bread;
use App\Models\DailyInventory;
use App\Models\DailyProduction;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-08-15');
    $this->user = User::factory()->withRole('manager')->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('calculates variance between total produced and total sold for a bread', function () {
    $bread = Bread::factory()->create();

    DailyProduction::create([
        'bread_id' => $bread->id, 'produced_by' => $this->user->id,
        'production_date' => '2026-08-10', 'quantity_produced' => 100,
    ]);
    DailyInventory::create([
        'bread_id' => $bread->id, 'recorded_by' => $this->user->id,
        'inventory_date' => '2026-08-10', 'opening_stock' => 100,
        'closing_stock' => 35, 'sold_quantity' => 65, 'revenue' => 3250.00,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/reports/production-variance?from=2026-08-01&to=2026-08-15');

    $response->assertStatus(200);
    $breadReport = collect($response->json('breads'))->firstWhere('bread.id', $bread->id);

    expect($breadReport['total_produced'])->toBe(100)
        ->and($breadReport['total_sold'])->toBe(65)
        ->and($breadReport['variance'])->toBe(35)
        ->and($breadReport['variance_percent'])->toEqual(35.0);
});

it('flags days with production but no closing entry as pending', function () {
    $bread = Bread::factory()->create();

    DailyProduction::create([
        'bread_id' => $bread->id, 'produced_by' => $this->user->id,
        'production_date' => '2026-08-10', 'quantity_produced' => 50,
    ]);
    // No corresponding DailyInventory entry — still pending closing.

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/reports/production-variance?from=2026-08-01&to=2026-08-15');

    $breadReport = collect($response->json('breads'))->firstWhere('bread.id', $bread->id);

    expect($breadReport['days_with_pending_closing'])->toBe(1)
        ->and($breadReport['total_sold'])->toBe(0);
});

it('excludes breads with no production in the range entirely', function () {
    $bread = Bread::factory()->create();
    // No production entries created at all.

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/reports/production-variance?from=2026-08-01&to=2026-08-15');

    $response->assertStatus(200)->assertJsonCount(0, 'breads');
});

it('rejects a range over 90 days', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/reports/production-variance?from=2026-01-01&to=2026-12-31');

    $response->assertStatus(422);
});