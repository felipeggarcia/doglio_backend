<?php

namespace App\Http\Controllers\V1;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\CartItem;
use App\Models\CartSnapshot;
use App\Models\Promotion;
use App\Models\StockMovement;
use App\Models\UserAddress;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Vinkla\Hashids\Facades\Hashids;

class OrderController extends Controller
{
    /**
     * Lista os pedidos do usuário autenticado.
     * GET /api/v1/orders
     */
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['orderItems.product.primaryImage', 'payment.paymentMethod'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return OrderResource::collection($orders);
    }

    /**
     * Detalhe de um pedido do usuário autenticado.
     * GET /api/v1/orders/{order}
     */
    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403);
        }

        $order->load(['orderItems.product.primaryImage', 'payment.paymentMethod']);

        return new OrderResource($order);
    }

    /**
     * Lista TODOS os pedidos (admin).
     * GET /api/v1/admin/orders
     */
    public function adminIndex(Request $request)
    {
        $query = Order::with(['orderItems.product.primaryImage', 'payment.paymentMethod', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $decoded = Hashids::decode($request->user_id);
            if (!empty($decoded)) {
                $query->where('user_id', $decoded[0]);
            }
        }

        if ($request->filled('delivery_type')) {
            $query->where('delivery_type', $request->delivery_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return OrderResource::collection($orders);
    }

    /**
     * Detalhe de qualquer pedido (admin).
     * GET /api/v1/admin/orders/{order}
     */
    public function adminShow(Order $order)
    {
        $order->load(['orderItems.product.primaryImage', 'payment.paymentMethod', 'user', 'statusHistory']);

        return new OrderResource($order);
    }

    /**
     * Converte o carrinho em pedido.
     * POST /api/v1/checkout
     */
    public function checkout(Request $request)
    {
        $request->validate([
            'payment_method_id' => 'required|string',
            'delivery_type' => 'required|in:delivery,pickup',
            'address_id' => 'nullable|string',
            // Endereço manual: só obrigatório quando delivery_type=delivery E address_id não foi informado
            'shipping_street' => [Rule::requiredIf(fn () => $request->delivery_type === 'delivery' && !$request->filled('address_id')), 'nullable', 'string', 'max:255'],
            'shipping_number' => [Rule::requiredIf(fn () => $request->delivery_type === 'delivery' && !$request->filled('address_id')), 'nullable', 'string', 'max:20'],
            'shipping_complement' => 'nullable|string|max:100',
            'shipping_city' => [Rule::requiredIf(fn () => $request->delivery_type === 'delivery' && !$request->filled('address_id')), 'nullable', 'string', 'max:255'],
            'shipping_state' => [Rule::requiredIf(fn () => $request->delivery_type === 'delivery' && !$request->filled('address_id')), 'nullable', 'string', 'size:2'],
            'shipping_zip' => [Rule::requiredIf(fn () => $request->delivery_type === 'delivery' && !$request->filled('address_id')), 'nullable', 'string', 'size:8'],
        ]);

        // Decodifica e valida payment_method_id
        $pmDecoded = Hashids::decode($request->payment_method_id);
        $paymentMethod = PaymentMethod::where('id', $pmDecoded[0] ?? null)
            ->where('is_active', true)
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'details' => ['payment_method_id' => ['Invalid or inactive payment method']],
                ]
            ], 422);
        }

        // Carrega carrinho com promoções aplicadas
        $cartItems = CartItem::where('user_id', $request->user()->id)
            ->with(['product', 'promotion'])
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty',
                'error' => [
                    'code' => 'CART_EMPTY',
                    'details' => 'Add products to your cart before checkout',
                ]
            ], 422);
        }

        // Valida estoque de TODOS os itens antes de criar qualquer coisa
        $stockErrors = [];
        foreach ($cartItems as $item) {
            if ($item->quantity > $item->product->stock_quantity) {
                $stockErrors[] = [
                    'product_id' => $item->product->hashid,
                    'product_name' => $item->product->name,
                    'requested' => $item->quantity,
                    'available' => $item->product->stock_quantity,
                ];
            }
        }

        if (!empty($stockErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock',
                'error' => [
                    'code' => 'INSUFFICIENT_STOCK',
                    'details' => $stockErrors,
                ]
            ], 422);
        }

        // Resolve endereço
        $addressId = null;
        $shippingData = [];

        if ($request->delivery_type === 'delivery') {
            if ($request->filled('address_id')) {
                // Endereço salvo
                $addrDecoded = Hashids::decode($request->address_id);
                $address = UserAddress::where('id', $addrDecoded[0] ?? null)
                    ->where('user_id', $request->user()->id)
                    ->first();

                if (!$address) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'error' => [
                            'code' => 'VALIDATION_ERROR',
                            'details' => ['address_id' => ['Address not found']],
                        ]
                    ], 422);
                }

                // Marca como endereço principal (último usado)
                UserAddress::where('user_id', $request->user()->id)->update(['is_primary' => false]);
                $address->update(['is_primary' => true]);

                $addressId = $address->id;
                $shippingData = [
                    'shipping_street' => $address->street,
                    'shipping_number' => $address->number,
                    'shipping_complement' => $address->complement,
                    'shipping_city' => $address->city,
                    'shipping_state' => $address->state,
                    'shipping_zip' => $address->zip,
                ];
            } else {
                // Endereço manual
                $shippingData = [
                    'shipping_street' => $request->shipping_street,
                    'shipping_number' => $request->shipping_number,
                    'shipping_complement' => $request->shipping_complement,
                    'shipping_city' => $request->shipping_city,
                    'shipping_state' => strtoupper($request->shipping_state),
                    'shipping_zip' => $request->shipping_zip,
                ];
            }
        }

        // Cria pedido e pagamento dentro de uma transação
        $order = DB::transaction(function () use ($request, $cartItems, $paymentMethod, $addressId, $shippingData) {
            // Usa o preço capturado no carrinho (snapshot do momento da adição)
            $total = $cartItems->sum(fn($item) => (float) $item->unit_price * $item->quantity);

            $order = Order::create(array_merge([
                'user_id' => $request->user()->id,
                'address_id' => $addressId,
                'status' => 'pending',
                'total_amount' => $total,
                'delivery_type' => $request->delivery_type,
            ], $shippingData));

            // Cria os itens, decrementa estoque com lock e registra movimentação
            foreach ($cartItems as $item) {
                // Lock exclusivo na linha do produto — previne race condition em checkouts simultâneos
                $product = \App\Models\Product::lockForUpdate()->find($item->product_id);

                if ($item->quantity > $product->stock_quantity) {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        response()->json([
                            'success' => false,
                            'message' => 'Insufficient stock',
                            'error' => [
                                'code' => 'INSUFFICIENT_STOCK',
                                'details' => [[
                                    'product_id'   => $product->hashid,
                                    'product_name' => $product->name,
                                    'requested'    => $item->quantity,
                                    'available'    => $product->stock_quantity,
                                ]],
                            ],
                        ], 422)
                    );
                }

                $stockBefore = (int) $product->stock_quantity;
                $product->decrement('stock_quantity', $item->quantity);

                StockMovement::create([
                    'product_id'     => $product->id,
                    'type'           => 'out',
                    'quantity'       => $item->quantity,
                    'stock_before'   => $stockBefore,
                    'reason'         => 'sale',
                    'reference_type' => 'order',
                    'reference_id'   => $order->id,
                    'user_id'        => null, // sistema automático
                ]);

                $order->orderItems()->create([
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'unit_price' => $item->unit_price,
                ]);

                // Incrementa uso da promoção (se o item teve desconto de promoção)
                if ($item->promotion_id) {
                    Promotion::where('id', $item->promotion_id)->increment('uses_count');
                }
            }

            // Congela o estado completo do carrinho num único snapshot
            $snapshotContent = $cartItems->map(fn($item) => [
                'product_id' => $item->product->hashid,
                'product_db_id' => $item->product_id,
                'name' => $item->product->name,
                'quantity' => $item->quantity,
                'original_price' => (float) $item->product->price,
                'promotion_id' => $item->promotion?->hashid,
                'promotion_name' => $item->promotion?->name,
                'applied_discount' => round((float) $item->product->price - (float) $item->unit_price, 2),
                'final_price' => (float) $item->unit_price,
            ])->values()->all();

            CartSnapshot::create([
                'user_id' => $request->user()->id,
                'content' => $snapshotContent,
                'trigger_type' => 'CHECKOUT',
                'total_value' => $total,
            ]);

            // Cria pagamento pendente
            Payment::create([
                'order_id' => $order->id,
                'payment_method_id' => $paymentMethod->id,
                'status' => 'pending',
                'amount' => $total,
            ]);

            // Limpa o carrinho
            CartItem::where('user_id', $request->user()->id)->delete();

            return $order;
        });

        $order->load(['orderItems.product.primaryImage', 'payment.paymentMethod']);

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }
}
