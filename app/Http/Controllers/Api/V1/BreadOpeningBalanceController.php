<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOpeningBalanceRequest;
use App\Models\Bread;
use App\Models\BreadOpeningBalance;
use App\Models\DailyInventory;
use App\Models\DailyProduction;
use Illuminate\Http\JsonResponse;

class BreadOpeningBalanceController extends Controller
{
    public function show(Bread $bread): JsonResponse
    {
        $balance = BreadOpeningBalance::with('setBy:id,name')
            ->where('bread_id', $bread->id)
            ->first();

        if (! $balance) {
            return response()->json(['message' => 'No opening balance recorded for this bread.'], 404);
        }

        return response()->json([
            'bread_id' => $balance->bread_id,
            'quantity' => $balance->quantity,
            'note' => $balance->note,
            'set_by' => [
                'id' => $balance->setBy->id,
                'name' => $balance->setBy->name,
            ],
            'created_at' => $balance->created_at,
        ]);
    }

    public function store(StoreOpeningBalanceRequest $request, Bread $bread): JsonResponse
    {
        $hasProduction = DailyProduction::where('bread_id', $bread->id)->exists();
        $hasInventory = DailyInventory::where('bread_id', $bread->id)->exists();

        if ($hasProduction || $hasInventory) {
            return response()->json([
                'message' => 'This bread already has activity history; opening balance can only be set before any production or inventory has been recorded.',
            ], 409);
        }

        $existingBalance = BreadOpeningBalance::where('bread_id', $bread->id)->exists();

        if ($existingBalance) {
            return response()->json([
                'message' => 'An opening balance has already been recorded for this bread.',
            ], 409);
        }

        $balance = BreadOpeningBalance::create([
            'bread_id' => $bread->id,
            'quantity' => $request->validated('quantity'),
            'note' => $request->validated('note'),
            'set_by' => $request->user()->id,
        ]);

        return response()->json([
            'bread_id' => $balance->bread_id,
            'quantity' => $balance->quantity,
            'note' => $balance->note,
            'set_by' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
            ],
            'created_at' => $balance->created_at,
        ], 201);
    }
}