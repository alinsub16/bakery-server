<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesRangeRequest;
use App\Models\Bread;
use App\Models\DailyInventory;
use App\Models\DailyProduction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ProductionVarianceController extends Controller
{
    public function index(SalesRangeRequest $request): JsonResponse
    {
        $from = Carbon::parse($request->validated('from'))->toDateString();
        $to = Carbon::parse($request->validated('to'))->toDateString();

        $productionQuery = DailyProduction::whereBetween('production_date', [$from, $to]);
        $inventoryQuery = DailyInventory::whereBetween('inventory_date', [$from, $to]);

        if ($request->filled('category_id')) {
            $categoryId = $request->integer('category_id');
            $productionQuery->whereHas('bread', fn ($q) => $q->where('category_id', $categoryId));
            $inventoryQuery->whereHas('bread', fn ($q) => $q->where('category_id', $categoryId));
        }

        $productionRows = $productionQuery->get(['bread_id', 'production_date', 'quantity_produced']);
        $inventoryRows = $inventoryQuery->get(['bread_id', 'inventory_date', 'closing_stock', 'sold_quantity']);

        $breadIdsWithProduction = $productionRows->pluck('bread_id')->unique();

        if ($breadIdsWithProduction->isEmpty()) {
            return response()->json(['from' => $from, 'to' => $to, 'breads' => []]);
        }

        $breads = Bread::whereIn('id', $breadIdsWithProduction)->get(['id', 'name', 'sku'])->keyBy('id');

        $productionByBread = $productionRows->groupBy('bread_id');
        $inventoryByBread = $inventoryRows->groupBy('bread_id');

        $result = $breadIdsWithProduction->map(function (int $breadId) use ($breads, $productionByBread, $inventoryByBread) {
            $breadProduction = $productionByBread->get($breadId, collect());
            $breadInventory = $inventoryByBread->get($breadId, collect());

            $totalProduced = $breadProduction->sum('quantity_produced');
            $totalSold = $breadInventory->sum('sold_quantity');
            $variance = $totalProduced - $totalSold;
            $variancePercent = $totalProduced > 0
                ? round(($variance / $totalProduced) * 100, 2)
                : 0.0;

            $daysWithProduction = $breadProduction->count();
            $daysWithClosing = $breadInventory->pluck('inventory_date')->unique()->count();
            $daysWithPendingClosing = max($daysWithProduction - $daysWithClosing, 0);

            $avgDailyClosingStock = $breadInventory->count() > 0
                ? round($breadInventory->avg('closing_stock'), 2)
                : 0.0;

            return [
                'bread' => [
                    'id' => $breads[$breadId]->id,
                    'name' => $breads[$breadId]->name,
                    'sku' => $breads[$breadId]->sku,
                ],
                'total_produced' => $totalProduced,
                'total_sold' => $totalSold,
                'variance' => $variance,
                'variance_percent' => $variancePercent,
                'avg_daily_closing_stock' => $avgDailyClosingStock,
                'days_with_production' => $daysWithProduction,
                'days_with_pending_closing' => $daysWithPendingClosing,
            ];
        })->sortByDesc('variance_percent')->values();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'breads' => $result,
        ]);
    }
}