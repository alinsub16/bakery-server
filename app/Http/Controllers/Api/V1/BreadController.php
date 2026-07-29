<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBreadRequest;
use App\Http\Requests\UpdateBreadRequest;
use App\Http\Resources\BreadResource;
use App\Models\Bread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BreadController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Bread::with('category:id,name');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', $term)
                    ->orWhere('sku', 'ilike', $term);
            });
        }

        return BreadResource::collection(
            $query->orderBy('name')->paginate(20)
        );
    }

    public function store(StoreBreadRequest $request): BreadResource
    {
        $bread = Bread::create($request->validated());

        return BreadResource::make($bread->load('category:id,name'));
    }

    public function show(Bread $bread): BreadResource
    {
        return BreadResource::make($bread->load('category:id,name'));
    }

    public function update(UpdateBreadRequest $request, Bread $bread): BreadResource
    {
        $bread->update($request->validated());

        return BreadResource::make($bread->load('category:id,name'));
    }

    public function deactivate(Bread $bread): BreadResource
    {
        $bread->update(['is_active' => false]);

        return BreadResource::make($bread->load('category:id,name'));
    }

    public function activate(Bread $bread): BreadResource
    {
        $bread->update(['is_active' => true]);

        return BreadResource::make($bread->load('category:id,name'));
    }
}