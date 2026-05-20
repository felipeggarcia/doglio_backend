<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromotionTest extends TestCase
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

    private function product(array $attrs = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true,
            'price'     => '100.00',
        ], $attrs));
    }

    /** Cria promoção percentual ativa por padrão. */
    private function promotion(array $attrs = []): Promotion
    {
        return Promotion::create(array_merge([
            'name'           => 'Promo Teste',
            'type'           => 'percentage',
            'discount_value' => 10,
            'starts_at'      => now()->subHour(),
            'ends_at'        => now()->addDay(),
            'is_active'      => true,
        ], $attrs));
    }

    /** Payload mínimo para criar promoção. */
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'Black Friday',
            'type'           => 'percentage',
            'discount_value' => 20,
            'starts_at'      => now()->addMinute()->toIso8601String(),
            'ends_at'        => now()->addDays(2)->toIso8601String(),
            'is_active'      => true,
        ], $overrides);
    }

    // =========================================================================
    // GET /api/v1/admin/promotions
    // =========================================================================

    #[Test]
    public function admin_index_requires_admin_role(): void
    {
        $customer = $this->customer();

        $this->withToken($this->token($customer))
            ->getJson('/api/v1/admin/promotions')
            ->assertStatus(403);
    }

    #[Test]
    public function admin_index_returns_all_promotions_paginated(): void
    {
        $admin = $this->admin();
        $this->promotion(['name' => 'Promo A']);
        $this->promotion(['name' => 'Promo B']);

        $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/promotions')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function admin_index_filters_by_is_active_true(): void
    {
        $admin = $this->admin();
        $this->promotion(['is_active' => true]);
        $this->promotion(['is_active' => false]);

        $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/promotions?is_active=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', true);
    }

    #[Test]
    public function admin_index_filters_by_is_active_false(): void
    {
        $admin = $this->admin();
        $this->promotion(['is_active' => true]);
        $this->promotion(['is_active' => false]);

        $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/promotions?is_active=0')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', false);
    }

    #[Test]
    public function admin_index_filters_expired(): void
    {
        $admin = $this->admin();
        $this->promotion(['ends_at' => now()->subDay()]); // expirada
        $this->promotion(['ends_at' => now()->addDay()]); // ativa

        $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/promotions?expired=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function admin_index_filters_by_search(): void
    {
        $admin = $this->admin();
        $this->promotion(['name' => 'Black Friday']);
        $this->promotion(['name' => 'Cyber Monday']);

        $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/promotions?search=Black')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Black Friday');
    }

    // =========================================================================
    // GET /api/v1/admin/promotions/{promotion}
    // =========================================================================

    #[Test]
    public function admin_show_requires_admin_role(): void
    {
        $customer = $this->customer();
        $promo    = $this->promotion();

        $this->withToken($this->token($customer))
            ->getJson("/api/v1/admin/promotions/{$promo->hashid}")
            ->assertStatus(403);
    }

    #[Test]
    public function admin_show_returns_promotion_with_correct_structure(): void
    {
        $admin = $this->admin();
        $promo = $this->promotion(['name' => 'Promo Detalhe', 'type' => 'fixed', 'discount_value' => 15]);

        $this->withToken($this->token($admin))
            ->getJson("/api/v1/admin/promotions/{$promo->hashid}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Promo Detalhe')
            ->assertJsonPath('data.type', 'fixed')
            ->assertJsonPath('data.discount_value', 15)
            ->assertJsonStructure(['data' => ['id', 'name', 'type', 'discount_value', 'is_active', 'starts_at']]);
    }

    #[Test]
    public function admin_show_returns_inactive_promotion(): void
    {
        $admin = $this->admin();
        $promo = $this->promotion(['is_active' => false]);

        $this->withToken($this->token($admin))
            ->getJson("/api/v1/admin/promotions/{$promo->hashid}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);
    }

    // =========================================================================
    // POST /api/v1/admin/promotions
    // =========================================================================

    #[Test]
    public function store_requires_admin_role(): void
    {
        $customer = $this->customer();

        $this->withToken($this->token($customer))
            ->postJson('/api/v1/admin/promotions', $this->storePayload())
            ->assertStatus(403);
    }

    #[Test]
    public function store_creates_promotion(): void
    {
        $admin = $this->admin();

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/admin/promotions', $this->storePayload([
                'name'           => 'Mega Promo',
                'type'           => 'percentage',
                'discount_value' => 25,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::PROMOTION_CREATED)
            ->assertJsonPath('data.name', 'Mega Promo')
            ->assertJsonPath('data.discount_value', 25);

        $this->assertDatabaseHas('promotions', ['name' => 'Mega Promo']);
    }

    #[Test]
    public function store_creates_promotion_with_products(): void
    {
        $admin   = $this->admin();
        $product = $this->product();

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/admin/promotions', array_merge($this->storePayload(), [
                'product_ids' => [['id' => $product->hashid, 'use_limit' => 50]],
            ]))
            ->assertStatus(201);

        $this->assertDatabaseHas('product_promotion', [
            'product_id'   => $product->id,
            'use_limit'    => 50,
        ]);
    }

    #[Test]
    public function store_returns_422_for_percentage_over_100(): void
    {
        $admin = $this->admin();

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/admin/promotions', $this->storePayload([
                'type'           => 'percentage',
                'discount_value' => 110,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    #[Test]
    public function store_requires_name_and_type(): void
    {
        $admin = $this->admin();

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/admin/promotions', [])
            ->assertStatus(422);
    }

    #[Test]
    public function store_creates_fixed_discount_promotion(): void
    {
        $admin = $this->admin();

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/admin/promotions', $this->storePayload([
                'type'           => 'fixed',
                'discount_value' => 30,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'fixed')
            ->assertJsonPath('data.discount_value', 30);
    }

    // =========================================================================
    // PUT /api/v1/admin/promotions/{promotion}
    // =========================================================================

    #[Test]
    public function update_requires_admin_role(): void
    {
        $customer = $this->customer();
        $promo    = $this->promotion();

        $this->withToken($this->token($customer))
            ->putJson("/api/v1/admin/promotions/{$promo->hashid}", ['name' => 'Novo Nome'])
            ->assertStatus(403);
    }

    #[Test]
    public function update_changes_promotion_fields(): void
    {
        $admin = $this->admin();
        $promo = $this->promotion(['name' => 'Promo Original']);

        $this->withToken($this->token($admin))
            ->putJson("/api/v1/admin/promotions/{$promo->hashid}", [
                'name'      => 'Promo Atualizada',
                'is_active' => false,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::PROMOTION_UPDATED)
            ->assertJsonPath('data.name', 'Promo Atualizada')
            ->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function update_returns_422_for_percentage_over_100(): void
    {
        $admin = $this->admin();
        $promo = $this->promotion(['type' => 'percentage', 'discount_value' => 10]);

        $this->withToken($this->token($admin))
            ->putJson("/api/v1/admin/promotions/{$promo->hashid}", ['discount_value' => 150])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    // =========================================================================
    // DELETE /api/v1/admin/promotions/{promotion}
    // =========================================================================

    #[Test]
    public function destroy_requires_admin_role(): void
    {
        $customer = $this->customer();
        $promo    = $this->promotion();

        $this->withToken($this->token($customer))
            ->deleteJson("/api/v1/admin/promotions/{$promo->hashid}")
            ->assertStatus(403);
    }

    #[Test]
    public function destroy_soft_deletes_promotion(): void
    {
        $admin = $this->admin();
        $promo = $this->promotion();

        $this->withToken($this->token($admin))
            ->deleteJson("/api/v1/admin/promotions/{$promo->hashid}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::PROMOTION_DELETED);

        $this->assertSoftDeleted('promotions', ['id' => $promo->id]);
    }

    // =========================================================================
    // POST /api/v1/admin/promotions/{promotion}/products
    // =========================================================================

    #[Test]
    public function attach_products_requires_admin(): void
    {
        $customer = $this->customer();
        $promo    = $this->promotion();
        $product  = $this->product();

        $this->withToken($this->token($customer))
            ->postJson("/api/v1/admin/promotions/{$promo->hashid}/products", [
                'products' => [['id' => $product->hashid]],
            ])
            ->assertStatus(403);
    }

    #[Test]
    public function attach_products_links_products_to_promotion(): void
    {
        $admin   = $this->admin();
        $promo   = $this->promotion();
        $product = $this->product();

        $this->withToken($this->token($admin))
            ->postJson("/api/v1/admin/promotions/{$promo->hashid}/products", [
                'products' => [['id' => $product->hashid, 'use_limit' => 100]],
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::PROMOTION_PRODUCTS_ATTACHED);

        $this->assertDatabaseHas('product_promotion', [
            'promotion_id' => $promo->id,
            'product_id'   => $product->id,
            'use_limit'    => 100,
        ]);
    }

    // =========================================================================
    // DELETE /api/v1/admin/promotions/{promotion}/products
    // =========================================================================

    #[Test]
    public function detach_products_removes_products_from_promotion(): void
    {
        $admin   = $this->admin();
        $promo   = $this->promotion();
        $product = $this->product();
        $promo->products()->attach($product->id, ['use_limit' => null, 'uses_count' => 0]);

        $this->withToken($this->token($admin))
            ->deleteJson("/api/v1/admin/promotions/{$promo->hashid}/products", [
                'product_ids' => [$product->hashid],
            ])
            ->assertStatus(200)
            ->assertJsonPath('message', ApiMessages::PROMOTION_PRODUCTS_DETACHED);

        $this->assertDatabaseMissing('product_promotion', [
            'promotion_id' => $promo->id,
            'product_id'   => $product->id,
        ]);
    }
}
