<?php

use App\Models\Bread;
use App\Models\Category;
use App\Models\DailyInventory;
use App\Models\DailyProduction;
use App\Models\User;
use App\Services\InventoryCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new InventoryCalculationService();
    $this->bread = Bread::factory()->create(['selling_price' => 45.00]);
    $this->user = User::factory()->create();
    Carbon::setTestNow('2026-08-01');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns 0 opening stock when no history exists', function () {
    $opening = $this->service->calculateOpeningStock($this->bread->id, '2026-08-01');

    expect($opening)->toBe(0);
});

it('calculates opening stock from todays production only when no prior closing exists', function () {
    DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->user->id,
        'production_date' => '2026-08-01',
        'quantity_produced' => 50,
    ]);

    $opening = $this->service->calculateOpeningStock($this->bread->id, '2026-08-01');

    expect($opening)->toBe(50);
});

it('calculates opening stock as yesterdays closing plus todays production', function () {
    DailyInventory::create([
        'bread_id' => $this->bread->id,
        'recorded_by' => $this->user->id,
        'inventory_date' => '2026-07-31',
        'opening_stock' => 40,
        'closing_stock' => 15,
        'sold_quantity' => 25,
        'revenue' => 1125.00,
    ]);

    DailyProduction::create([
        'bread_id' => $this->bread->id,
        'produced_by' => $this->user->id,
        'production_date' => '2026-08-01',
        'quantity_produced' => 30,
    ]);

    $opening = $this->service->calculateOpeningStock($this->bread->id, '2026-08-01');

    expect($opening)->toBe(45); // 15 leftover + 30 produced
});

it('uses only yesterdays closing when nothing was produced today', function () {
    DailyInventory::create([
        'bread_id' => $this->bread->id,
        'recorded_by' => $this->user->id,
        'inventory_date' => '2026-07-31',
        'opening_stock' => 40,
        'closing_stock' => 12,
        'sold_quantity' => 28,
        'revenue' => 1260.00,
    ]);

    $opening = $this->service->calculateOpeningStock($this->bread->id, '2026-08-01');

    expect($opening)->toBe(12);
});

it('computes sold quantity and revenue correctly', function () {
    $result = $this->service->computeClosing($this->bread, openingStock: 50, closingStock: 10);

    expect($result)->not->toBeNull()
        ->and($result['sold_quantity'])->toBe(40)
        ->and($result['revenue'])->toBe(1800.0); // 40 * 45.00
});

it('returns null when closing stock exceeds opening stock', function () {
    $result = $this->service->computeClosing($this->bread, openingStock: 20, closingStock: 25);

    expect($result)->toBeNull();
});

it('handles zero sold quantity when closing equals opening', function () {
    $result = $this->service->computeClosing($this->bread, openingStock: 30, closingStock: 30);

    expect($result['sold_quantity'])->toBe(0)
        ->and($result['revenue'])->toBe(0.0);
});