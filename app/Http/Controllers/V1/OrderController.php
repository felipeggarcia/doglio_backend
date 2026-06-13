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
use App\Support\ApiMessages;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Output\QRGdImagePNG;
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
            $userIds = $this->decodeHashidList($request->input('user_id'));
            if (!empty($userIds)) {
                $query->whereIn('user_id', $userIds);
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

        if ($request->filled('payment_method_id')) {
            $methodIds = $this->decodeHashidList($request->input('payment_method_id'));
            if (!empty($methodIds)) {
                $query->whereHas('payment', fn ($q) => $q->whereIn('payment_method_id', $methodIds));
            }
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
            'shipping_district' => [Rule::requiredIf(fn () => $request->delivery_type === 'delivery' && !$request->filled('address_id')), 'nullable', 'string', 'max:255'],
            'shipping_city' => [Rule::requiredIf(fn () => $request->delivery_type === 'delivery' && !$request->filled('address_id')), 'nullable', 'string', 'max:255'],
            'shipping_state' => [Rule::requiredIf(fn () => $request->delivery_type === 'delivery' && !$request->filled('address_id')), 'nullable', 'string', 'size:2'],
            'shipping_zip_code' => [Rule::requiredIf(fn () => $request->delivery_type === 'delivery' && !$request->filled('address_id')), 'nullable', 'string', 'size:8'],
            // Cartão de crédito (opcionais — usados quando disponíveis, senão gerados automaticamente)
            'card_last_four' => 'nullable|string|size:4',
            'card_brand'     => 'nullable|string|max:30',
            'installments'   => 'nullable|integer|min:1|max:12',
        ]);

        // Decodifica e valida payment_method_id
        $pmDecoded = Hashids::decode($request->payment_method_id);
        $paymentMethod = PaymentMethod::where('id', $pmDecoded[0] ?? null)
            ->where('is_active', true)
            ->first();

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => ApiMessages::VALIDATION_FAILED,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'details' => ['payment_method_id' => [ApiMessages::ORDER_INVALID_PAYMENT]],
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
                'message' => ApiMessages::CART_EMPTY,
                'error' => [
                    'code' => 'CART_EMPTY',
                    'details' => ApiMessages::CART_EMPTY,
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
                'message' => ApiMessages::ORDER_INSUFFICIENT_STOCK,
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
                    'shipping_street'   => $address->street,
                    'shipping_number'   => $address->number,
                    'shipping_complement' => $address->complement,
                    'shipping_district' => $address->district,
                    'shipping_city'     => $address->city,
                    'shipping_state'    => $address->state,
                    'shipping_zip_code' => $address->zip_code,
                ];
            } else {
                // Endereço manual
                $shippingData = [
                    'shipping_street'   => $request->shipping_street,
                    'shipping_number'   => $request->shipping_number,
                    'shipping_complement' => $request->shipping_complement,
                    'shipping_district' => $request->shipping_district,
                    'shipping_city'     => $request->shipping_city,
                    'shipping_state'    => strtoupper($request->shipping_state),
                    'shipping_zip_code' => $request->shipping_zip_code,
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
                            'message' => ApiMessages::ORDER_INSUFFICIENT_STOCK,
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

                // Incrementa uso da promoção por produto (limite é por produto, não global)
                if ($item->promotion_id) {
                    DB::table('product_promotion')
                        ->where('promotion_id', $item->promotion_id)
                        ->where('product_id', $item->product_id)
                        ->increment('uses_count');
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

        $type = strtolower($paymentMethod->type);

        if ($type === 'pix') {
            $pixCode = $this->generateFakePixCode((float) $order->total_amount, $order->id);
            $pixQrCode = $this->generatePixQrCode($pixCode);
            $order->payment->update([
                'pix_code'       => $pixCode,
                'pix_qr_code'    => $pixQrCode,
                'pix_expires_at' => now()->addHours(24),
            ]);
        } elseif ($type === 'boleto') {
            $order->payment->update([
                'boleto_code'       => $this->generateFakeBoletoCode((float) $order->total_amount, $order->id),
                'boleto_expires_at' => now()->addDays(3),
            ]);
        } elseif ($type === 'credit_card') {
            $brands = ['Visa', 'Mastercard', 'Elo', 'Hipercard', 'American Express'];
            $order->payment->update([
                'card_last_four' => $request->input('card_last_four', str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT)),
                'card_brand'     => $request->input('card_brand', $brands[array_rand($brands)]),
                'installments'   => $request->input('installments', 1),
            ]);
        }

        $order->load(['orderItems.product.primaryImage', 'payment.paymentMethod']);

        return response()->json([
            'success' => true,
            'message' => ApiMessages::ORDER_CREATED,
            'data' => new OrderResource($order),
        ], 201);
    }

    private function generateFakeBoletoCode(float $amount, int $orderId): string
    {
        // Formato linha digitável: BBBBB.NNNNN BBBBB.NNNNNN BBBBB.NNNNNN K FFFFFFFFVVVVVVVVVV
        $bank    = '237'; // Bradesco
        $value   = str_pad((int) round($amount * 100), 10, '0', STR_PAD_LEFT);
        // Factor de vencimento: dias desde 07/10/1997
        $factor  = str_pad((int) floor((now()->addDays(3)->timestamp - mktime(0, 0, 0, 10, 7, 1997)) / 86400), 4, '0', STR_PAD_LEFT);
        $field1  = $bank . '9' . str_pad($orderId, 5, '0', STR_PAD_LEFT) . '.' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        $field2  = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT) . '.' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $field3  = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT) . '.' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);

        return "{$field1} {$field2} {$field3} 1 {$factor}{$value}";
    }

    private function generateFakePixCode(float $amount, int $orderId): string
    {
        $txId = str_pad($orderId, 16, '0', STR_PAD_LEFT);
        $amountStr = number_format($amount, 2, '.', '');
        $amountLen = str_pad(strlen($amountStr), 2, '0', STR_PAD_LEFT);
        $merchantName = 'DOGLIO STORE';
        $merchantCity = 'SAO PAULO';

        return "00020101021226720014BR.GOV.BCB.PIX01365f84a4b2-{$orderId}-4c8e-a1d3-9e2f{$txId}5204000053039865{$amountLen}{$amountStr}5802BR" .
               str_pad(strlen($merchantName), 2, '0', STR_PAD_LEFT) . $merchantName .
               str_pad(strlen($merchantCity), 2, '0', STR_PAD_LEFT) . $merchantCity .
               "62160512DOGLIO{$txId}6304ABCD";
    }

    private function generatePixQrCode(string $pixCode): string
    {
        $options = new QROptions;
        $options->outputInterface = QRGdImagePNG::class;
        $options->outputBase64 = true;
        $options->scale = 10;
        $options->quietzoneSize = 2;

        $dataUri = (new QRCode($options))->render($pixCode);

        // Strip the "data:image/png;base64," prefix for Flutter Image.memory()
        return substr($dataUri, strpos($dataUri, ',') + 1);
    }

    /**
     * Decodifica uma lista de hashids separados por vírgula ou array.
     * Retorna array de IDs inteiros válidos.
     */
    private function decodeHashidList(mixed $value): array
    {
        $hashes = is_array($value)
            ? $value
            : array_filter(array_map('trim', explode(',', $value)));

        $ids = [];
        foreach ($hashes as $hash) {
            $decoded = Hashids::decode($hash);
            if (!empty($decoded)) {
                $ids[] = $decoded[0];
            }
        }

        return $ids;
    }
}
