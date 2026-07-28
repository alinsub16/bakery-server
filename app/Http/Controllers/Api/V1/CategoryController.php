<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Category::query();

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%'.$request->string('search').'%');
        }

        return CategoryResource::collection(
            $query->orderBy('name')->paginate(20)
        );
    }

    public function store(StoreCategoryRequest $request): CategoryResource
    {
        $category = Category::create([
            'name' => $request->validated('name'),
            'slug' => Category::generateUniqueSlug($request->validated('name')),
            'description' => $request->validated('description'),
        ]);

        return CategoryResource::make($category);
    }

    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update([
            'name' => $request->validated('name'),
            'slug' => Category::generateUniqueSlug($request->validated('name'), $category->id),
            'description' => $request->validated('description'),
        ]);

        return CategoryResource::make($category);
    }

    public function deactivate(Category $category): CategoryResource
    {
        $category->update(['is_active' => false]);

        return CategoryResource::make($category);
    }

    public function activate(Category $category): CategoryResource
    {
        $category->update(['is_active' => true]);

        return CategoryResource::make($category);
    }
}