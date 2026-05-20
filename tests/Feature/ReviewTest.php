<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewTest extends TestCase
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

    /**
     * Cria Order + OrderItem + histórico "delivered" para o usuário/produto.
     * Isso é o mínimo para o usuário poder avaliar.
     */
    private function deliveredOrder(User $user, Product $product): Order
    {
        $order = Order::create([
            'user_id'      => $user->id,
            'status'       => 'delivered',
            'total_amount' => $product->price,
            'delivery_type' => 'delivery',
            'shipping_street' => 'Rua A',
            'shipping_number' => '1',
            'shipping_city'   => 'Recife',
            'shipping_state'  => 'PE',
            'shipping_zip'    => '52000000',
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit_price' => $product->price,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status'   => 'delivered',
        ]);

        return $order;
    }

    private function review(User $user, Product $product, array $attrs = []): Review
    {
        return Review::create(array_merge([
            'user_id'    => $user->id,
            'product_id' => $product->id,
            'rating'     => 5,
            'comment'    => 'Ótimo!',
        ], $attrs));
    }

    // =========================================================================
    // GET /api/v1/products/{product}/reviews  (público)
    // =========================================================================

    #[Test]
    public function index_returns_paginated_reviews_for_product(): void
    {
        $product  = Product::factory()->create(['is_active' => true]);
        $reviewer = $this->customer();
        $this->review($reviewer, $product, ['rating' => 4, 'comment' => 'Bom']);

        $this->getJson("/api/v1/products/{$product->hashid}/reviews")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 4)
            ->assertJsonPath('data.0.comment', 'Bom');
    }

    #[Test]
    public function index_does_not_require_authentication(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        // Sem token — deve retornar 200
        $this->getJson("/api/v1/products/{$product->hashid}/reviews")
            ->assertStatus(200);
    }

    #[Test]
    public function index_returns_empty_for_product_without_reviews(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->getJson("/api/v1/products/{$product->hashid}/reviews")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function index_returns_404_for_invalid_product_hashid(): void
    {
        $this->getJson('/api/v1/products/hashid-invalido/reviews')
            ->assertStatus(404);
    }

    #[Test]
    public function index_review_response_contains_user_info(): void
    {
        $product  = Product::factory()->create();
        $reviewer = $this->customer();
        $this->review($reviewer, $product);

        $response = $this->getJson("/api/v1/products/{$product->hashid}/reviews")
            ->assertStatus(200);

        $reviewData = $response->json('data.0');
        $this->assertArrayHasKey('id',         $reviewData);
        $this->assertArrayHasKey('rating',     $reviewData);
        $this->assertArrayHasKey('comment',    $reviewData);
        $this->assertArrayHasKey('created_at', $reviewData);
        $this->assertArrayHasKey('user',       $reviewData);
        $this->assertArrayHasKey('name',       $reviewData['user']);
    }

    // =========================================================================
    // POST /api/v1/products/{product}/reviews  (auth, compra entregue)
    // =========================================================================

    #[Test]
    public function store_creates_review_after_delivered_order(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $this->deliveredOrder($user, $product);

        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$product->hashid}/reviews", [
                'rating'  => 5,
                'comment' => 'Produto incrível!',
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true, 'message' => ApiMessages::REVIEW_SUBMITTED])
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Produto incrível!');

        $this->assertDatabaseHas('reviews', [
            'user_id'    => $user->id,
            'product_id' => $product->id,
            'rating'     => 5,
        ]);
    }

    #[Test]
    public function store_comment_is_optional(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $this->deliveredOrder($user, $product);

        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$product->hashid}/reviews", ['rating' => 3])
            ->assertStatus(201)
            ->assertJsonPath('data.comment', null);
    }

    #[Test]
    public function store_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this->postJson("/api/v1/products/{$product->hashid}/reviews", ['rating' => 5])
            ->assertStatus(401);
    }

    #[Test]
    public function store_requires_delivered_order(): void
    {
        // Usuário sem nenhum pedido não pode avaliar
        $user    = $this->customer();
        $product = Product::factory()->create();

        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$product->hashid}/reviews", ['rating' => 5])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PURCHASE_REQUIRED');
    }

    #[Test]
    public function store_user_with_non_delivered_order_cannot_review(): void
    {
        // Pedido existe mas NÃO tem histórico de status "delivered"
        $user    = $this->customer();
        $product = Product::factory()->create();

        $order = Order::create([
            'user_id'         => $user->id,
            'status'          => 'processing',
            'total_amount'    => $product->price,
            'delivery_type'   => 'delivery',
            'shipping_street' => 'Rua A',
            'shipping_number' => '1',
            'shipping_city'   => 'Recife',
            'shipping_state'  => 'PE',
            'shipping_zip'    => '52000000',
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit_price' => $product->price,
        ]);

        // Sem OrderStatusHistory de "delivered"
        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$product->hashid}/reviews", ['rating' => 5])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PURCHASE_REQUIRED');
    }

    #[Test]
    public function store_prevents_duplicate_review_for_same_product(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $this->deliveredOrder($user, $product);

        // Primeira avaliação
        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$product->hashid}/reviews", ['rating' => 5])
            ->assertStatus(201);

        // Segunda avaliação para o mesmo produto
        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$product->hashid}/reviews", ['rating' => 3])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_REVIEWED');
    }

    #[Test]
    public function store_validates_rating_range(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $this->deliveredOrder($user, $product);

        // rating = 0 (abaixo do mínimo)
        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$product->hashid}/reviews", ['rating' => 0])
            ->assertStatus(422)
            ->assertJsonPath('error.details.rating', fn ($v) => !empty($v));

        // rating = 6 (acima do máximo)
        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$product->hashid}/reviews", ['rating' => 6])
            ->assertStatus(422)
            ->assertJsonPath('error.details.rating', fn ($v) => !empty($v));
    }

    #[Test]
    public function store_validates_comment_max_1000_chars(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $this->deliveredOrder($user, $product);

        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$product->hashid}/reviews", [
                'rating'  => 4,
                'comment' => str_repeat('x', 1001),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.comment', fn ($v) => !empty($v));
    }

    // =========================================================================
    // DELETE /api/v1/reviews/{review}  (auth, dono)
    // =========================================================================

    #[Test]
    public function destroy_owner_can_delete_own_review(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $rev     = $this->review($user, $product);

        $this->withToken($this->token($user))
            ->deleteJson("/api/v1/reviews/{$rev->hashid}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'message' => ApiMessages::REVIEW_DELETED]);

        $this->assertDatabaseMissing('reviews', ['id' => $rev->id]);
    }

    #[Test]
    public function destroy_requires_authentication(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $rev     = $this->review($user, $product);

        $this->deleteJson("/api/v1/reviews/{$rev->hashid}")->assertStatus(401);
    }

    #[Test]
    public function destroy_other_user_cannot_delete_review(): void
    {
        $owner   = $this->customer();
        $other   = $this->customer();
        $product = Product::factory()->create();
        $rev     = $this->review($owner, $product);

        $this->withToken($this->token($other))
            ->deleteJson("/api/v1/reviews/{$rev->hashid}")
            ->assertStatus(403);
    }

    // =========================================================================
    // Testes especialista
    // =========================================================================

    #[Test]
    public function index_only_returns_reviews_of_the_requested_product(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $user     = $this->customer();
        $this->review($user, $productA, ['comment' => 'Review A']);
        $this->review($user, $productB, ['comment' => 'Review B']);

        $response = $this->getJson("/api/v1/products/{$productA->hashid}/reviews")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals('Review A', $response->json('data.0.comment'));
    }

    #[Test]
    public function store_can_review_after_order_delivered_even_if_later_cancelled(): void
    {
        // Regra de negócio: o histórico de "delivered" é suficiente,
        // mesmo que o pedido tenha mudado de status depois.
        $user    = $this->customer();
        $product = Product::factory()->create();
        $order   = $this->deliveredOrder($user, $product);

        // Simula cancelamento posterior
        $order->update(['status' => 'cancelled']);
        OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'cancelled']);

        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$product->hashid}/reviews", ['rating' => 2])
            ->assertStatus(201);
    }

    #[Test]
    public function store_two_users_can_each_review_same_product(): void
    {
        $user1   = $this->customer();
        $user2   = $this->customer();
        $product = Product::factory()->create();
        $this->deliveredOrder($user2, $product);

        // Criamos o review de user1 diretamente no banco — evita conflito
        // de cache do guard Sanctum em dois requests autenticados distintos
        $this->review($user1, $product, ['rating' => 5]);

        // Somente o request HTTP de user2 precisa de autenticação real
        $this->withToken($this->token($user2))
            ->postJson("/api/v1/products/{$product->hashid}/reviews", ['rating' => 3])
            ->assertStatus(201);

        $this->assertEquals(2, $product->reviews()->count());
    }

    #[Test]
    public function store_same_user_can_review_different_products(): void
    {
        $user     = $this->customer();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $this->deliveredOrder($user, $productA);
        $this->deliveredOrder($user, $productB);

        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$productA->hashid}/reviews", ['rating' => 5])
            ->assertStatus(201);

        $this->withToken($this->token($user))
            ->postJson("/api/v1/products/{$productB->hashid}/reviews", ['rating' => 4])
            ->assertStatus(201);

        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'product_id' => $productA->id]);
        $this->assertDatabaseHas('reviews', ['user_id' => $user->id, 'product_id' => $productB->id]);
    }

    #[Test]
    public function destroy_admin_cannot_delete_another_users_review(): void
    {
        // ReviewPolicy não tem bypass para admin — só o dono pode deletar
        $owner   = $this->customer();
        $admin   = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();
        $rev     = $this->review($owner, $product);

        $this->withToken($this->token($admin))
            ->deleteJson("/api/v1/reviews/{$rev->hashid}")
            ->assertStatus(403);

        $this->assertDatabaseHas('reviews', ['id' => $rev->id]);
    }
}
