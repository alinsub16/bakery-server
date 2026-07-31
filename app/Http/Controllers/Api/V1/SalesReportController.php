<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesMonthlyRequest;
use App\Http\Requests\SalesRangeRequest;
use App\Http\Requests\SalesYearlyRequest;
use App\Models\DailyInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesReportController extends Controller
{
    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))->toDateString()
            : Carbon::today()->toDateString();

        $rows = DailyInventory::with('bread:id,cost_price')
            ->where('inventory_date', $date)
            ->get();

        return response()->json($this->summarize($rows) + ['date' => $date]);
    }

    public function range(SalesRangeRequest $request): JsonResponse
    {
        $from = Carbon::parse($request->validated('from'))->toDateString();
        $to = Carbon::parse($request->validated('to'))->toDateString();

        $query = DailyInventory::with('bread:id,cost_price')
            ->whereBetween('inventory_date', [$from, $to]);

        if ($request->filled('category_id')) {
            $query->whereHas('bread', fn ($q) => $q->where('category_id', $request->integer('category_id')));
        }

        $rows = $query->get();

        // Group by date for the daily breakdown, then summarize each group.
        $byDate = $rows->groupBy(fn (DailyInventory $row) => $row->inventory_date->toDateString());

        $dailyBreakdown = $byDate->map(function ($dayRows, $date) {
            $summary = $this->summarize($dayRows);

            return [
                'date' => $date,
                'sold_quantity' => $summary['total_sold_quantity'],
                'revenue' => $summary['total_revenue'],
                'profit' => $summary['total_profit'],
            ];
        })->sortBy('date')->values();

        return response()->json([
            'from' => $from,
            'to' => $to,
            ...$this->summarize($rows),
            'daily_breakdown' => $dailyBreakdown,
        ]);
    }

    public function byBread(SalesRangeRequest $request): JsonResponse
    {
        $from = Carbon::parse($request->validated('from'))->toDateString();
        $to = Carbon::parse($request->validated('to'))->toDateString();

        $query = DailyInventory::with('bread:id,name,sku,cost_price,category_id')
            ->whereBetween('inventory_date', [$from, $to]);

        if ($request->filled('category_id')) {
            $query->whereHas('bread', fn ($q) => $q->where('category_id', $request->integer('category_id')));
        }

        $rows = $query->get();

        $byBread = $rows->groupBy('bread_id')->map(function ($breadRows) {
            $bread = $breadRows->first()->bread;
            $summary = $this->summarize($breadRows);

            return [
                'bread' => [
                    'id' => $bread->id,
                    'name' => $bread->name,
                    'sku' => $bread->sku,
                ],
                'total_sold_quantity' => $summary['total_sold_quantity'],
                'total_revenue' => $summary['total_revenue'],
                'total_profit' => $summary['total_profit'],
            ];
        })->sortByDesc('total_revenue')->values();

        return response()->json($byBread);
    }

    public function monthly(SalesMonthlyRequest $request): JsonResponse
    {
        $year = $request->validated('year');
        $month = $request->validated('month');

        $start = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $end = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        $rows = DailyInventory::with('bread:id,cost_price')
            ->whereBetween('inventory_date', [$start, $end])
            ->get();

        $byDate = $rows->groupBy(fn (DailyInventory $row) => $row->inventory_date->toDateString());

        $dailyBreakdown = $byDate->map(function ($dayRows, $date) {
            $summary = $this->summarize($dayRows);

            return [
                'date' => $date,
                'sold_quantity' => $summary['total_sold_quantity'],
                'revenue' => $summary['total_revenue'],
                'profit' => $summary['total_profit'],
            ];
        })->sortBy('date')->values();

        return response()->json([
            'year' => $year,
            'month' => $month,
            ...$this->summarize($rows),
            'daily_breakdown' => $dailyBreakdown,
        ]);
    }

    public function yearly(SalesYearlyRequest $request): JsonResponse
    {
        $year = $request->validated('year');

        $start = Carbon::create($year, 1, 1)->startOfYear()->toDateString();
        $end = Carbon::create($year, 12, 31)->endOfYear()->toDateString();

        $rows = DailyInventory::with('bread:id,cost_price')
            ->whereBetween('inventory_date', [$start, $end])
            ->get();

        $byMonth = $rows->groupBy(fn (DailyInventory $row) => $row->inventory_date->month);

        // Always return all 12 months, even if some have zero activity.
        $monthlyBreakdown = collect(range(1, 12))->map(function (int $month) use ($byMonth) {
            $monthRows = $byMonth->get($month, collect());
            $summary = $this->summarize($monthRows);

            return [
                'month' => $month,
                'sold_quantity' => $summary['total_sold_quantity'],
                'revenue' => $summary['total_revenue'],
                'profit' => $summary['total_profit'],
            ];
        });

        return response()->json([
            'year' => $year,
            ...$this->summarize($rows),
            'monthly_breakdown' => $monthlyBreakdown,
        ]);
    }

    /**
     * Shared aggregation logic. Profit uses each bread's CURRENT cost_price
     * (Decision A) — not a historical snapshot, so this is a best-current-estimate,
     * not a locked historical fact the way revenue is.
     *
     * @param  \Illuminate\Support\Collection<int, DailyInventory>  $rows
     */
    private function summarize($rows): array
    {
        $totalSoldQuantity = $rows->sum('sold_quantity');
        $totalRevenue = (float) $rows->sum('revenue');

        $totalCost = $rows->sum(function (DailyInventory $row) {
            $costPrice = (float) ($row->bread->cost_price ?? 0);

            return $row->sold_quantity * $costPrice;
        });

        return [
            'total_sold_quantity' => $totalSoldQuantity,
            'total_revenue' => round($totalRevenue, 2),
            'total_cost' => round($totalCost, 2),
            'total_profit' => round($totalRevenue - $totalCost, 2),
            'breads_reported' => $rows->pluck('bread_id')->unique()->count(),
        ];
    }
}