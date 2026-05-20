<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAddress;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function paymentMethod(array $attrs = []): PaymentMethod
    {
        return PaymentMethod::create(array_merge([
            'name'      => 'Pix',
            'type'      => 'pix',
            'is_active' => true,
        ], $attrs));
    }

    private function product(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active'      => true,
            'price'          => '50.00',
            'stock_quantity' => 100,
        ], $attrs));
    }

    /** Adiciona um item ao carrinho do usuário diretamente. */
    private function addToCart(User $user, Product $product, int $quantity = 1): CartItem
    {
        return CartItem::create([
            'user_id'    => $user->id,
            'product_id' => $product->id,
            'quantity'   => $quantity,
            'unit_price' => $product->price,
        ]);
    }

    /** Payload mínimo para checkout com entrega manual. */
    private function deliveryPayload(PaymentMethod $pm): array
    {
        return [
            'payment_method_id' => $pm->hashid,
            'delivery_type'     => 'delivery',
            'shipping_street'   => 'Rua das Flores',
            'shipping_number'   => '123',
            'shipping_city'     => 'Recife',
            'shipping_state'    => 'PE',
            'shipping_zip'      => '52000000',
        ];
    }

    /** Cria um pedido com item e pagamento diretamente no banco. */
    private function makeOrder(User $user, Product $product, array $orderAttrs = []): Order
    {
        $order = Order::create(array_merge([
            'user_id'         => $user->id,
            'status'          => 'pending',
            'total_amount'    => $product->price,
            'delivery_type'   => 'pickup',
        ], $orderAttrs));

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit_price' => $product->price,
        ]);

        return $order;
    }

    // =========================================================================
    // GET /api/v1/orders
    // =========================================================================

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/v1/orders')->assertStatus(401);
    }

    #[Test]
    public function index_returns_empty_for_user_with_no_orders(): void
    {
        $user = $this->customer();

        $this->withToken($this->token($user))
            ->getJson('/api/v1/orders')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function index_returns_only_own_orders(): void
    {
        $user1   = $this->customer();
        $user2   = $this->customer();
        $product = $this->product();
        $this->makeOrder($user1, $product);
        $this->makeOrder($user2, $product);

        $this->withToken($this->token($user1))
            ->getJson('/api/v1/orders')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function index_returns_paginated_orders(): void
    {
        $user    = $this->customer();
        $product = $this->product();
        $this->makeOrder($user, $product);
        $this->makeOrder($user, $product);

        $this->withToken($this->token($user))
            ->getJson('/api/v1/orders')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function index_returns_order_with_correct_structure(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '75.00']);
        $this->makeOrder($user, $product);

        $this->withToken($this->token($user))
            ->getJson('/api/v1/orders')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'status', 'total_amount', 'delivery_type', 'items', 'created_at']],
            ]);
    }

    // =========================================================================
    // GET /api/v1/orders/{order}
    // =========================================================================

    #[Test]
    public function show_requires_authentication(): void
    {
        $user    = $this->customer();
        $product = $this->product();
        $order   = $this->makeOrder($user, $product);

        $this->getJson("/api/v1/orders/{$order->hashid}")->assertStatus(401);
    }

    #[Test]
    public function show_returns_own_order(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '99.00']);
        $order   = $this->makeOrder($user, $product);

        $this->withToken($this->token($user))
            ->getJson("/api/v1/orders/{$order->hashid}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $order->hashid)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_amount', '99.00');
    }

    #[Test]
    public function show_returns_403_for_another_users_order(): void
    {
        $owner   = $this->customer();
        $other   = $this->customer();
        $product = $this->product();
        $order   = $this->makeOrder($owner, $product);

        $this->withToken($this->token($other))
            ->getJson("/api/v1/orders/{$order->hashid}")
            ->assertStatus(403);
    }

    #[Test]
    public function show_returns_order_items(): void
    {
        $user    = $this->customer();
        $product = $this->product(['name' => 'Produto Teste', 'price' => '30.00']);
        $order   = $this->makeOrder($user, $product);

        $data = $this->withToken($this->token($user))
            ->getJson("/api/v1/orders/{$order->hashid}")
            ->assertStatus(200)
            ->json('data.items');

        $this->assertCount(1, $data);
        $this->assertEquals(1, $data[0]['quantity']);
        $this->assertEquals('30.00', $data[0]['unit_price']);
    }

    // =========================================================================
    // POST /api/v1/checkout
    // =========================================================================

    #[Test]
    public function checkout_requires_authentication(): void
    {
        $pm = $this->paymentMethod();

        $this->postJson('/api/v1/checkout', [
            'payment_method_id' => $pm->hashid,
            'delivery_type'     => 'pickup',
        ])->assertStatus(401);
    }

    #[Test]
    public function checkout_returns_422_for_empty_cart(): void
    {
        $user = $this->customer();
        $pm   = $this->paymentMethod();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', [
                'payment_method_id' => $pm->hashid,
                'delivery_type'     => 'pickup',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CART_EMPTY');
    }

    #[Test]
    public function checkout_returns_422_for_invalid_payment_method(): void
    {
        $user    = $this->customer();
        $product = $this->product();
        $this->addToCart($user, $product);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', [
                'payment_method_id' => 'INVALID_HASH',
                'delivery_type'     => 'pickup',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    #[Test]
    public function checkout_returns_422_for_inactive_payment_method(): void
    {
        $user    = $this->customer();
        $product = $this->product();
        $pm      = $this->paymentMethod(['is_active' => false]);
        $this->addToCart($user, $product);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', [
                'payment_method_id' => $pm->hashid,
                'delivery_type'     => 'pickup',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    #[Test]
    public function checkout_returns_422_for_insufficient_stock(): void
    {
        $user    = $this->customer();
        $product = $this->product(['stock_quantity' => 2]);
        $pm      = $this->paymentMethod();
        $this->addToCart($user, $product, 5); // requisita 5, só tem 2

        $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', [
                'payment_method_id' => $pm->hashid,
                'delivery_type'     => 'pickup',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
    }

    #[Test]
    public function checkout_pickup_creates_order_with_no_shipping_address(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '20.00']);
        $pm      = $this->paymentMethod();
        $this->addToCart($user, $product, 2);

        $response = $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', [
                'payment_method_id' => $pm->hashid,
                'delivery_type'     => 'pickup',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::ORDER_CREATED)
            ->assertJsonPath('data.delivery_type', 'pickup')
            ->assertJsonPath('data.shipping_address', null)
            ->assertJsonPath('data.total_amount', '40.00');

        $orderId = $response->json('data.id');
        $this->assertNotNull($orderId);
    }

    #[Test]
    public function checkout_delivery_creates_order_with_manual_address(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '15.00']);
        $pm      = $this->paymentMethod();
        $this->addToCart($user, $product);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', $this->deliveryPayload($pm))
            ->assertStatus(201)
            ->assertJsonPath('data.delivery_type', 'delivery')
            ->assertJsonPath('data.shipping_address.city', 'Recife')
            ->assertJsonPath('data.shipping_address.state', 'PE')
            ->assertJsonPath('data.shipping_address.zip', '52000000');
    }

    #[Test]
    public function checkout_delivery_with_saved_address_uses_address_data(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '10.00']);
        $pm      = $this->paymentMethod();
        $this->addToCart($user, $product);

        $address = UserAddress::create([
            'user_id'    => $user->id,
            'label'      => 'Casa',
            'street'     => 'Rua das Pedras',
            'number'     => '999',
            'city'       => 'Olinda',
            'state'      => 'PE',
            'zip'        => '53000000',
            'is_primary' => false,
        ]);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', [
                'payment_method_id' => $pm->hashid,
                'delivery_type'     => 'delivery',
                'address_id'        => $address->hashid,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.shipping_address.city', 'Olinda')
            ->assertJsonPath('data.shipping_address.zip', '53000000');
    }

    #[Test]
    public function checkout_decrements_product_stock(): void
    {
        $user    = $this->customer();
        $product = $this->product(['stock_quantity' => 10]);
        $pm      = $this->paymentMethod();
        $this->addToCart($user, $product, 3);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', [
                'payment_method_id' => $pm->hashid,
                'delivery_type'     => 'pickup',
            ])
            ->assertStatus(201);

        $this->assertEquals(7, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function checkout_creates_stock_movement(): void
    {
        $user    = $this->customer();
        $product = $this->product(['stock_quantity' => 10]);
        $pm      = $this->paymentMethod();
        $this->addToCart($user, $product, 2);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', [
                'payment_method_id' => $pm->hashid,
                'delivery_type'     => 'pickup',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'   => $product->id,
            'type'         => 'out',
            'quantity'     => 2,
            'reason'       => 'sale',
            'stock_before' => 10,
        ]);
    }

    #[Test]
    public function checkout_clears_cart_after_order(): void
    {
        $user    = $this->customer();
        $product = $this->product();
        $pm      = $this->paymentMethod();
        $this->addToCart($user, $product);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', [
                'payment_method_id' => $pm->hashid,
                'delivery_type'     => 'pickup',
            ])
            ->assertStatus(201);

        $this->assertDatabaseMissing('cart_items', ['user_id' => $user->id]);
    }

    #[Test]
    public function checkout_creates_pending_payment_record(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '45.00']);
        $pm      = $this->paymentMethod(['type' => 'credit_card']);
        $this->addToCart($user, $product);

        $response = $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', [
                'payment_method_id' => $pm->hashid,
                'delivery_type'     => 'pickup',
            ])
            ->assertStatus(201);

        $orderId = Order::where('user_id', $user->id)->first()->id;
        $this->assertDatabaseHas('payments', [
            'order_id'          => $orderId,
            'payment_method_id' => $pm->id,
            'status'            => 'pending',
            'amount'            => '45.00',
        ]);
    }

    #[Test]
    public function checkout_delivery_requires_shipping_fields_when_no_address_id(): void
    {
        $user    = $this->customer();
        $product = $this->product();
        $pm      = $this->paymentMethod();
        $this->addToCart($user, $product);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/checkout', [
                'payment_method_id' => $pm->hashid,
                'delivery_type'     => 'delivery',
                // missing shipping fields
            ])
            ->assertStatus(422);
    }

    // =========================================================================
    // GET /api/v1/admin/orders
    // =========================================================================

    #[Test]
    public function admin_index_requires_admin_role(): void
    {
        $customer = $this->customer();

        $this->withToken($this->token($customer))
            ->getJson('/api/v1/admin/orders')
            ->assertStatus(403);
    }

    #[Test]
    public function admin_index_returns_all_orders(): void
    {
        $admin   = $this->admin();
        $user1   = $this->customer();
        $user2   = $this->customer();
        $product = $this->product();
        $this->makeOrder($user1, $product);
        $this->makeOrder($user2, $product);

        $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/orders')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function admin_index_filters_by_status(): void
    {
        $admin   = $this->admin();
        $user    = $this->customer();
        $product = $this->product();
        $this->makeOrder($user, $product, ['status' => 'pending']);
        $this->makeOrder($user, $product, ['status' => 'confirmed']);

        $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/orders?status=pending')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    #[Test]
    public function admin_index_filters_by_delivery_type(): void
    {
        $admin   = $this->admin();
        $user    = $this->customer();
        $product = $this->product();
        $this->makeOrder($user, $product, ['delivery_type' => 'pickup']);
        $this->makeOrder($user, $product, ['delivery_type' => 'delivery', 'shipping_street' => 'Rua A', 'shipping_number' => '1', 'shipping_city' => 'Recife', 'shipping_state' => 'PE', 'shipping_zip' => '52000000']);

        $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/orders?delivery_type=pickup')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.delivery_type', 'pickup');
    }

    // =========================================================================
    // GET /api/v1/admin/orders/{order}
    // =========================================================================

    #[Test]
    public function admin_show_requires_admin_role(): void
    {
        $customer = $this->customer();
        $product  = $this->product();
        $order    = $this->makeOrder($customer, $product);

        $this->withToken($this->token($customer))
            ->getJson("/api/v1/admin/orders/{$order->hashid}")
            ->assertStatus(403);
    }

    #[Test]
    public function admin_show_returns_any_order(): void
    {
        $admin   = $this->admin();
        $user    = $this->customer();
        $product = $this->product(['price' => '60.00']);
        $order   = $this->makeOrder($user, $product);

        $this->withToken($this->token($admin))
            ->getJson("/api/v1/admin/orders/{$order->hashid}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $order->hashid)
            ->assertJsonPath('data.total_amount', '60.00');
    }

    #[Test]
    public function admin_show_includes_customer_info(): void
    {
        $admin   = $this->admin();
        $user    = $this->customer();
        $product = $this->product();
        $order   = $this->makeOrder($user, $product);

        $data = $this->withToken($this->token($admin))
            ->getJson("/api/v1/admin/orders/{$order->hashid}")
            ->assertStatus(200)
            ->json('data.customer');

        $this->assertEquals($user->name, $data['name']);
        $this->assertEquals($user->email, $data['email']);
    }

    #[Test]
    public function admin_show_includes_status_history(): void
    {
        $admin   = $this->admin();
        $user    = $this->customer();
        $product = $this->product();
        $order   = $this->makeOrder($user, $product);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status'   => 'confirmed',
        ]);

        $history = $this->withToken($this->token($admin))
            ->getJson("/api/v1/admin/orders/{$order->hashid}")
            ->assertStatus(200)
            ->json('data.status_history');

        $this->assertCount(1, $history);
        $this->assertEquals('confirmed', $history[0]['status']);
    }

    // =========================================================================
    // PATCH /api/v1/admin/orders/{order}/status
    // =========================================================================

    #[Test]
    public function status_update_requires_admin_role(): void
    {
        $customer = $this->customer();
        $product  = $this->product();
        $order    = $this->makeOrder($customer, $product);

        $this->withToken($this->token($customer))
            ->patchJson("/api/v1/admin/orders/{$order->hashid}/status", ['status' => 'confirmed'])
            ->assertStatus(403);
    }

    #[Test]
    public function status_update_changes_order_status(): void
    {
        $admin   = $this->admin();
        $user    = $this->customer();
        $product = $this->product();
        $order   = $this->makeOrder($user, $product);

        $this->withToken($this->token($admin))
            ->patchJson("/api/v1/admin/orders/{$order->hashid}/status", ['status' => 'confirmed'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::ORDER_STATUS_UPDATED)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('orders', [
            'id'     => $order->id,
            'status' => 'confirmed',
        ]);
    }

    #[Test]
    public function status_update_creates_history_entry(): void
    {
        $admin   = $this->admin();
        $user    = $this->customer();
        $product = $this->product();
        $order   = $this->makeOrder($user, $product);

        $this->withToken($this->token($admin))
            ->patchJson("/api/v1/admin/orders/{$order->hashid}/status", [
                'status' => 'preparing',
                'notes'  => 'Em produção',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status'   => 'preparing',
            'notes'    => 'Em produção',
        ]);
    }

    #[Test]
    public function status_update_cancellation_restores_stock(): void
    {
        $admin   = $this->admin();
        $user    = $this->customer();
        $product = $this->product(['stock_quantity' => 5]);
        $order   = $this->makeOrder($user, $product); // 1 item quantity=1

        // Desconta o stock como se já tivesse sido vendido
        $product->decrement('stock_quantity', 1);
        $this->assertEquals(4, $product->fresh()->stock_quantity);

        $this->withToken($this->token($admin))
            ->patchJson("/api/v1/admin/orders/{$order->hashid}/status", ['status' => 'cancelled'])
            ->assertStatus(200);

        // Estoque deve voltar para 5
        $this->assertEquals(5, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function status_update_cancellation_creates_return_stock_movement(): void
    {
        $admin   = $this->admin();
        $user    = $this->customer();
        $product = $this->product(['stock_quantity' => 5]);
        $order   = $this->makeOrder($user, $product);

        $this->withToken($this->token($admin))
            ->patchJson("/api/v1/admin/orders/{$order->hashid}/status", ['status' => 'cancelled'])
            ->assertStatus(200);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'     => $product->id,
            'type'           => 'in',
            'quantity'       => 1,
            'reason'         => 'return',
            'reference_type' => 'order',
            'reference_id'   => $order->id,
        ]);
    }

    #[Test]
    public function status_update_rejects_invalid_status(): void
    {
        $admin   = $this->admin();
        $user    = $this->customer();
        $product = $this->product();
        $order   = $this->makeOrder($user, $product);

        $this->withToken($this->token($admin))
            ->patchJson("/api/v1/admin/orders/{$order->hashid}/status", ['status' => 'invalid_status'])
            ->assertStatus(422);
    }
}
