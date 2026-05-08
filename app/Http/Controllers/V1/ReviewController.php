<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Lista as avaliações de um produto.
     * GET /api/v1/products/{product}/reviews
     */
    public function index(Request $request, Product $product)
    {
        $reviews = $product->reviews()
            ->with('user')
            ->latest()
            ->paginate($request->get('per_page', 15));

        return ReviewResource::collection($reviews);
    }

    /**
     * Cria uma avaliação para um produto.
     * POST /api/v1/products/{product}/reviews
     */
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        // Verifica se o usuário já comprou este produto e o pedido chegou ao status "delivered"
        // (mesmo que o pedido tenha sido devolvido/cancelado depois)
        $hasPurchased = OrderStatusHistory::where('status', 'delivered')
            ->whereIn('order_id', function ($q) use ($user, $product) {
                $q->select('orders.id')
                    ->from('orders')
                    ->join('order_items', 'orders.id', '=', 'order_items.order_id')
                    ->where('orders.user_id', $user->id)
                    ->where('order_items.product_id', $product->id);
            })
            ->exists();

        if (!$hasPurchased) {
            return response()->json([
                'success' => false,
                'message' => 'You can only review a product after receiving it.',
                'error' => ['code' => 'PURCHASE_REQUIRED', 'details' => null],
            ], 403);
        }

        if ($product->reviews()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this product.',
                'error' => ['code' => 'ALREADY_REVIEWED', 'details' => null],
            ], 422);
        }

        $review = $product->reviews()->create([
            'user_id' => $user->id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        $review->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'data' => new ReviewResource($review),
        ], 201);
    }

    /**
     * Remove a avaliação do usuário autenticado.
     * DELETE /api/v1/reviews/{review}
     */
    public function destroy(Request $request, Review $review)
    {
        $this->authorize('delete', $review);

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted.',
            'data' => null,
        ]);
    }
}
