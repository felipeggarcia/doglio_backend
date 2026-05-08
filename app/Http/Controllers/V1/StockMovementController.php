<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\ApiMessages;

class StockMovementController extends Controller
{
    /**
     * Lista o histórico de movimentações de estoque de um produto.
     * GET /api/v1/products/{product}/stock
     */
    public function index(Request $request, Product $product)
    {
        $movements = StockMovement::where('product_id', $product->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return StockMovementResource::collection($movements);
    }

    /**
     * Registra uma movimentação de estoque.
     *
     * POST /api/v1/products/{product}/stock
     *
     * Modo delta (type + quantity):
     *   type     : 'in' | 'out'
     *   quantity : integer, mín 1
     *   reason   : 'purchase' | 'return' | 'manual_adjustment' | 'loss'  (opcional, default 'manual_adjustment')
     *   notes    : string (opcional)
     *
     * Modo absoluto (absolute):
     *   absolute : integer, mín 0  — define o stock final exato
     *   reason   : idem (opcional, default 'manual_adjustment')
     *   notes    : string (opcional)
     */
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'absolute' => 'nullable|integer|min:0',
            'type'     => 'required_without:absolute|in:in,out',
            'quantity' => 'required_without:absolute|integer|min:1',
            'reason'   => 'nullable|in:purchase,return,manual_adjustment,loss',
            'notes'    => 'nullable|string|max:500',
        ]);

        $movement = DB::transaction(function () use ($data, $product, $request) {
            $product = Product::lockForUpdate()->findOrFail($product->id);

            $stockBefore = (int) $product->stock_quantity;

            // Modo absoluto: derivar type e quantity da diferença
            if (isset($data['absolute'])) {
                $newStock = (int) $data['absolute'];
                $diff     = $newStock - $stockBefore;

                if ($diff === 0) {
                    return null; // nenhuma alteração necessária
                }

                $type     = $diff > 0 ? 'in' : 'out';
                $quantity = abs($diff);
            } else {
                $type     = $data['type'];
                $quantity = (int) $data['quantity'];
                $newStock = $type === 'in'
                    ? $stockBefore + $quantity
                    : $stockBefore - $quantity;

                if ($type === 'out' && $quantity > $stockBefore) {
                    abort(response()->json([
                        'success' => false,
                        'message' => ApiMessages::PRODUCT_INSUFFICIENT_STOCK,
                        'error'   => [
                            'code'    => 'INSUFFICIENT_STOCK',
                            'details' => [
                                'available' => $stockBefore,
                                'requested' => $quantity,
                            ],
                        ],
                    ], 422));
                }
            }

            $product->update(['stock_quantity' => $newStock]);

            return StockMovement::create([
                'product_id'   => $product->id,
                'type'         => $type,
                'quantity'     => $quantity,
                'stock_before' => $stockBefore,
                'reason'       => $data['reason'] ?? 'manual_adjustment',
                'user_id'      => $request->user()->id,
                'notes'        => $data['notes'] ?? null,
            ]);
        });

        if ($movement === null) {
            return response()->json([
                'success' => true,
                'message' => ApiMessages::STOCK_NO_CHANGE,
            ]);
        }

        $movement->load('user');

        return response()->json([
            'success' => true,
            'message' => ApiMessages::STOCK_MOVEMENT_CREATED,
            'data'    => new StockMovementResource($movement),
        ], 201);
    }
}
