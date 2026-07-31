<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDailyInventoryRequest;
use App\Http\Requests\UpdateDailyInventoryRequest;
use App\Http\Resources\DailyInventoryResource;
use App\Models\ActivityLog;
use App\Models\Bread;
use App\Models\DailyInventory;
use App\Services\InventoryCalculationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class DailyInventoryController extends Controller
{
    public function __construct(
        private readonly InventoryCalculationService $calculator,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DailyInventory::with(['bread:id,name,sku', 'recordedBy:id,name']);

        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))->toDateString()
            : Carbon::today()->toDateString();

        $query->where('inventory_date', $date);

        if ($request->filled('bread_id')) {
            $query->where('bread_id', $request->integer('bread_id'));
        }

        return DailyInventoryResource::collection(
            $query->orderBy('created_at')->paginate(20)
        );
    }

    public function openingStock(Bread $bread): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        $opening = $this->calculator->calculateOpeningStock($bread->id, $today);

        return response()->json([
            'bread_id' => $bread->id,
            'opening_stock' => $opening,
            'production_date' => $today,
        ]);
    }

    public function store(StoreDailyInventoryRequest $request): DailyInventoryResource|JsonResponse
    {
        $bread = Bread::findOrFail($request->validated('bread_id'));
        $today = Carbon::today()->toDateString();

        $openingStock = $this->calculator->calculateOpeningStock($bread->id, $today);
        $closingStock = $request->validated('closing_stock');

        $result = $this->calculator->computeClosing($bread, $openingStock, $closingStock);

        if ($result === null) {
            return response()->json([
                'message' => "Closing stock ({$closingStock}) cannot exceed opening stock ({$openingStock}).",
            ], 422);
        }

        try {
            $inventory = DailyInventory::create([
                'bread_id' => $bread->id,
                'recorded_by' => $request->user()->id,
                'inventory_date' => $today,
                'opening_stock' => $openingStock,
                'closing_stock' => $closingStock,
                'sold_quantity' => $result['sold_quantity'],
                'revenue' => $result['revenue'],
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                return response()->json([
                    'message' => 'Closing inventory for this bread has already been recorded today.',
                ], 409);
            }

            throw $e;
        }

        return DailyInventoryResource::make(
            $inventory->load(['bread:id,name,sku', 'recordedBy:id,name'])
        );
    }

    public function update(UpdateDailyInventoryRequest $request, DailyInventory $inventory): DailyInventoryResource|JsonResponse
    {
        $today = Carbon::today()->toDateString();

        if ($inventory->inventory_date->toDateString() !== $today) {
            return response()->json([
                'message' => 'Cannot correct closing inventory from a previous day.',
            ], 403);
        }

        $closingStock = $request->validated('closing_stock');
        $result = $this->calculator->computeClosing($inventory->bread, $inventory->opening_stock, $closingStock);

        if ($result === null) {
            return response()->json([
                'message' => "Closing stock ({$closingStock}) cannot exceed opening stock ({$inventory->opening_stock}).",
            ], 422);
        }

        $oldClosing = $inventory->closing_stock;
        $oldSold = $inventory->sold_quantity;
        $oldRevenue = $inventory->revenue;

        $inventory->update([
            'closing_stock' => $closingStock,
            'sold_quantity' => $result['sold_quantity'],
            'revenue' => $result['revenue'],
        ]);

        ActivityLog::record(
            user: $request->user(),
            action: 'inventory.corrected',
            subjectType: 'DailyInventory',
            subjectId: $inventory->id,
            properties: [
                'bread_id' => $inventory->bread_id,
                'inventory_date' => $today,
                'old_closing_stock' => $oldClosing,
                'new_closing_stock' => $closingStock,
                'old_sold_quantity' => $oldSold,
                'new_sold_quantity' => $result['sold_quantity'],
                'old_revenue' => (float) $oldRevenue,
                'new_revenue' => $result['revenue'],
            ],
        );

        return DailyInventoryResource::make(
            $inventory->load(['bread:id,name,sku', 'recordedBy:id,name'])
        );
    }
}