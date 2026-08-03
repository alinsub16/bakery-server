<?php

use App\Models\Bread;
use App\Models\DailyInventory;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-08-15');
    $this->user = User::factory()->withRole('manager')->create();
    $this->bread = Bread::factory()->create(['selling_price' => 50.00, 'cost_price' => 20.00]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns todays sales summary with computed profit', function () {
    DailyInventory::create([
        'bread_id' => $this->bread->id,
        'recorded_by' => $this->user->id,
        'inventory_date' => '2026-08-15',
        'opening_stock' => 50,
        'closing_stock' => 10,
        'sold_quantity' => 40,
        'revenue' => 2000.00,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/sales/daily-summary');

    $response->assertStatus(200)
        ->assertJsonPath('total_sold_quantity', 40)
        ->assertJsonPath('breads_reported', 1);

    // cost = 40 * 20.00 = 800, profit = 2000 - 800 = 1200
    expect((float) $response->json('total_revenue'))->toEqual(2000.0);
    expect((float) $response->json('total_profit'))->toEqual(1200.0);
});

it('rejects a date range over 90 days', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/sales/range?from=2026-01-01&to=2026-12-31');

    $response->assertStatus(422)->assertJsonValidationErrors('to');
});

it('returns a range report with per-day breakdown', function () {
    DailyInventory::create([
        'bread_id' => $this->bread->id, 'recorded_by' => $this->user->id,
        'inventory_date' => '2026-08-10', 'opening_stock' => 30, 'closing_stock' => 5,
        'sold_quantity' => 25, 'revenue' => 1250.00,
    ]);
    DailyInventory::create([
        'bread_id' => $this->bread->id, 'recorded_by' => $this->user->id,
        'inventory_date' => '2026-08-11', 'opening_stock' => 30, 'closing_stock' => 10,
        'sold_quantity' => 20, 'revenue' => 1000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/sales/range?from=2026-08-01&to=2026-08-15');

    $response->assertStatus(200)->assertJsonCount(2, 'daily_breakdown');
    expect((float) $response->json('total_revenue'))->toEqual(2250.0);
});

it('returns by-bread totals sorted by revenue descending', function () {
    $breadB = Bread::factory()->create(['selling_price' => 100.00]);

    DailyInventory::create([
        'bread_id' => $this->bread->id, 'recorded_by' => $this->user->id,
        'inventory_date' => '2026-08-14', 'opening_stock' => 20, 'closing_stock' => 10,
        'sold_quantity' => 10, 'revenue' => 500.00,
    ]);
    DailyInventory::create([
        'bread_id' => $breadB->id, 'recorded_by' => $this->user->id,
        'inventory_date' => '2026-08-14', 'opening_stock' => 20, 'closing_stock' => 10,
        'sold_quantity' => 10, 'revenue' => 1000.00,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/sales/by-bread?from=2026-08-01&to=2026-08-15');

    $response->assertStatus(200)
        ->assertJsonPath('0.bread.id', $breadB->id); // higher revenue first
});

it('returns a monthly report scoped to the given month only', function () {
    DailyInventory::create([
        'bread_id' => $this->bread->id, 'recorded_by' => $this->user->id,
        'inventory_date' => '2026-08-05', 'opening_stock' => 20, 'closing_stock' => 5,
        'sold_quantity' => 15, 'revenue' => 750.00,
    ]);
    DailyInventory::create([
        'bread_id' => $this->bread->id, 'recorded_by' => $this->user->id,
        'inventory_date' => '2026-07-05', 'opening_stock' => 20, 'closing_stock' => 5,
        'sold_quantity' => 15, 'revenue' => 750.00,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/sales/monthly?year=2026&month=8');

    $response->assertStatus(200)->assertJsonCount(1, 'daily_breakdown');
    expect((float) $response->json('total_revenue'))->toEqual(750.0);
});

it('rejects an invalid month', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/sales/monthly?year=2026&month=13');

    $response->assertStatus(422);
});

it('returns a yearly report with all 12 months present even when some are empty', function () {
    DailyInventory::create([
        'bread_id' => $this->bread->id, 'recorded_by' => $this->user->id,
        'inventory_date' => '2026-03-10', 'opening_stock' => 20, 'closing_stock' => 5,
        'sold_quantity' => 15, 'revenue' => 750.00,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/sales/yearly?year=2026');

    $response->assertStatus(200)->assertJsonCount(12, 'monthly_breakdown');
    expect($response->json('monthly_breakdown.2.sold_quantity'))->toBe(15); // index 2 = March
    expect($response->json('monthly_breakdown.0.sold_quantity'))->toBe(0); // January, empty
});