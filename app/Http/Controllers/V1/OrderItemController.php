<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\ApiMessages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class OrderItemController extends Controller
{
    /**
     * POST /admin/orders/{order}/items
     * Adiciona um produto ao pedido.
     */
    public function addItem(Request $request, Order $order)
    {
        $request->validate([
            'product_id' => 'required|string',
            'quantity'   => 'required|integer|min:1',
        ]);

        $decoded = Hashids::decode($request->product_id);
        $product = Product::find($decoded[0] ?? null);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => ApiMessages::VALIDATION_FAILED,
                'error'   => ['code' => 'PRODUCT_NOT_FOUND', 'details' => 'Produto não encontrado.'],
            ], 422);
        }

        $result = DB::transaction(function () use ($request, $order, $product) {
            $product = Product::lockForUpdate()->find($product->id);

            if ($request->quantity > $product->stock_quantity) {
                return [
                    'success' => false,
                    'message' => ApiMessages::ORDER_INSUFFICIENT_STOCK,
                    'error'   => [
                        'code'      => 'INSUFFICIENT_STOCK',
                        'details'   => [[
                            'product_id'   => $product->hashid,
                            'product_name' => $product->name,
                            'requested'    => $request->quantity,
                            'available'    => $product->stock_quantity,
                        ]],
                    ],
                ];
            }

            $stockBefore = (int) $product->stock_quantity;
            $product->decrement('stock_quantity', $request->quantity);

            StockMovement::create([
                'product_id'     => $product->id,
                'type'           => 'out',
                'quantity'       => $request->quantity,
                'stock_before'   => $stockBefore,
                'reason'         => 'sale',
                'reference_type' => 'order',
                'reference_id'   => $order->id,
                'user_id'        => null,
            ]);

            $order->orderItems()->create([
                'product_id' => $product->id,
                'quantity'   => $request->quantity,
                'unit_price' => $product->price,
            ]);

            $order->update([
                'total_amount' => $order->orderItems()->sum(DB::raw('quantity * unit_price')),
            ]);

            return null;
        });

        if ($result !== null) {
            return response()->json($result, 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item adicionado ao pedido.',
            'data'    => new OrderResource($order->load(['orderItems.product.primaryImage', 'payment.paymentMethod', 'user'])),
        ]);
    }

    /**
     * PUT /admin/orders/{order}/items/{item}
     * Atualiza a quantidade de um item.
     */
    public function updateItem(Request $request, Order $order, OrderItem $item)
    {
        if ($item->order_id !== $order->id) {
            abort(404);
        }

        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $diff = $request->quantity - $item->quantity;

        if ($diff === 0) {
            return response()->json([
                'success' => true,
                'message' => 'Nenhuma alteração.',
                'data'    => new OrderResource($order->load(['orderItems.product.primaryImage', 'payment.paymentMethod', 'user'])),
            ]);
        }

        $result = DB::transaction(function () use ($request, $order, $item, $diff) {
            $product = Product::lockForUpdate()->find($item->product_id);

            if ($diff > 0 && $diff > $product->stock_quantity) {
                return [
                    'success' => false,
                    'message' => ApiMessages::ORDER_INSUFFICIENT_STOCK,
                    'error'   => [
                        'code'    => 'INSUFFICIENT_STOCK',
                        'details' => [[
                            'product_id'   => $product->hashid,
                            'product_name' => $product->name,
                            'requested'    => $diff,
                            'available'    => $product->stock_quantity,
                        ]],
                    ],
                ];
            }

            $stockBefore = (int) $product->stock_quantity;

            if ($diff > 0) {
                $product->decrement('stock_quantity', $diff);
                StockMovement::create([
                    'product_id'     => $product->id,
                    'type'           => 'out',
                    'quantity'       => $diff,
                    'stock_before'   => $stockBefore,
                    'reason'         => 'sale',
                    'reference_type' => 'order',
                    'reference_id'   => $order->id,
                    'user_id'        => null,
                ]);
            } else {
                $product->increment('stock_quantity', abs($diff));
                StockMovement::create([
                    'product_id'     => $product->id,
                    'type'           => 'in',
                    'quantity'       => abs($diff),
                    'stock_before'   => $stockBefore,
                    'reason'         => 'return',
                    'reference_type' => 'order',
                    'reference_id'   => $order->id,
                    'user_id'        => null,
                ]);
            }

            $item->update(['quantity' => $request->quantity]);

            $order->update([
                'total_amount' => $order->orderItems()->sum(DB::raw('quantity * unit_price')),
            ]);

            return null;
        });

        if ($result !== null) {
            return response()->json($result, 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item atualizado.',
            'data'    => new OrderResource($order->load(['orderItems.product.primaryImage', 'payment.paymentMethod', 'user'])),
        ]);
    }

    /**
     * DELETE /admin/orders/{order}/items/{item}
     * Remove um item do pedido e devolve o estoque.
     */
    public function removeItem(Order $order, OrderItem $item)
    {
        if ($item->order_id !== $order->id) {
            abort(404);
        }

        DB::transaction(function () use ($order, $item) {
            $product = Product::lockForUpdate()->find($item->product_id);

            $stockBefore = (int) $product->stock_quantity;
            $product->increment('stock_quantity', $item->quantity);

            StockMovement::create([
                'product_id'     => $product->id,
                'type'           => 'in',
                'quantity'       => $item->quantity,
                'stock_before'   => $stockBefore,
                'reason'         => 'return',
                'reference_type' => 'order',
                'reference_id'   => $order->id,
                'user_id'        => null,
            ]);

            $item->delete();

            $order->update([
                'total_amount' => $order->orderItems()->sum(DB::raw('quantity * unit_price')),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Item removido do pedido.',
            'data'    => new OrderResource($order->load(['orderItems.product.primaryImage', 'payment.paymentMethod', 'user'])),
        ]);
    }
}
