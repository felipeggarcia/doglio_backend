<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /** Cria um produto ativo com preço determinado. */
    private function product(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active'      => true,
            'price'          => '10.00',
            'stock_quantity' => 50,
        ], $attrs));
    }

    /** Cria um CartItem diretamente no banco para um usuário/produto. */
    private function cartItem(User $user, Product $product, array $attrs = []): CartItem
    {
        return CartItem::create(array_merge([
            'user_id'    => $user->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit_price' => $product->price,
        ], $attrs));
    }

    /** Cria uma promoção percentual ativa e a vincula a um produto. */
    private function activePromotion(Product $product, float $discount, array $attrs = []): Promotion
    {
        $promo = Promotion::create(array_merge([
            'name'           => 'Promo Teste',
            'type'           => 'percentage',
            'discount_value' => $discount,
            'starts_at'      => now()->subHour(),
            'ends_at'        => now()->addDay(),
            'is_active'      => true,
        ], $attrs));

        $product->promotions()->attach($promo->id, ['use_limit' => null, 'uses_count' => 0]);

        return $promo;
    }

    /** Payload padrão de sync com um produto. */
    private function syncPayload(Product $product, int $quantity = 1): array
    {
        return [
            'items' => [
                ['product_id' => $product->hashid, 'quantity' => $quantity],
            ],
        ];
    }

    // =========================================================================
    // GET /api/v1/cart
    // =========================================================================

    #[Test]
    public function show_returns_empty_cart_for_new_user(): void
    {
        $user = $this->customer();

        $this->withToken($this->token($user))
            ->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.total', '0.00')
            ->assertJsonPath('data.has_stock_warning', false)
            ->assertJsonPath('data.has_price_change', false);
    }

    #[Test]
    public function show_requires_authentication(): void
    {
        $this->getJson('/api/v1/cart')->assertStatus(401);
    }

    #[Test]
    public function show_returns_cart_with_items_and_totals(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '25.00']);
        $this->cartItem($user, $product, ['quantity' => 2, 'unit_price' => '25.00']);

        $this->withToken($this->token($user))
            ->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.total', '50.00')
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', '25.00')
            ->assertJsonPath('data.items.0.subtotal', '50.00');
    }

    #[Test]
    public function show_is_isolated_between_users(): void
    {
        $user1 = $this->customer();
        $user2 = $this->customer();
        $product = $this->product();
        $this->cartItem($user1, $product, ['quantity' => 3]);

        $this->withToken($this->token($user2))
            ->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.items', []);
    }

    #[Test]
    public function show_includes_product_info_in_items(): void
    {
        $user    = $this->customer();
        $product = $this->product(['name' => 'Produto Alpha', 'price' => '15.00', 'stock_quantity' => 10]);
        $this->cartItem($user, $product, ['unit_price' => '15.00']);

        $data = $this->withToken($this->token($user))
            ->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->json('data.items.0.product');

        $this->assertEquals('Produto Alpha', $data['name']);
        $this->assertEquals('15.00', $data['original_price']);
        $this->assertEquals(10, $data['stock_quantity']);
        $this->assertTrue($data['in_stock']);
    }

    #[Test]
    public function show_detects_price_change_since_added(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '20.00']);
        // Item adicionado a R$20, mas produto agora custa R$30
        $this->cartItem($user, $product, ['unit_price' => '20.00']);
        $product->update(['price' => '30.00']);

        $this->withToken($this->token($user))
            ->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('data.has_price_change', true)
            ->assertJsonPath('data.items.0.price_changed', true)
            ->assertJsonPath('data.items.0.current_price', '30.00')
            ->assertJsonPath('data.items.0.unit_price', '20.00');
    }

    #[Test]
    public function show_detects_stock_warning_when_quantity_exceeds_stock(): void
    {
        $user    = $this->customer();
        $product = $this->product(['stock_quantity' => 2]);
        $this->cartItem($user, $product, ['quantity' => 5, 'unit_price' => $product->price]);

        $this->withToken($this->token($user))
            ->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('data.has_stock_warning', true)
            ->assertJsonPath('data.items.0.stock_warning', true);
    }

    // =========================================================================
    // POST /api/v1/cart/sync
    // =========================================================================

    #[Test]
    public function sync_requires_authentication(): void
    {
        $product = $this->product();

        $this->postJson('/api/v1/cart/sync', $this->syncPayload($product))
            ->assertStatus(401);
    }

    #[Test]
    public function sync_adds_item_to_cart(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '19.90']);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/cart/sync', $this->syncPayload($product, 3))
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::CART_SYNCED)
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->assertDatabaseHas('cart_items', [
            'user_id'    => $user->id,
            'product_id' => $product->id,
            'quantity'   => 3,
        ]);
    }

    #[Test]
    public function sync_replaces_existing_cart_completely(): void
    {
        $user     = $this->customer();
        $product1 = $this->product();
        $product2 = $this->product();
        // Carrinho inicial com produto1
        $this->cartItem($user, $product1, ['quantity' => 2]);

        // Sync com apenas produto2 — produto1 deve ser removido
        $this->withToken($this->token($user))
            ->postJson('/api/v1/cart/sync', $this->syncPayload($product2, 1))
            ->assertStatus(200)
            ->assertJsonPath('data.items_count', 1);

        $this->assertDatabaseMissing('cart_items', [
            'user_id'    => $user->id,
            'product_id' => $product1->id,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'user_id'    => $user->id,
            'product_id' => $product2->id,
        ]);
    }

    #[Test]
    public function sync_captures_effective_price_with_active_promotion(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '100.00']);
        // Promoção de 20% ativa
        $this->activePromotion($product, 20);

        $response = $this->withToken($this->token($user))
            ->postJson('/api/v1/cart/sync', $this->syncPayload($product))
            ->assertStatus(200);

        // Preço efetivo deve ser R$80,00 (100 - 20%)
        $unitPrice = $response->json('data.items.0.unit_price');
        $this->assertEquals('80.00', $unitPrice);

        $this->assertDatabaseHas('cart_items', [
            'user_id'    => $user->id,
            'product_id' => $product->id,
            'unit_price' => '80.00',
        ]);
    }

    #[Test]
    public function sync_captures_full_price_without_promotion(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '49.99']);

        $response = $this->withToken($this->token($user))
            ->postJson('/api/v1/cart/sync', $this->syncPayload($product))
            ->assertStatus(200);

        $this->assertEquals('49.99', $response->json('data.items.0.unit_price'));
    }

    #[Test]
    public function sync_accepts_multiple_items(): void
    {
        $user     = $this->customer();
        $product1 = $this->product(['price' => '10.00']);
        $product2 = $this->product(['price' => '20.00']);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/cart/sync', [
                'items' => [
                    ['product_id' => $product1->hashid, 'quantity' => 1],
                    ['product_id' => $product2->hashid, 'quantity' => 2],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.items_count', 2)
            ->assertJsonPath('data.total', '50.00'); // 10 + 40
    }

    #[Test]
    public function sync_returns_422_for_invalid_product_hashid(): void
    {
        $user = $this->customer();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/cart/sync', [
                'items' => [['product_id' => 'INVALID_ID', 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    #[Test]
    public function sync_returns_422_for_nonexistent_product(): void
    {
        $user    = $this->customer();
        $product = $this->product();
        $hashid  = $product->hashid;
        $product->forceDelete();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/cart/sync', [
                'items' => [['product_id' => $hashid, 'quantity' => 1]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    }

    #[Test]
    public function sync_validates_quantity_min_1(): void
    {
        $user    = $this->customer();
        $product = $this->product();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/cart/sync', [
                'items' => [['product_id' => $product->hashid, 'quantity' => 0]],
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function sync_validates_quantity_max_999(): void
    {
        $user    = $this->customer();
        $product = $this->product();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/cart/sync', [
                'items' => [['product_id' => $product->hashid, 'quantity' => 1000]],
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function sync_requires_items_array(): void
    {
        $user = $this->customer();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/cart/sync', [])
            ->assertStatus(422);
    }

    // =========================================================================
    // GET /api/v1/cart/validate
    // =========================================================================

    #[Test]
    public function validate_requires_authentication(): void
    {
        $this->getJson('/api/v1/cart/validate')->assertStatus(401);
    }

    #[Test]
    public function validate_returns_valid_true_for_empty_cart(): void
    {
        $user = $this->customer();

        $this->withToken($this->token($user))
            ->getJson('/api/v1/cart/validate')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.changes', []);
    }

    #[Test]
    public function validate_returns_valid_true_for_unchanged_cart(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '15.00', 'stock_quantity' => 10]);
        $this->cartItem($user, $product, ['quantity' => 1, 'unit_price' => '15.00']);

        $this->withToken($this->token($user))
            ->getJson('/api/v1/cart/validate')
            ->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.changes', []);
    }

    #[Test]
    public function validate_detects_price_change(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '10.00', 'stock_quantity' => 10]);
        $this->cartItem($user, $product, ['unit_price' => '10.00']);
        $product->update(['price' => '15.00']);

        $response = $this->withToken($this->token($user))
            ->getJson('/api/v1/cart/validate')
            ->assertStatus(200)
            ->assertJsonPath('data.valid', false);

        $changes = $response->json('data.changes');
        $priceChange = collect($changes)->firstWhere('type', 'price_changed');

        $this->assertNotNull($priceChange);
        $this->assertEquals('10.00', $priceChange['old_price']);
        $this->assertEquals('15.00', $priceChange['new_price']);
    }

    #[Test]
    public function validate_detects_out_of_stock(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '10.00', 'stock_quantity' => 0]);
        $this->cartItem($user, $product, ['unit_price' => '10.00']);

        $response = $this->withToken($this->token($user))
            ->getJson('/api/v1/cart/validate')
            ->assertStatus(200)
            ->assertJsonPath('data.valid', false);

        $changes = $response->json('data.changes');
        $this->assertNotNull(collect($changes)->firstWhere('type', 'out_of_stock'));
    }

    #[Test]
    public function validate_detects_stock_reduced_below_requested_quantity(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '10.00', 'stock_quantity' => 3]);
        $this->cartItem($user, $product, ['quantity' => 5, 'unit_price' => '10.00']);

        $response = $this->withToken($this->token($user))
            ->getJson('/api/v1/cart/validate')
            ->assertStatus(200)
            ->assertJsonPath('data.valid', false);

        $changes = $response->json('data.changes');
        $stockReduced = collect($changes)->firstWhere('type', 'stock_reduced');

        $this->assertNotNull($stockReduced);
        $this->assertEquals(5, $stockReduced['requested_quantity']);
        $this->assertEquals(3, $stockReduced['available_quantity']);
    }

    #[Test]
    public function validate_detects_expired_promotion(): void
    {
        $user    = $this->customer();
        $product = $this->product(['price' => '50.00', 'stock_quantity' => 10]);
        // Promoção já expirada
        $expiredPromo = Promotion::create([
            'name'           => 'Promo Expirada',
            'type'           => 'percentage',
            'discount_value' => 10,
            'starts_at'      => now()->subDays(3),
            'ends_at'        => now()->subDay(),
            'is_active'      => true,
        ]);
        $product->promotions()->attach($expiredPromo->id, ['use_limit' => null, 'uses_count' => 0]);
        // Item adicionado com a promoção que agora expirou
        $this->cartItem($user, $product, [
            'unit_price'   => '45.00',
            'promotion_id' => $expiredPromo->id,
        ]);

        $response = $this->withToken($this->token($user))
            ->getJson('/api/v1/cart/validate')
            ->assertStatus(200)
            ->assertJsonPath('data.valid', false);

        $changes = $response->json('data.changes');
        $this->assertNotNull(collect($changes)->firstWhere('type', 'promotion_expired'));
    }

    // =========================================================================
    // DELETE /api/v1/cart
    // =========================================================================

    #[Test]
    public function clear_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/cart')->assertStatus(401);
    }

    #[Test]
    public function clear_removes_all_items_from_cart(): void
    {
        $user = $this->customer();
        $p1   = $this->product();
        $p2   = $this->product();
        $this->cartItem($user, $p1);
        $this->cartItem($user, $p2);

        $this->withToken($this->token($user))
            ->deleteJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::CART_CLEARED);

        $this->assertDatabaseMissing('cart_items', ['user_id' => $user->id]);
    }

    #[Test]
    public function clear_is_isolated_between_users(): void
    {
        $user1 = $this->customer();
        $user2 = $this->customer();
        $product = $this->product();
        $this->cartItem($user1, $product);
        $this->cartItem($user2, $product);

        // user1 limpa o carrinho
        $this->withToken($this->token($user1))
            ->deleteJson('/api/v1/cart')
            ->assertStatus(200);

        // Itens de user2 devem permanecer
        $this->assertDatabaseHas('cart_items', [
            'user_id'    => $user2->id,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function clear_on_empty_cart_returns_success(): void
    {
        $user = $this->customer();

        $this->withToken($this->token($user))
            ->deleteJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
