<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDailyProductionRequest;
use App\Http\Requests\UpdateDailyProductionRequest;
use App\Http\Resources\DailyProductionResource;
use App\Models\ActivityLog;
use App\Models\DailyInventory;
use App\Models\DailyProduction;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class DailyProductionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DailyProduction::with(['bread:id,name,sku', 'producedBy:id,name']);

        $date = $request->filled('date')
            ? Carbon::parse($request->string('date'))->toDateString()
            : Carbon::today()->toDateString();

        $query->where('production_date', $date);

        if ($request->filled('bread_id')) {
            $query->where('bread_id', $request->integer('bread_id'));
        }

        return DailyProductionResource::collection(
            $query->orderBy('created_at')->paginate(20)
        );
    }

    public function store(StoreDailyProductionRequest $request): DailyProductionResource|JsonResponse
    {
        try {
            $production = DailyProduction::create([
                'bread_id' => $request->validated('bread_id'),
                'produced_by' => $request->user()->id,
                'production_date' => Carbon::today()->toDateString(),
                'quantity_produced' => $request->validated('quantity_produced'),
            ]);
        } catch (QueryException $e) {
            // 23505 = Postgres unique_violation — the bread_id/production_date
            // unique constraint caught a duplicate submission for today.
            if ($e->getCode() === '23505') {
                return response()->json([
                    'message' => 'Production for this bread has already been recorded today.',
                ], 409);
            }

            throw $e;
        }

        return DailyProductionResource::make(
            $production->load(['bread:id,name,sku', 'producedBy:id,name'])
        );
    }
    public function update(UpdateDailyProductionRequest $request, DailyProduction $production): DailyProductionResource|JsonResponse
    {
        $today = Carbon::today()->toDateString();

        if ($production->production_date->toDateString() !== $today) {
            return response()->json([
                'message' => 'Cannot correct production from a previous day.',
            ], 403);
        }

        $alreadyClosed = DailyInventory::where('bread_id', $production->bread_id)
            ->where('inventory_date', $today)
            ->exists();

        if ($alreadyClosed) {
            return response()->json([
                'message' => 'Cannot correct — closing inventory has already been recorded for this bread today.',
            ], 409);
        }

        $oldQuantity = $production->quantity_produced;
        $newQuantity = $request->validated('quantity_produced');

        $production->update(['quantity_produced' => $newQuantity]);

        ActivityLog::record(
            user: $request->user(),
            action: 'production.corrected',
            subjectType: 'DailyProduction',
            subjectId: $production->id,
            properties: [
                'bread_id' => $production->bread_id,
                'production_date' => $today,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $newQuantity,
            ],
        );

        return DailyProductionResource::make(
            $production->load(['bread:id,name,sku', 'producedBy:id,name'])
        );
    }
}