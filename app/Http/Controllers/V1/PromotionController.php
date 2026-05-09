<?php

namespace App\Http\Controllers\V1;

use App\Models\Promotion;
use App\Models\Product;
use App\Http\Controllers\Controller;
use App\Http\Resources\PromotionResource;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;
use App\Support\ApiMessages;

class PromotionController extends Controller
{
    /**
     * Lista promoções (admin) — todas, com filtros.
     * GET /api/v1/admin/promotions
     */
    public function adminIndex(Request $request)
    {
        $query = Promotion::with(['products.primaryImage', 'products.promotions']);

        // Filtro por ativo/inativo
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filtro por expiradas (ends_at no passado)
        if ($request->has('expired')) {
            if ($request->boolean('expired')) {
                $query->where('ends_at', '<', now());
            } else {
                $query->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                });
            }
        }

        // Busca por nome
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Filtro por produto(s) (um ou mais hashids — OR entre eles)
        if ($request->filled('product_ids')) {
            $productIds = collect((array) $request->product_ids)
                ->map(fn($h) => Hashids::decode($h)[0] ?? null)
                ->filter()
                ->values()
                ->all();

            if (!empty($productIds)) {
                $query->whereHas('products', fn($q) => $q->whereIn('products.id', $productIds));
            }
        }

        $promotions = $query->orderBy('created_at', 'desc')->paginate(15);

        return PromotionResource::collection($promotions);
    }

    /**
     * Lista promoções ativas (público).
     * GET /api/v1/promotions
     */
    public function index()
    {
        $promotions = Promotion::where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->whereHas('products', function ($q) {
                $q->where('products.is_active', true)
                  ->whereNull('products.deleted_at')
                  ->where(function ($inner) {
                      $inner->whereNull('product_promotion.use_limit')
                            ->orWhereColumn('product_promotion.uses_count', '<', 'product_promotion.use_limit');
                  });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return PromotionResource::collection($promotions);
    }

    /**
     * Detalhe de uma promoção (público) — só ativas e vigentes.
     * GET /api/v1/promotions/{promotion}
     */
    public function show(Promotion $promotion)
    {
        if (!$promotion->isCurrentlyActive()) {
            abort(404);
        }

        return new PromotionResource($promotion->load(['products.primaryImage', 'products.promotions']));
    }

    /**
     * Detalhe de uma promoção (admin) — qualquer estado.
     * GET /api/v1/admin/promotions/{promotion}
     */
    public function adminShow(Promotion $promotion)
    {
        return new PromotionResource($promotion->load(['products.primaryImage', 'products.promotions']));
    }

    /**
     * Cria uma promoção (admin).
     * POST /api/v1/promotions
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
            'min_quantity' => 'nullable|integer|min:1',
            // Produtos a vincular
            'product_ids'             => 'nullable|array',
            'product_ids.*.id'        => 'required_if_accepted:product_ids|string',
            'product_ids.*.use_limit' => 'nullable|integer|min:1',
        ]);

        // Valida desconto percentual
        if ($data['type'] === 'percentage' && $data['discount_value'] > 100) {
            return response()->json([
                'success' => false,
                'message' => ApiMessages::PROMOTION_PERCENTAGE_LIMIT,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'details' => ['discount_value' => [ApiMessages::PROMOTION_PERCENTAGE_LIMIT]],
                ]
            ], 422);
        }

        $promotion = Promotion::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'discount_value' => $data['discount_value'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'min_quantity' => $data['min_quantity'] ?? null,
        ]);

        // Vincula produtos se informados
        if (!empty($data['product_ids'])) {
            $syncData = collect($data['product_ids'])
                ->mapWithKeys(function ($item) {
                    $hash = is_array($item) ? ($item['id'] ?? null) : $item;
                    $useLimit = is_array($item) ? ($item['use_limit'] ?? null) : null;
                    $realId = Hashids::decode($hash)[0] ?? null;
                    return $realId ? [$realId => ['use_limit' => $useLimit]] : [];
                })
                ->filter()
                ->all();

            $promotion->products()->sync($syncData);
        }

        return response()->json([
            'success' => true,
            'message' => ApiMessages::PROMOTION_CREATED,
            'data' => new PromotionResource($promotion->load(['products.primaryImage', 'products.promotions'])),
        ], 201);
    }

    /**
     * Atualiza uma promoção (admin).
     * PUT /api/v1/promotions/{promotion}
     */
    public function update(Request $request, Promotion $promotion)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|in:percentage,fixed',
            'discount_value' => 'sometimes|numeric|min:0.01',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'is_active' => 'boolean',
            'min_quantity' => 'nullable|integer|min:1',
        ]);

        $type = $data['type'] ?? $promotion->type;
        $discount = $data['discount_value'] ?? $promotion->discount_value;

        if ($type === 'percentage' && $discount > 100) {
            return response()->json([
                'success' => false,
                'message' => ApiMessages::PROMOTION_PERCENTAGE_LIMIT,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'details' => ['discount_value' => [ApiMessages::PROMOTION_PERCENTAGE_LIMIT]],
                ]
            ], 422);
        }

        $promotion->update($data);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::PROMOTION_UPDATED,
            'data' => new PromotionResource($promotion->load(['products.primaryImage', 'products.promotions'])),
        ]);
    }

    /**
     * Remove uma promoção (admin, soft delete).
     * DELETE /api/v1/promotions/{promotion}
     */
    public function destroy(Promotion $promotion)
    {
        $promotion->delete();

        return response()->json([
            'success' => true,
            'message' => ApiMessages::PROMOTION_DELETED,
        ]);
    }

    /**
     * Vincula produtos a uma promoção com use_limit individual (admin).
     * POST /api/v1/promotions/{promotion}/products
     * Body: { "products": [ {"id": "hashid", "use_limit": 30}, {"id": "hashid2", "use_limit": null} ] }
     */
    public function attachProducts(Request $request, Promotion $promotion)
    {
        $request->validate([
            'products'             => 'required|array|min:1',
            'products.*.id'        => 'required|string',
            'products.*.use_limit' => 'nullable|integer|min:1',
        ]);

        $syncData = collect($request->products)
            ->mapWithKeys(function ($item) {
                $realId = Hashids::decode($item['id'])[0] ?? null;
                return $realId ? [$realId => ['use_limit' => $item['use_limit'] ?? null]] : [];
            })
            ->filter()
            ->all();

        $promotion->products()->syncWithoutDetaching($syncData);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::PROMOTION_PRODUCTS_ATTACHED,
        ]);
    }

    /**
     * Desvincula produtos de uma promoção (admin).
     * DELETE /api/v1/promotions/{promotion}/products
     */
    public function detachProducts(Request $request, Promotion $promotion)
    {
        $request->validate([
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|string',
        ]);

        $realIds = collect($request->product_ids)
            ->map(fn($hash) => Hashids::decode($hash)[0] ?? null)
            ->filter()
            ->values()
            ->all();

        $promotion->products()->detach($realIds);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::PROMOTION_PRODUCTS_DETACHED,
        ]);
    }
}
