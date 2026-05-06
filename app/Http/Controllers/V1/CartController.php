<?php

namespace App\Http\Controllers\V1;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartItemResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class CartController extends Controller
{
    /**
     * Sobrescreve o carrinho do usuário com o estado enviado pelo Flutter.
     * Registra eventos de diff (added / removed / quantity_changed).
     * POST /api/v1/cart/sync
     */
    public function sync(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1|max:999',
        ]);

        // Resolve hashids → IDs reais
        $resolvedItems = [];
        $invalidIds = [];

        foreach ($request->items as $item) {
            $decoded = Hashids::decode($item['product_id']);
            $realId = $decoded[0] ?? null;

            if (!$realId) {
                $invalidIds[] = $item['product_id'];
                continue;
            }

            $resolvedItems[$realId] = (int) $item['quantity'];
        }

        if (!empty($invalidIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'details' => ['product_id' => ['Invalid product IDs: ' . implode(', ', $invalidIds)]],
                ]
            ], 422);
        }

        // Carrega produtos com promoções (evita N+1)
        $products = Product::whereIn('id', array_keys($resolvedItems))
            ->with('promotions')
            ->get()
            ->keyBy('id');

        // Valida existência de todos os produtos
        $missingIds = array_diff(array_keys($resolvedItems), $products->keys()->all());
        if (!empty($missingIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'error' => ['code' => 'PRODUCT_NOT_FOUND'],
            ], 422);
        }

        $user = $request->user();

        DB::transaction(function () use ($user, $resolvedItems, $products) {
            CartItem::where('user_id', $user->id)->delete();

            foreach ($resolvedItems as $productId => $quantity) {
                $product = $products[$productId];
                $promo = $product->getActivePromotion();
                $effectivePrice = $product->getEffectivePrice();

                CartItem::create([
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $effectivePrice,
                    'promotion_id' => $promo?->id,
                ]);
            }
        });

        return $this->cartResponse($user);
    }

    /**
     * Retorna o carrinho com totais e alertas de estoque/preço.
     * GET /api/v1/cart
     */
    public function show(Request $request)
    {
        return $this->cartResponse($request->user());
    }

    /**
     * Limpa o carrinho do usuário.
     * DELETE /api/v1/cart
     */
    public function clear(Request $request)
    {
        CartItem::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully',
        ]);
    }

    /**
     * Valida o carrinho atual: detecta mudanças de preço e problemas de estoque
     * desde que os itens foram adicionados.
     * GET /api/v1/cart/validate
     */
    public function validate(Request $request)
    {
        $user = $request->user();

        $items = CartItem::where('user_id', $user->id)
            ->with(['product.promotions', 'promotion'])
            ->get();

        if ($items->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Cart is valid',
                'data' => ['valid' => true, 'changes' => []],
            ]);
        }

        $changes = [];

        foreach ($items as $item) {
            $product = $item->product;

            if (!$product) {
                $changes[] = [
                    'type' => 'product_unavailable',
                    'cart_item_id' => $item->hashid,
                ];
                continue;
            }

            $currentPrice = $product->getEffectivePrice();
            $currentPromo = $product->getActivePromotion();
            $priceAtAdd = (float) $item->unit_price;

            // Mudança de preço
            if (abs($currentPrice - $priceAtAdd) > 0.001) {
                $changes[] = [
                    'type' => 'price_changed',
                    'product_id' => $product->hashid,
                    'product_name' => $product->name,
                    'old_price' => number_format($priceAtAdd, 2, '.', ''),
                    'new_price' => number_format($currentPrice, 2, '.', ''),
                    'promotion_id' => $currentPromo?->hashid,
                    'promotion_name' => $currentPromo?->name,
                ];
            }

            // Promoção que estava aplicada já expirou (preço pode ter voltado)
            if ($item->promotion_id && !$item->promotion?->isCurrentlyActive()) {
                $changes[] = [
                    'type' => 'promotion_expired',
                    'product_id' => $product->hashid,
                    'product_name' => $product->name,
                    'promotion_name' => $item->promotion?->name,
                ];
            }

            // Sem estoque
            if ($product->stock_quantity === 0) {
                $changes[] = [
                    'type' => 'out_of_stock',
                    'product_id' => $product->hashid,
                    'product_name' => $product->name,
                ];
            } elseif ($item->quantity > $product->stock_quantity) {
                $changes[] = [
                    'type' => 'stock_reduced',
                    'product_id' => $product->hashid,
                    'product_name' => $product->name,
                    'requested_quantity' => $item->quantity,
                    'available_quantity' => $product->stock_quantity,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => empty($changes) ? 'Cart is valid' : 'Cart has changes',
            'data' => [
                'valid' => empty($changes),
                'changes' => $changes,
            ],
        ]);
    }

    /**
     * Monta resposta padrão do carrinho.
     */
    private function cartResponse($user)
    {
        $items = CartItem::where('user_id', $user->id)
            ->with(['product.primaryImage', 'product.promotions', 'promotion'])
            ->get();

        $hasStockWarning = $items->some(
            fn($item) => $item->quantity > ($item->product->stock_quantity ?? 0)
        );

        $hasPriceChange = $items->some(
            fn($item) => abs((float) $item->unit_price - $item->product->getEffectivePrice()) > 0.001
        );

        $total = $items->sum(fn($item) => (float) $item->unit_price * $item->quantity);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => CartItemResource::collection($items),
                'total' => number_format($total, 2, '.', ''),
                'items_count' => $items->count(),
                'has_stock_warning' => $hasStockWarning,
                'has_price_change' => $hasPriceChange,
            ],
        ]);
    }
}
