<?php

namespace App\Http\Controllers\V1;

use App\Models\Product;
use App\Models\ProductImage;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of products
     */
    public function index(Request $request)
    {
        $query = Product::with(['categories', 'images', 'primaryImage']);

        // Filtro por categoria
        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        // Filtro por destacados
        if ($request->has('is_highlighted')) {
            $query->where('is_highlighted', $request->boolean('is_highlighted'));
        }

        // Busca por nome
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->paginate($request->get('per_page', 15));

        return ProductResource::collection($products);
    }

    /**
     * Display the specified product
     */
    public function show(Product $product)
    {
        $product->load(['categories', 'images', 'primaryImage']);
        return new ProductResource($product);
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'is_highlighted' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string',
            'images' => 'nullable|array|max:6',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'stock_quantity' => $request->stock_quantity,
            'is_highlighted' => $request->boolean('is_highlighted'),
        ]);

        if ($request->has('category_ids')) {
            // Decodifica Hashids para IDs reais
            $realIds = collect($request->category_ids)->map(function ($hashid) {
                return \Vinkla\Hashids\Facades\Hashids::decode($hashid)[0] ?? null;
            })->filter()->toArray();
            
            $product->categories()->sync($realIds);
        }

        // Upload de imagens
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $extension = $image->getClientOriginalExtension();
                $fileName = sprintf(
                    'product_%d_img_%d_%s.%s',
                    $product->id,
                    $index + 1,
                    substr(md5(uniqid() . time()), 0, 8),
                    $extension
                );
                
                $path = $image->storeAs('products', $fileName, 'public');
                
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'order' => $index,
                    'is_primary' => $index === 0, // Primeira imagem é a principal
                ]);
            }
        }

        return new ProductResource($product->load(['categories', 'images', 'primaryImage']));
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'stock_quantity' => 'sometimes|integer|min:0',
            'is_highlighted' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'string',
            'images' => 'nullable|array|max:6',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'string',
        ]);

        $product->update($request->only([
            'name',
            'description',
            'price',
            'stock_quantity',
            'is_highlighted',
        ]));

        if ($request->has('category_ids')) {
            // Decodifica Hashids para IDs reais
            $realIds = collect($request->category_ids)->map(function ($hashid) {
                return \Vinkla\Hashids\Facades\Hashids::decode($hashid)[0] ?? null;
            })->filter()->toArray();
            
            $product->categories()->sync($realIds);
        }

        // Remover imagens antigas se solicitado
        if ($request->has('remove_images')) {
            // Decodifica Hashids para IDs reais
            $realImageIds = collect($request->remove_images)->map(function ($hashid) {
                return \Vinkla\Hashids\Facades\Hashids::decode($hashid)[0] ?? null;
            })->filter()->toArray();
            
            ProductImage::whereIn('id', $realImageIds)
                ->where('product_id', $product->id)
                ->each(function ($image) {
                    $image->delete(); // Usa o boot do model para deletar o arquivo
                });
        }

        // Upload de novas imagens
        if ($request->hasFile('images')) {
            $currentMaxOrder = $product->images()->max('order') ?? -1;
            $currentCount = $product->images()->count();
            
            // Valida se não excede o limite de 6 imagens
            if ($currentCount + count($request->file('images')) > 6) {
                return response()->json([
                    'message' => 'Maximum limit of 6 images per product exceeded.',
                    'current_count' => $currentCount,
                    'max_allowed' => 6
                ], 422);
            }
            
            foreach ($request->file('images') as $index => $image) {
                $newOrder = $currentMaxOrder + $index + 1;
                $extension = $image->getClientOriginalExtension();
                $fileName = sprintf(
                    'product_%d_img_%d_%s.%s',
                    $product->id,
                    $newOrder + 1,
                    substr(md5(uniqid() . time()), 0, 8),
                    $extension
                );
                
                $path = $image->storeAs('products', $fileName, 'public');
                
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $path,
                    'order' => $newOrder,
                    'is_primary' => $product->images()->count() === 0 && $index === 0,
                ]);
            }
        }

        return new ProductResource($product->load(['categories', 'images', 'primaryImage']));
    }

    /**
     * Remove the specified product
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }
}
