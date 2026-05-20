<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\UserFavorite;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FavoriteTest extends TestCase
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

    private function favorite(User $user, Product $product, array $attrs = []): UserFavorite
    {
        return UserFavorite::create(array_merge([
            'user_id'            => $user->id,
            'product_id'         => $product->id,
            'notify_on_restock'  => true,
        ], $attrs));
    }

    // =========================================================================
    // GET /api/v1/favorites
    // =========================================================================

    #[Test]
    public function index_returns_user_favorites_with_product_data(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create(['name' => 'Favorito']);
        $this->favorite($user, $product);

        $this->withToken($this->token($user))
            ->getJson('/api/v1/favorites')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.notify_on_restock', true)
            ->assertJsonPath('data.0.product.name', 'Favorito');
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/v1/favorites')->assertStatus(401);
    }

    #[Test]
    public function index_only_returns_current_user_favorites(): void
    {
        $user1   = $this->customer();
        $user2   = $this->customer();
        $product = Product::factory()->create();

        $this->favorite($user1, $product);
        $this->favorite($user2, Product::factory()->create());

        // user1 só vê o próprio favorito
        $this->withToken($this->token($user1))
            ->getJson('/api/v1/favorites')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function index_returns_empty_when_no_favorites(): void
    {
        $user = $this->customer();

        $this->withToken($this->token($user))
            ->getJson('/api/v1/favorites')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // =========================================================================
    // POST /api/v1/favorites
    // =========================================================================

    #[Test]
    public function store_adds_product_to_favorites(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/favorites', ['product_id' => $product->hashid])
            ->assertStatus(201)
            ->assertJson(['success' => true, 'message' => ApiMessages::FAVORITE_ADDED])
            ->assertJsonPath('data.product.id', $product->hashid);

        $this->assertDatabaseHas('user_favorites', [
            'user_id'    => $user->id,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function store_requires_authentication(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/v1/favorites', ['product_id' => $product->hashid])
            ->assertStatus(401);
    }

    #[Test]
    public function store_returns_404_for_invalid_product_hashid(): void
    {
        $user = $this->customer();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/favorites', ['product_id' => 'hashid-invalido'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    #[Test]
    public function store_prevents_duplicate_favorite(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $this->favorite($user, $product);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/favorites', ['product_id' => $product->hashid])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ALREADY_FAVORITED');
    }

    #[Test]
    public function store_defaults_notify_on_restock_to_true(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();

        // Sem enviar notify_on_restock → default true
        $this->withToken($this->token($user))
            ->postJson('/api/v1/favorites', ['product_id' => $product->hashid])
            ->assertStatus(201)
            ->assertJsonPath('data.notify_on_restock', true);
    }

    #[Test]
    public function store_accepts_notify_on_restock_false(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/favorites', [
                'product_id'         => $product->hashid,
                'notify_on_restock'  => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.notify_on_restock', false);
    }

    // =========================================================================
    // DELETE /api/v1/favorites/{favorite}
    // =========================================================================

    #[Test]
    public function destroy_removes_favorite(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $fav     = $this->favorite($user, $product);

        $this->withToken($this->token($user))
            ->deleteJson("/api/v1/favorites/{$fav->hashid}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'message' => ApiMessages::FAVORITE_REMOVED]);

        $this->assertSoftDeleted('user_favorites', ['id' => $fav->id]);
    }

    #[Test]
    public function destroy_requires_authentication(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $fav     = $this->favorite($user, $product);

        $this->deleteJson("/api/v1/favorites/{$fav->hashid}")->assertStatus(401);
    }

    #[Test]
    public function destroy_other_user_cannot_remove_favorite(): void
    {
        $owner   = $this->customer();
        $other   = $this->customer();
        $product = Product::factory()->create();
        $fav     = $this->favorite($owner, $product);

        $this->withToken($this->token($other))
            ->deleteJson("/api/v1/favorites/{$fav->hashid}")
            ->assertStatus(403);
    }

    // =========================================================================
    // PATCH /api/v1/favorites/{favorite}/notify
    // =========================================================================

    #[Test]
    public function toggle_notify_flips_from_true_to_false(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $fav     = $this->favorite($user, $product, ['notify_on_restock' => true]);

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/favorites/{$fav->hashid}/notify")
            ->assertStatus(200)
            ->assertJsonPath('data.notify_on_restock', false);

        $this->assertDatabaseHas('user_favorites', [
            'id'                => $fav->id,
            'notify_on_restock' => false,
        ]);
    }

    #[Test]
    public function toggle_notify_flips_from_false_to_true(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $fav     = $this->favorite($user, $product, ['notify_on_restock' => false]);

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/favorites/{$fav->hashid}/notify")
            ->assertStatus(200)
            ->assertJsonPath('data.notify_on_restock', true);
    }

    #[Test]
    public function toggle_notify_requires_authentication(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $fav     = $this->favorite($user, $product);

        $this->patchJson("/api/v1/favorites/{$fav->hashid}/notify")->assertStatus(401);
    }

    #[Test]
    public function toggle_notify_other_user_cannot_toggle(): void
    {
        $owner   = $this->customer();
        $other   = $this->customer();
        $product = Product::factory()->create();
        $fav     = $this->favorite($owner, $product);

        $this->withToken($this->token($other))
            ->patchJson("/api/v1/favorites/{$fav->hashid}/notify")
            ->assertStatus(403);
    }

    // =========================================================================
    // Testes especialista
    // =========================================================================

    #[Test]
    public function store_returns_404_for_soft_deleted_product(): void
    {
        // Product::find() respeita soft deletes — produto deletado retorna 404
        $user    = $this->customer();
        $product = Product::factory()->create();
        $hashid  = $product->hashid;
        $product->delete();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/favorites', ['product_id' => $hashid])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }

    #[Test]
    public function store_can_favorite_inactive_product(): void
    {
        // FavoriteController não filtra por is_active — produto inativo pode ser favoritado
        $user    = $this->customer();
        $product = Product::factory()->create(['is_active' => false]);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/favorites', ['product_id' => $product->hashid])
            ->assertStatus(201);

        $this->assertDatabaseHas('user_favorites', [
            'user_id'    => $user->id,
            'product_id' => $product->id,
        ]);
    }

    #[Test]
    public function index_does_not_show_deleted_favorites(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $fav     = $this->favorite($user, $product);

        $this->withToken($this->token($user))
            ->getJson('/api/v1/favorites')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $fav->delete();

        $this->withToken($this->token($user))
            ->getJson('/api/v1/favorites')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function toggle_notify_twice_returns_to_original_state(): void
    {
        $user    = $this->customer();
        $product = Product::factory()->create();
        $fav     = $this->favorite($user, $product, ['notify_on_restock' => true]);

        // Primeira chamada: true → false
        $this->withToken($this->token($user))
            ->patchJson("/api/v1/favorites/{$fav->hashid}/notify")
            ->assertJsonPath('data.notify_on_restock', false);

        // Segunda chamada: false → true (volta ao original)
        $this->withToken($this->token($user))
            ->patchJson("/api/v1/favorites/{$fav->hashid}/notify")
            ->assertJsonPath('data.notify_on_restock', true);
    }
}
