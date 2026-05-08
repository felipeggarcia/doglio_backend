<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FavoriteResource;
use App\Models\Product;
use App\Models\UserFavorite;
use Illuminate\Http\Request;
use Vinkla\Hashids\Facades\Hashids;
use App\Support\ApiMessages;

class FavoriteController extends Controller
{
    /**
     * Lista os favoritos do usuário autenticado.
     * GET /api/v1/favorites
     */
    public function index(Request $request)
    {
        $favorites = $request->user()
            ->favorites()
            ->with(['product.primaryImage', 'product.promotions'])
            ->latest()
            ->get();

        return FavoriteResource::collection($favorites);
    }

    /**
     * Adiciona um produto aos favoritos.
     * POST /api/v1/favorites
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|string',
            'notify_on_restock' => 'boolean',
        ]);

        $decoded = Hashids::decode($data['product_id']);
        $product = Product::find($decoded[0] ?? null);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => ApiMessages::PRODUCT_NOT_FOUND,
                'error' => ['code' => 'RESOURCE_NOT_FOUND', 'details' => null],
            ], 404);
        }

        $user = $request->user();

        if ($user->favorites()->where('product_id', $product->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => ApiMessages::FAVORITE_ALREADY_EXISTS,
                'error' => ['code' => 'ALREADY_FAVORITED', 'details' => null],
            ], 422);
        }

        $favorite = $user->favorites()->create([
            'product_id' => $product->id,
            'notify_on_restock' => $data['notify_on_restock'] ?? true,
        ]);

        $favorite->load(['product.primaryImage', 'product.promotions']);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::FAVORITE_ADDED,
            'data' => new FavoriteResource($favorite),
        ], 201);
    }

    /**
     * Remove um produto dos favoritos.
     * DELETE /api/v1/favorites/{favorite}
     */
    public function destroy(Request $request, UserFavorite $favorite)
    {
        $this->authorize('delete', $favorite);

        $favorite->delete();

        return response()->json([
            'success' => true,
            'message' => ApiMessages::FAVORITE_REMOVED,
        ]);
    }

    /**
     * Ativa/desativa notificação de estoque para um favorito.
     * PATCH /api/v1/favorites/{favorite}/notify
     */
    public function toggleNotify(Request $request, UserFavorite $favorite)
    {
        $this->authorize('update', $favorite);

        $favorite->update(['notify_on_restock' => !$favorite->notify_on_restock]);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::FAVORITE_NOTIFY_UPDATED,
            'data' => ['notify_on_restock' => $favorite->notify_on_restock],
        ]);
    }
}
