<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\OrderStatusHistoryResource;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\ApiMessages;

class OrderStatusController extends Controller
{
    /**
     * Atualiza o status de um pedido (admin only).
     * PATCH /api/v1/orders/{order}/status
     */
    public function update(Request $request, Order $order)
    {
        $allowed = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered', 'cancelled'];

        $data = $request->validate([
            'status' => 'required|string|in:' . implode(',', $allowed),
            'notes' => 'nullable|string|max:500',
        ]);

        $order->update(['status' => $data['status']]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status'   => $data['status'],
            'notes'    => $data['notes'] ?? null,
        ]);

        if ($data['status'] === 'cancelled') {
            DB::transaction(function () use ($order, $request) {
                $order->load('orderItems.product');

                foreach ($order->orderItems as $item) {
                    $stockBefore = (int) $item->product->stock_quantity;
                    $item->product->increment('stock_quantity', $item->quantity);

                    StockMovement::create([
                        'product_id'     => $item->product_id,
                        'type'           => 'in',
                        'quantity'       => $item->quantity,
                        'stock_before'   => $stockBefore,
                        'reason'         => 'return',
                        'reference_type' => 'order',
                        'reference_id'   => $order->id,
                        'user_id'        => $request->user()->id, // admin que cancelou
                    ]);
                }
            });
        }

        $order->load(['orderItems.product.primaryImage', 'payment.paymentMethod', 'statusHistory']);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::ORDER_STATUS_UPDATED,
            'data' => new OrderResource($order),
        ]);
    }
}
