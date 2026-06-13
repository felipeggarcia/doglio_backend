<?php

namespace App\Http\Controllers\V1;

use App\Models\Product;
use App\Models\ProductImage;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockMovementResource;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Support\ApiMessages;

class ProductController extends Controller
{
    /**
     * Display a listing of products
     */
    public function index(Request $request)
    {
        $query = Product::with(['categories', 'images', 'primaryImage', 'promotions'])
            ->where('is_active', true);

        // Filtro por produtos em promoção ativa
        if ($request->boolean('on_promotion')) {
            $query->whereHas('promotions', function ($q) {
                $q->where('is_active', true)
                  ->where('starts_at', '<=', now())
                  ->where(function ($inner) {
                      $inner->whereNull('ends_at')->orWhere('ends_at', '>', now());
                  })
                  ->where(function ($inner) {
                      $inner->whereNull('product_promotion.use_limit')
                            ->orWhereColumn('product_promotion.uses_count', '<', 'product_promotion.use_limit');
                  });
            });
        }

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

        // Filtro de produtos sem estoque (retorna APENAS sem estoque)
        if ($request->has('out_of_stock') && $request->boolean('out_of_stock')) {
            $query->where('stock_quantity', '=', 0);
        }

        // Filtro de produtos com estoque (retorna APENAS com estoque)
        if ($request->has('in_stock') && $request->boolean('in_stock')) {
            $query->where('stock_quantity', '>', 0);
        }

        // Ordenação
        if ($request->has('sort_by')) {
            $sortBy = $request->sort_by;
            $sortOrder = $request->get('sort_order', 'asc');

            $allowedSorts = ['name', 'price', 'stock_quantity', 'created_at', 'updated_at'];

            if (in_array($sortBy, $allowedSorts)) {
                // Sem estoque sempre vai pro final, mesmo com sort_by customizado
                $query->orderByRaw('CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END')
                      ->orderBy($sortBy, $sortOrder);
            }
        } elseif ($request->boolean('on_promotion')) {
            // Em promoção: destacados primeiro, depois alfabético, sem estoque por último
            $query->orderByRaw('CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END')
                  ->orderBy('is_highlighted', 'desc')
                  ->orderBy('name', 'asc');
        } else {
            // Ordenação padrão: destacados primeiro, depois alfabético, sem estoque por último
            $query->orderByRaw('CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END')
                  ->orderBy('is_highlighted', 'desc')
                  ->orderBy('name', 'asc');
        }

        $products = $query->paginate($request->get('per_page', 15));

        return ProductResource::collection($products);
    }

    /**
     * Listagem completa de produtos para admin (inclui inativos).
     */
    public function adminIndex(Request $request)
    {
        $query = Product::with(['categories', 'images', 'primaryImage', 'promotions']);

        // Filtro por ativo/inativo
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Busca genérica (nome ou descrição)
        if ($request->has('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Filtro por destaque
        if ($request->has('is_highlighted')) {
            $query->where('is_highlighted', $request->boolean('is_highlighted'));
        }

        // Filtro por categoria (um ou mais hashids) — produto deve estar em TODAS as categorias informadas
        if ($request->has('category_ids')) {
            $ids = collect((array) $request->category_ids)->map(function ($hashid) {
                if (config('app.use_hashids', true)) {
                    $decoded = \Vinkla\Hashids\Facades\Hashids::decode($hashid);
                    return $decoded[0] ?? null;
                }
                return is_numeric($hashid) ? (int) $hashid : null;
            })->filter()->values()->all();

            foreach ($ids as $catId) {
                $query->whereHas('categories', fn($q) => $q->where('categories.id', $catId));
            }
        }

        // Filtro por estoque zerado
        if ($request->has('out_of_stock')) {
            if ($request->boolean('out_of_stock')) {
                $query->where('stock_quantity', 0);
            } else {
                $query->where('stock_quantity', '>', 0);
            }
        }

        // Filtro por faixa de preço
        if ($request->has('price_min')) {
            $query->where('price', '>=', $request->price_min);
        }
        if ($request->has('price_max')) {
            $query->where('price', '<=', $request->price_max);
        }

        // Filtro por data de criação
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Ordenação
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSorts = ['name', 'price', 'stock_quantity', 'created_at', 'updated_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $products = $query->paginate($request->get('per_page', 15));

        return ProductResource::collection($products);
    }

    /**
     * Display the specified product
     */
    public function show(Product $product)
    {
        $product->load(['categories', 'images', 'primaryImage', 'reviews']);
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
            'stock_quantity' => 0,
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

        return response()->json([
            'success' => true,
            'message' => ApiMessages::PRODUCT_CREATED,
            'data' => new ProductResource($product->load(['categories', 'images', 'primaryImage'])),
        ], 201);
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name'           => 'sometimes|string|max:255',
            'description'    => 'sometimes|string',
            'price'          => 'sometimes|numeric|min:0',
            'is_highlighted' => 'boolean',
            'is_active'      => 'boolean',
            'category_ids'   => 'nullable|array',
            'category_ids.*' => 'string',
            'images'         => 'nullable|array|max:6',
            'images.*'       => 'image|mimes:jpeg,png,jpg,webp|max:2048',
            'remove_images'  => 'nullable|array',
            'remove_images.*'=> 'string',
            'image_order'    => 'nullable|array',
            'image_order.*'  => 'string',
        ]);

        $product->update($request->only([
            'name',
            'description',
            'price',
            'is_highlighted',
            'is_active',
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
                    'message' => ApiMessages::PRODUCT_IMAGE_LIMIT,
                    'error' => [
                        'code' => 'IMAGE_LIMIT_EXCEEDED',
                        'details' => ApiMessages::PRODUCT_IMAGE_LIMIT,
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

        if ($request->has('image_order')) {
            $orderedIds = collect($request->image_order)->map(function ($hashid) {
                if (!config('app.use_hashids', true)) {
                    return is_numeric($hashid) ? (int)$hashid : null;
                }
                $decoded = \Vinkla\Hashids\Facades\Hashids::decode($hashid);
                return $decoded[0] ?? null;
            })->filter()->values();

            $productImageIds = $product->images()->pluck('id');

            foreach ($orderedIds as $position => $imageId) {
                if (!$productImageIds->contains($imageId)) {
                    continue;
                }
                ProductImage::where('id', $imageId)->update([
                    'order'      => $position,
                    'is_primary' => $position === 0,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => ApiMessages::PRODUCT_UPDATED,
            'data' => new ProductResource($product->load(['categories', 'images', 'primaryImage'])),
        ]);
    }

    /**
     * Remove the specified product
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => ApiMessages::PRODUCT_DELETED,
        ]);
    }

    /**
     * Lista as movimentações de estoque de um produto (admin only).
     * GET /api/v1/products/{product}/stock-movements
     */
    public function stockMovements(Request $request, Product $product)
    {
        $request->validate([
            'type'     => 'nullable|in:in,out',
            'reason'   => 'nullable|in:sale,return,purchase,manual_adjustment,loss',
            'from'     => 'nullable|date',
            'to'       => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $product->stockMovements()->with('user');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $movements = $query->paginate($request->get('per_page', 20));

        return StockMovementResource::collection($movements);
    }

}
