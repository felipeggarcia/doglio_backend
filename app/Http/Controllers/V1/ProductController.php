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
            // Decodifica Hashid para ID real (ou usa diretamente se hashids desabilitado)
            $categoryId = $request->category_id;
            
            if (config('app.use_hashids', true)) {
                $decoded = \Vinkla\Hashids\Facades\Hashids::decode($categoryId);
                $categoryId = $decoded[0] ?? null;
            } else {
                $categoryId = is_numeric($categoryId) ? (int)$categoryId : null;
            }
            
            if ($categoryId) {
                $query->whereHas('categories', function ($q) use ($categoryId) {
                    $q->where('categories.id', $categoryId);
                });
            }
        }

        // Filtro por destacados
        if ($request->has('is_highlighted')) {
            $query->where('is_highlighted', $request->boolean('is_highlighted'));
        }

        // Busca por nome
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // Busca por descrição
        if ($request->has('description')) {
            $query->where('description', 'like', '%' . $request->description . '%');
        }

        // Busca genérica (nome OU descrição)
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Filtro por preço mínimo
        if ($request->has('price_min')) {
            $query->where('price', '>=', $request->price_min);
        }

        // Filtro por preço máximo
        if ($request->has('price_max')) {
            $query->where('price', '<=', $request->price_max);
        }

        // Filtro por faixa de preço
        if ($request->has('price_from') && $request->has('price_to')) {
            $query->whereBetween('price', [$request->price_from, $request->price_to]);
        }

        // Filtro por quantidade em estoque mínima
        if ($request->has('stock_min')) {
            $query->where('stock_quantity', '>=', $request->stock_min);
        }

        // Filtro por quantidade em estoque máxima
        if ($request->has('stock_max')) {
            $query->where('stock_quantity', '<=', $request->stock_max);
        }

        // Filtro de produtos em estoque (maior que 0)
        if ($request->has('in_stock') && $request->boolean('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        // Filtro de produtos sem estoque
        if ($request->has('out_of_stock') && $request->boolean('out_of_stock')) {
            $query->where('stock_quantity', '=', 0);
        }

        // Por padrão, esconde produtos sem estoque (a menos que out_of_stock=true)
        if (!$request->has('out_of_stock') || !$request->boolean('out_of_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        // Ordenação
        if ($request->has('sort_by')) {
            $sortBy = $request->sort_by;
            $sortOrder = $request->get('sort_order', 'asc'); // asc ou desc

            // Valida campos permitidos para ordenação
            $allowedSorts = ['name', 'price', 'stock_quantity', 'created_at', 'updated_at'];
            
            if (in_array($sortBy, $allowedSorts)) {
                $query->orderBy($sortBy, $sortOrder);
            }
        } else {
            // Ordenação padrão: destacados primeiro, depois por estoque (maior para menor)
            $query->orderBy('is_highlighted', 'desc')
                  ->orderBy('stock_quantity', 'desc');
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
            // Decodifica Hashids para IDs reais (ou usa diretamente se hashids desabilitado)
            $realIds = collect($request->category_ids)->map(function ($hashid) {
                if (!config('app.use_hashids', true)) {
                    return is_numeric($hashid) ? (int)$hashid : null;
                }
                $decoded = \Vinkla\Hashids\Facades\Hashids::decode($hashid);
                return $decoded[0] ?? null;
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
            // Decodifica Hashids para IDs reais (ou usa diretamente se hashids desabilitado)
            $realIds = collect($request->category_ids)->map(function ($hashid) {
                if (!config('app.use_hashids', true)) {
                    return is_numeric($hashid) ? (int)$hashid : null;
                }
                $decoded = \Vinkla\Hashids\Facades\Hashids::decode($hashid);
                return $decoded[0] ?? null;
            })->filter()->toArray();
            
            $product->categories()->sync($realIds);
        }

        // Remover imagens antigas se solicitado
        if ($request->has('remove_images')) {
            // Decodifica Hashids para IDs reais (ou usa diretamente se hashids desabilitado)
            $realImageIds = collect($request->remove_images)->map(function ($hashid) {
                if (!config('app.use_hashids', true)) {
                    return is_numeric($hashid) ? (int)$hashid : null;
                }
                $decoded = \Vinkla\Hashids\Facades\Hashids::decode($hashid);
                return $decoded[0] ?? null;
            })->filter()->toArray();
            
            ProductImage::whereIn('id', $realImageIds)
                ->where('product_id', $product->id)
                ->get()
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
                    'success' => false,
                    'message' => 'Image limit exceeded',
                    'error' => [
                        'code' => 'IMAGE_LIMIT_EXCEEDED',
                        'details' => 'Maximum of 6 images per product allowed',
                        'current_count' => $currentCount,
                        'max_allowed' => 6
                    ]
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
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}
