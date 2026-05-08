<?php

namespace App\Http\Controllers\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Requests\V1\Admin\CategoryStoreRequest;
use App\Http\Requests\V1\Admin\CategoryUpdateRequest;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories (PUBLIC)
     */
    public function index(Request $request)
    {
        $version = Cache::get('categories.version', 1);
        $cacheKey = "categories.index.v{$version}." . md5(http_build_query($request->query()));

        $categories = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($request) {
            $query = Category::query();

            if ($request->has('is_highlighted')) {
                $query->where('is_highlighted', $request->boolean('is_highlighted'));
            }

            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            if ($request->boolean('with_count')) {
                $query->withCount('products');
            }

            return $query->get();
        });

        return CategoryResource::collection($categories);
    }

    /**
     * Display the specified category with paginated products (PUBLIC)
     */
    public function show(Request $request, Category $category)
    {
        $category->loadCount('products');

        $products = $category->products()
            ->with(['images', 'primaryImage'])
            ->where('stock_quantity', '>', 0)
            ->orderBy('is_highlighted', 'desc')
            ->orderBy('name', 'asc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => new CategoryResource($category),
            'products' => ProductResource::collection($products),
        ]);
    }

    /**
     * Store a newly created category (ADMIN ONLY)
     */
    public function store(CategoryStoreRequest $request)
    {
        $category = Category::create($request->validated() + [
            'slug' => \Str::slug($request->name)
        ]);

        Cache::increment('categories.version');

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified category (ADMIN ONLY)
     */
    public function update(CategoryUpdateRequest $request, Category $category)
    {
        $data = $request->validated();
        
        // Atualiza o slug se o nome foi alterado
        if (isset($data['name'])) {
            $data['slug'] = \Str::slug($data['name']);
        }

        $category->update($data);

        Cache::increment('categories.version');

        return new CategoryResource($category);
    }

    /**
     * Remove the specified category (ADMIN ONLY)
     */
    public function destroy(Category $category)
    {
        $category->delete();

        Cache::increment('categories.version');

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }
}
