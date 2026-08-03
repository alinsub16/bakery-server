<?php

use App\Models\Bread;
use App\Models\DailyInventory;
use App\Models\DailyProduction;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-08-15');
    $this->user = User::factory()->withRole('baker')->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('lists active breads with no production today as needing production', function () {
    $bread = Bread::factory()->create(['is_active' => true]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard/summary');

    $response->assertStatus(200);
    $ids = collect($response->json('pending.needs_production'))->pluck('id');
    expect($ids)->toContain($bread->id);
});

it('excludes inactive breads from needs_production', function () {
    $bread = Bread::factory()->inactive()->create();

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard/summary');

    $ids = collect($response->json('pending.needs_production'))->pluck('id');
    expect($ids)->not->toContain($bread->id);
});

it('lists breads produced today but not yet closed as needing closing', function () {
    $bread = Bread::factory()->create();

    DailyProduction::create([
        'bread_id' => $bread->id,
        'produced_by' => $this->user->id,
        'production_date' => '2026-08-15',
        'quantity_produced' => 30,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard/summary');

    $ids = collect($response->json('pending.needs_closing'))->pluck('id');
    expect($ids)->toContain($bread->id);
});

it('removes a bread from needs_closing once it has been closed', function () {
    $bread = Bread::factory()->create();

    DailyProduction::create([
        'bread_id' => $bread->id, 'produced_by' => $this->user->id,
        'production_date' => '2026-08-15', 'quantity_produced' => 30,
    ]);
    DailyInventory::create([
        'bread_id' => $bread->id, 'recorded_by' => $this->user->id,
        'inventory_date' => '2026-08-15', 'opening_stock' => 30,
        'closing_stock' => 5, 'sold_quantity' => 25, 'revenue' => 1000.00,
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard/summary');

    $ids = collect($response->json('pending.needs_closing'))->pluck('id');
    expect($ids)->not->toContain($bread->id);
});

it('flags a bread as low stock when opening stock is at or below the threshold', function () {
    $bread = Bread::factory()->create();

    DailyProduction::create([
        'bread_id' => $bread->id, 'produced_by' => $this->user->id,
        'production_date' => '2026-08-15', 'quantity_produced' => 5,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson('/api/v1/dashboard/summary?low_stock_threshold=10');

    $ids = collect($response->json('low_stock'))->pluck('id');
    expect($ids)->toContain($bread->id);
});

it('returns null change_percent when there is no prior week revenue to compare against', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard/summary');

    expect($response->json('week_trend.change_percent'))->toBeNull();
});