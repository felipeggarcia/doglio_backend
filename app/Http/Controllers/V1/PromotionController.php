<?php

namespace App\Http\Controllers\V1;

use App\Models\Promotion;
use App\Http\Controllers\Controller;
use App\Http\Resources\PromotionResource;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;
use App\Support\ApiMessages;

class PromotionController extends Controller
{
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
            ->where(function ($q) {
                $q->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return PromotionResource::collection($promotions);
    }

    /**
     * Detalhe de uma promoção (público).
     * GET /api/v1/promotions/{promotion}
     */
    public function show(Promotion $promotion)
    {
        return new PromotionResource($promotion);
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
            'max_uses' => 'nullable|integer|min:1',
            // Produtos a vincular
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'string',
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
            'max_uses' => $data['max_uses'] ?? null,
        ]);

        // Vincula produtos se informados
        if (!empty($data['product_ids'])) {
            $realIds = collect($data['product_ids'])
                ->map(fn($hash) => Hashids::decode($hash)[0] ?? null)
                ->filter()
                ->values()
                ->all();

            $promotion->products()->sync($realIds);
        }

        return response()->json([
            'success' => true,
            'message' => ApiMessages::PROMOTION_CREATED,
            'data' => new PromotionResource($promotion),
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
            'max_uses' => 'nullable|integer|min:1',
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
            'data' => new PromotionResource($promotion->fresh()),
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
     * Vincula produtos a uma promoção (admin).
     * POST /api/v1/promotions/{promotion}/products
     */
    public function attachProducts(Request $request, Promotion $promotion)
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

        $promotion->products()->syncWithoutDetaching($realIds);

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
