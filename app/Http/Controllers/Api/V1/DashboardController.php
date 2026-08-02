<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bread;
use App\Models\DailyInventory;
use App\Models\DailyProduction;
use App\Services\InventoryCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        private readonly InventoryCalculationService $calculator,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();
        $threshold = $request->filled('low_stock_threshold')
            ? $request->integer('low_stock_threshold')
            : 10;

        return response()->json([
            'date' => $today,
            'today' => $this->todayTotals($today),
            'pending' => [
                'needs_production' => $this->needsProduction($today),
                'needs_closing' => $this->needsClosing($today),
            ],
            'low_stock' => $this->lowStock($today, $threshold),
            'week_trend' => $this->weekTrend($today),
        ]);
    }

    /**
     * Today's running totals, reusing the same aggregation shape as
     * SalesReportController::summarize() but scoped inline here since
     * the dashboard only ever needs "today", not arbitrary ranges.
     */
    private function todayTotals(string $today): array
    {
        $rows = DailyInventory::with('bread:id,cost_price')
            ->where('inventory_date', $today)
            ->get();

        $totalRevenue = (float) $rows->sum('revenue');
        $totalCost = $rows->sum(fn (DailyInventory $r) => $r->sold_quantity * (float) ($r->bread->cost_price ?? 0));

        return [
            'total_sold_quantity' => $rows->sum('sold_quantity'),
            'total_revenue' => round($totalRevenue, 2),
            'total_profit' => round($totalRevenue - $totalCost, 2),
            'breads_reported' => $rows->pluck('bread_id')->unique()->count(),
        ];
    }

    /**
     * Active breads with no production entry logged today.
     */
    private function needsProduction(string $today): array
    {
        $producedBreadIds = DailyProduction::where('production_date', $today)->pluck('bread_id');

        return Bread::where('is_active', true)
            ->whereNotIn('id', $producedBreadIds)
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->toArray();
    }

    /**
     * Breads produced today but not yet closed out — the actionable
     * "still pending" gap, not all breads with any leftover stock.
     */
    private function needsClosing(string $today): array
    {
        $closedBreadIds = DailyInventory::where('inventory_date', $today)->pluck('bread_id');

        return DailyProduction::with('bread:id,name,sku')
            ->where('production_date', $today)
            ->whereNotIn('bread_id', $closedBreadIds)
            ->get()
            ->map(fn (DailyProduction $p) => [
                'id' => $p->bread->id,
                'name' => $p->bread->name,
                'sku' => $p->bread->sku,
                'quantity_produced' => $p->quantity_produced,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Active breads whose current (pre-closing) opening stock today
     * falls at or below the threshold.
     */
    private function lowStock(string $today, int $threshold): array
    {
        $activeBreads = Bread::where('is_active', true)->get(['id', 'name', 'sku']);

        $flagged = [];

        foreach ($activeBreads as $bread) {
            $opening = $this->calculator->calculateOpeningStock($bread->id, $today);

            if ($opening <= $threshold) {
                $flagged[] = [
                    'id' => $bread->id,
                    'name' => $bread->name,
                    'sku' => $bread->sku,
                    'opening_stock' => $opening,
                ];
            }
        }

        return $flagged;
    }

    /**
     * This week (last 7 days including today) vs the prior 7-day window.
     */
    private function weekTrend(string $today): array
    {
        $todayDate = Carbon::parse($today);

        $thisWeekStart = $todayDate->copy()->subDays(6)->toDateString();
        $lastWeekStart = $todayDate->copy()->subDays(13)->toDateString();
        $lastWeekEnd = $todayDate->copy()->subDays(7)->toDateString();

        $thisWeekRevenue = (float) DailyInventory::whereBetween('inventory_date', [$thisWeekStart, $today])->sum('revenue');
        $lastWeekRevenue = (float) DailyInventory::whereBetween('inventory_date', [$lastWeekStart, $lastWeekEnd])->sum('revenue');

        $changePercent = $lastWeekRevenue > 0
            ? round((($thisWeekRevenue - $lastWeekRevenue) / $lastWeekRevenue) * 100, 2)
            : null; // avoid divide-by-zero; null means "no baseline to compare against"

        return [
            'this_week_revenue' => round($thisWeekRevenue, 2),
            'last_week_revenue' => round($lastWeekRevenue, 2),
            'change_percent' => $changePercent,
        ];
    }
}