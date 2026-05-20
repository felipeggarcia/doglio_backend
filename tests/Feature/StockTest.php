<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function product(int $stock = 50): Product
    {
        return Product::factory()->create([
            'is_active'      => true,
            'stock_quantity' => $stock,
        ]);
    }

    private function movement(Product $product, User $user, array $attrs = []): StockMovement
    {
        return StockMovement::create(array_merge([
            'product_id'   => $product->id,
            'type'         => 'in',
            'quantity'     => 5,
            'stock_before' => $product->stock_quantity,
            'reason'       => 'purchase',
            'user_id'      => $user->id,
        ], $attrs));
    }

    // =========================================================================
    // GET /api/v1/admin/products/{product}/stock
    // =========================================================================

    #[Test]
    public function index_requires_admin_role(): void
    {
        $customer = $this->customer();
        $product  = $this->product();

        $this->withToken($this->token($customer))
            ->getJson("/api/v1/admin/products/{$product->hashid}/stock")
            ->assertStatus(403);
    }

    #[Test]
    public function index_returns_paginated_movements(): void
    {
        $admin   = $this->admin();
        $product = $this->product();
        $this->movement($product, $admin);
        $this->movement($product, $admin, ['type' => 'out', 'reason' => 'sale', 'quantity' => 2]);

        $this->withToken($this->token($admin))
            ->getJson("/api/v1/admin/products/{$product->hashid}/stock")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function index_returns_empty_for_product_with_no_movements(): void
    {
        $admin   = $this->admin();
        $product = $this->product();

        $this->withToken($this->token($admin))
            ->getJson("/api/v1/admin/products/{$product->hashid}/stock")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // =========================================================================
    // POST /api/v1/admin/products/{product}/stock  — modo delta
    // =========================================================================

    #[Test]
    public function store_requires_admin_role(): void
    {
        $customer = $this->customer();
        $product  = $this->product();

        $this->withToken($this->token($customer))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'type'     => 'in',
                'quantity' => 10,
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function store_in_increments_stock(): void
    {
        $admin   = $this->admin();
        $product = $this->product(20);

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'type'     => 'in',
                'quantity' => 10,
                'reason'   => 'purchase',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::STOCK_MOVEMENT_CREATED)
            ->assertJsonPath('data.type', 'in')
            ->assertJsonPath('data.quantity', 10);

        $this->assertEquals(30, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function store_out_decrements_stock(): void
    {
        $admin   = $this->admin();
        $product = $this->product(30);

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'type'     => 'out',
                'quantity' => 5,
                'reason'   => 'loss',
            ])
            ->assertStatus(201);

        $this->assertEquals(25, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function store_out_returns_422_for_insufficient_stock(): void
    {
        $admin   = $this->admin();
        $product = $this->product(3);

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'type'     => 'out',
                'quantity' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
    }

    #[Test]
    public function store_creates_movement_with_stock_before(): void
    {
        $admin   = $this->admin();
        $product = $this->product(50);

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'type'     => 'in',
                'quantity' => 20,
                'reason'   => 'purchase',
                'notes'    => 'Reposição',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('stock_movements', [
            'product_id'   => $product->id,
            'type'         => 'in',
            'quantity'     => 20,
            'stock_before' => 50,
            'reason'       => 'purchase',
        ]);
    }

    #[Test]
    public function store_uses_manual_adjustment_as_default_reason(): void
    {
        $admin   = $this->admin();
        $product = $this->product(10);

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'type'     => 'in',
                'quantity' => 5,
                // reason omitted
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reason'     => 'manual_adjustment',
        ]);
    }

    #[Test]
    public function store_validates_reason_enum(): void
    {
        $admin   = $this->admin();
        $product = $this->product();

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'type'     => 'in',
                'quantity' => 5,
                'reason'   => 'invalid_reason',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function store_validates_quantity_min_1(): void
    {
        $admin   = $this->admin();
        $product = $this->product();

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'type'     => 'in',
                'quantity' => 0,
            ])
            ->assertStatus(422);
    }

    // =========================================================================
    // POST /api/v1/admin/products/{product}/stock — modo absoluto
    // =========================================================================

    #[Test]
    public function store_absolute_sets_exact_stock(): void
    {
        $admin   = $this->admin();
        $product = $this->product(10);

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'absolute' => 35,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.quantity', 25) // diff: 35-10
            ->assertJsonPath('data.type', 'in');

        $this->assertEquals(35, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function store_absolute_out_when_new_stock_is_lower(): void
    {
        $admin   = $this->admin();
        $product = $this->product(30);

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'absolute' => 20,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'out')
            ->assertJsonPath('data.quantity', 10);

        $this->assertEquals(20, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function store_absolute_same_value_returns_no_change(): void
    {
        $admin   = $this->admin();
        $product = $this->product(15);

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'absolute' => 15,
            ])
            ->assertStatus(200)
            ->assertJsonPath('message', ApiMessages::STOCK_NO_CHANGE);

        // Nenhum movimento deve ter sido criado
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
        // Estoque não muda
        $this->assertEquals(15, $product->fresh()->stock_quantity);
    }

    #[Test]
    public function store_absolute_validates_min_0(): void
    {
        $admin   = $this->admin();
        $product = $this->product();

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/products/{$product->hashid}/stock", [
                'absolute' => -1,
            ])
            ->assertStatus(422);
    }
}
