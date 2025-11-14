<?php

namespace App\Http\Controllers\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\V1\Admin\CategoryStoreRequest;
use App\Http\Requests\V1\Admin\CategoryUpdateRequest;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories (PUBLIC)
     */
    public function index(Request $request)
    {
        $query = Category::query();

        // Filtro por destacados
        if ($request->has('is_highlighted')) {
            $query->where('is_highlighted', $request->boolean('is_highlighted'));
        }

        // Busca por nome
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Conta produtos se solicitado
        if ($request->boolean('with_count')) {
            $query->withCount('products');
        }

        $categories = $query->get();
        return CategoryResource::collection($categories);
    }

    /**
     * Display the specified category (PUBLIC)
     */
    public function show(Category $category)
    {
        $category->loadCount('products');
        return new CategoryResource($category);
    }

    /**
     * Store a newly created category (ADMIN ONLY)
     */
    public function store(CategoryStoreRequest $request)
    {
        $category = Category::create($request->validated() + [
            'slug' => \Str::slug($request->name)
        ]);
        
        return new CategoryResource($category);
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

        return new CategoryResource($category);
    }

    /**
     * Remove the specified category (ADMIN ONLY)
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ], 200);
    }
}
