<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockMovementController extends Controller
{
    /**
     * Registra uma movimentação de estoque (entrada ou saída por quantidade delta).
     *
     * POST /api/v1/products/{product}/stock
     *
     * Body:
     *   type     : 'in' | 'out'
     *   quantity : integer, mín 1
     *   reason   : 'purchase' | 'return' | 'manual_adjustment' | 'loss'  (opcional, default 'manual_adjustment')
     *   notes    : string (opcional)
     */
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'type'     => 'required|in:in,out',
            'quantity' => 'required|integer|min:1',
            'reason'   => 'nullable|in:purchase,return,manual_adjustment,loss',
            'notes'    => 'nullable|string|max:500',
        ]);

        $movement = DB::transaction(function () use ($data, $product, $request) {
            // Recarrega o produto com lock para evitar race condition
            $product = Product::lockForUpdate()->findOrFail($product->id);

            $stockBefore = (int) $product->stock_quantity;
            $quantity    = (int) $data['quantity'];

            if ($data['type'] === 'out' && $quantity > $stockBefore) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Estoque insuficiente para esta saída',
                    'error'   => [
                        'code'    => 'INSUFFICIENT_STOCK',
                        'details' => [
                            'available' => $stockBefore,
                            'requested' => $quantity,
                        ],
                    ],
                ], 422));
            }

            $newStock = $data['type'] === 'in'
                ? $stockBefore + $quantity
                : $stockBefore - $quantity;

            $product->update(['stock_quantity' => $newStock]);

            return StockMovement::create([
                'product_id'  => $product->id,
                'type'        => $data['type'],
                'quantity'    => $quantity,
                'stock_before'=> $stockBefore,
                'reason'      => $data['reason'] ?? 'manual_adjustment',
                'user_id'     => $request->user()->id,
                'notes'       => $data['notes'] ?? null,
            ]);
        });

        $movement->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Movimentação de estoque registrada com sucesso',
            'data'    => new StockMovementResource($movement),
        ], 201);
    }
}
