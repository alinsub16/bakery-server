<?php

namespace App\Services;

use App\Models\Bread;
use App\Models\DailyInventory;
use App\Models\DailyProduction;
use Illuminate\Support\Carbon;

class InventoryCalculationService
{
    /**
     * opening_stock = yesterday's closing_stock (if any) + today's production (if any)
     */
    public function calculateOpeningStock(int $breadId, string $date): int
    {
        $yesterday = Carbon::parse($date)->subDay()->toDateString();

        $yesterdayClosing = DailyInventory::where('bread_id', $breadId)
            ->where('inventory_date', $yesterday)
            ->value('closing_stock') ?? 0;

        $todayProduction = DailyProduction::where('bread_id', $breadId)
            ->where('production_date', $date)
            ->value('quantity_produced') ?? 0;

        return $yesterdayClosing + $todayProduction;
    }

    /**
     * sold_quantity = opening_stock - closing_stock
     * revenue        = sold_quantity * bread.selling_price (at time of closing)
     *
     * Returns null if closing_stock > opening_stock — caller decides how to
     * surface that as a validation error rather than this service throwing.
     */
    public function computeClosing(Bread $bread, int $openingStock, int $closingStock): ?array
    {
        if ($closingStock > $openingStock) {
            return null;
        }

        $soldQuantity = $openingStock - $closingStock;
        $revenue = round($soldQuantity * (float) $bread->selling_price, 2);

        return [
            'sold_quantity' => $soldQuantity,
            'revenue' => $revenue,
        ];
    }
}