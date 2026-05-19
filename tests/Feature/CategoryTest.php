<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    // =========================================================================
    // GET /api/v1/categories  (publico)
    // =========================================================================

    #[Test]
    public function public_index_returns_only_active_categories(): void
    {
        Category::factory()->create(['name' => 'Ativa',   'is_active' => true]);
        Category::factory()->create(['name' => 'Inativa', 'is_active' => false]);

        $response = $this->getJson('/api/v1/categories')->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Ativa', $names);
        $this->assertNotContains('Inativa', $names);
    }

    #[Test]
    public function public_index_filters_by_is_highlighted(): void
    {
        Category::factory()->create(['name' => 'Destaque', 'is_highlighted' => true,  'is_active' => true]);
        Category::factory()->create(['name' => 'Normal',   'is_highlighted' => false, 'is_active' => true]);

        $response = $this->getJson('/api/v1/categories?is_highlighted=true')->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Destaque', $names);
        $this->assertNotContains('Normal', $names);
    }

    #[Test]
    public function public_index_filters_by_search(): void
    {
        Category::factory()->create(['name' => 'Racao Seca',  'is_active' => true]);
        Category::factory()->create(['name' => 'Brinquedos', 'is_active' => true]);

        $response = $this->getJson('/api/v1/categories?search=Racao')->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Racao Seca', $names);
        $this->assertNotContains('Brinquedos', $names);
    }

    #[Test]
    public function public_index_returns_products_count_when_requested(): void
    {
        Category::factory()->create(['is_active' => true]);

        $this->getJson('/api/v1/categories?with_count=true')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'products_count']]]);
    }

    // =========================================================================
    // GET /api/v1/categories/{id}  (publico)
    // =========================================================================

    #[Test]
    public function public_show_returns_active_category(): void
    {
        $category = Category::factory()->create(['name' => 'Coleiras', 'is_active' => true]);

        $this->getJson("/api/v1/categories/{$category->hashid}")
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Coleiras')
            ->assertJsonStructure(['data' => ['id', 'name', 'slug'], 'products']);
    }

    #[Test]
    public function public_show_returns_404_for_inactive_category(): void
    {
        $category = Category::factory()->create(['is_active' => false]);

        $this->getJson("/api/v1/categories/{$category->hashid}")
            ->assertStatus(404);
    }

    #[Test]
    public function public_show_returns_404_for_invalid_hashid(): void
    {
        $this->getJson('/api/v1/categories/hashid-invalido')
            ->assertStatus(404);
    }

    #[Test]
    public function public_show_returns_404_for_soft_deleted_category(): void
    {
        $category = Category::factory()->create(['is_active' => true]);
        $hashid   = $category->hashid;
        $category->delete();

        $this->getJson("/api/v1/categories/{$hashid}")
            ->assertStatus(404);
    }

    // =========================================================================
    // GET /api/v1/admin/categories
    // =========================================================================

    #[Test]
    public function admin_index_returns_all_categories_including_inactive(): void
    {
        Category::factory()->create(['name' => 'Ativa',   'is_active' => true]);
        Category::factory()->create(['name' => 'Inativa', 'is_active' => false]);

        $response = $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->getJson('/api/v1/admin/categories')
            ->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Ativa', $names);
        $this->assertContains('Inativa', $names);
    }

    #[Test]
    public function admin_index_returns_categories_in_correct_order(): void
    {
        Category::factory()->create(['name' => 'Zeta', 'is_active' => true,  'is_highlighted' => false]);
        Category::factory()->create(['name' => 'Alfa', 'is_active' => false, 'is_highlighted' => false]);
        Category::factory()->create(['name' => 'Beta', 'is_active' => true,  'is_highlighted' => true]);

        $response = $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->getJson('/api/v1/admin/categories')
            ->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->values();
        $this->assertEquals(['Beta', 'Zeta', 'Alfa'], $names->toArray());
    }

    #[Test]
    public function admin_index_filters_by_is_active_false(): void
    {
        Category::factory()->create(['name' => 'Ativa',   'is_active' => true]);
        Category::factory()->create(['name' => 'Inativa', 'is_active' => false]);

        $response = $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->getJson('/api/v1/admin/categories?is_active=false')
            ->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Inativa', $names);
        $this->assertNotContains('Ativa', $names);
    }

    #[Test]
    public function admin_index_filters_by_search(): void
    {
        Category::factory()->create(['name' => 'Coleiras Pet', 'is_active' => true]);
        Category::factory()->create(['name' => 'Brinquedos',   'is_active' => true]);

        $response = $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->getJson('/api/v1/admin/categories?search=Coleiras')
            ->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Coleiras Pet', $names);
        $this->assertNotContains('Brinquedos', $names);
    }

    #[Test]
    public function admin_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/categories')->assertStatus(401);
    }

    #[Test]
    public function admin_index_requires_admin_role(): void
    {
        $this->withToken($this->customer()->createToken('t')->plainTextToken)
            ->getJson('/api/v1/admin/categories')
            ->assertStatus(403);
    }

    // =========================================================================
    // POST /api/v1/admin/categories
    // =========================================================================

    #[Test]
    public function admin_can_create_category(): void
    {
        $response = $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->postJson('/api/v1/admin/categories', [
                'name'           => 'Racao Premium',
                'is_highlighted' => true,
                'is_active'      => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::CATEGORY_CREATED,
                'data'    => [
                    'name'           => 'Racao Premium',
                    'slug'           => 'racao-premium',
                    'is_highlighted' => true,
                    'is_active'      => true,
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'name'      => 'Racao Premium',
            'slug'      => 'racao-premium',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function create_category_with_is_active_false_saves_correctly(): void
    {
        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->postJson('/api/v1/admin/categories', [
                'name'      => 'Rascunho',
                'is_active' => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('categories', ['name' => 'Rascunho', 'is_active' => false]);
    }

    #[Test]
    public function create_category_increments_cache_version(): void
    {
        Cache::put('categories.version', 10);

        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->postJson('/api/v1/admin/categories', ['name' => 'Nova Categoria']);

        $this->assertEquals(11, (int) Cache::get('categories.version'));
    }

    #[Test]
    public function create_category_fails_with_duplicate_name(): void
    {
        Category::factory()->create(['name' => 'Existente']);

        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->postJson('/api/v1/admin/categories', ['name' => 'Existente'])
            ->assertStatus(422)
            ->assertJsonPath('error.details.name', fn ($v) => !empty($v));
    }

    #[Test]
    public function create_category_fails_without_name(): void
    {
        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->postJson('/api/v1/admin/categories', [])
            ->assertStatus(422)
            ->assertJsonPath('error.details.name', fn ($v) => !empty($v));
    }

    #[Test]
    public function customer_cannot_create_category(): void
    {
        $this->withToken($this->customer()->createToken('t')->plainTextToken)
            ->postJson('/api/v1/admin/categories', ['name' => 'Tentativa'])
            ->assertStatus(403);
    }

    #[Test]
    public function unauthenticated_user_cannot_create_category(): void
    {
        $this->postJson('/api/v1/admin/categories', ['name' => 'Tentativa'])
            ->assertStatus(401);
    }

    // =========================================================================
    // PUT /api/v1/admin/categories/{id}
    // =========================================================================

    #[Test]
    public function admin_can_update_category_and_slug_is_regenerated(): void
    {
        $category = Category::factory()->create(['name' => 'Antigo Nome', 'slug' => 'antigo-nome']);

        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->putJson("/api/v1/admin/categories/{$category->hashid}", [
                'name'      => 'Novo Nome',
                'is_active' => false,
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::CATEGORY_UPDATED,
                'data'    => ['name' => 'Novo Nome', 'slug' => 'novo-nome', 'is_active' => false],
            ]);

        $this->assertDatabaseHas('categories', [
            'id'   => $category->id,
            'name' => 'Novo Nome',
            'slug' => 'novo-nome',
        ]);
    }

    #[Test]
    public function update_category_allows_same_name_for_itself(): void
    {
        $category = Category::factory()->create(['name' => 'Meu Nome']);

        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->putJson("/api/v1/admin/categories/{$category->hashid}", ['name' => 'Meu Nome'])
            ->assertStatus(200);
    }

    #[Test]
    public function update_category_fails_with_name_used_by_another(): void
    {
        Category::factory()->create(['name' => 'Ocupado']);
        $category = Category::factory()->create(['name' => 'Livre']);

        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->putJson("/api/v1/admin/categories/{$category->hashid}", ['name' => 'Ocupado'])
            ->assertStatus(422)
            ->assertJsonPath('error.details.name', fn ($v) => !empty($v));
    }

    #[Test]
    public function update_category_increments_cache_version(): void
    {
        $category = Category::factory()->create();
        Cache::put('categories.version', 10);

        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->putJson("/api/v1/admin/categories/{$category->hashid}", ['name' => 'Atualizada']);

        $this->assertEquals(11, (int) Cache::get('categories.version'));
    }

    // =========================================================================
    // DELETE /api/v1/admin/categories/{id}
    // =========================================================================

    #[Test]
    public function admin_can_soft_delete_category(): void
    {
        $category = Category::factory()->create();

        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->deleteJson("/api/v1/admin/categories/{$category->hashid}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::CATEGORY_DELETED,
            ]);

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    #[Test]
    public function deleted_category_not_returned_in_public_index(): void
    {
        $category = Category::factory()->create(['name' => 'Sera Deletada', 'is_active' => true]);

        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->deleteJson("/api/v1/admin/categories/{$category->hashid}");

        $names = collect($this->getJson('/api/v1/categories')->json('data'))->pluck('name');
        $this->assertNotContains('Sera Deletada', $names);
    }

    #[Test]
    public function delete_category_increments_cache_version(): void
    {
        $category = Category::factory()->create();
        Cache::put('categories.version', 10);

        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->deleteJson("/api/v1/admin/categories/{$category->hashid}");

        $this->assertEquals(11, (int) Cache::get('categories.version'));
    }

    #[Test]
    public function create_category_name_cannot_exceed_100_chars(): void
    {
        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->postJson('/api/v1/admin/categories', ['name' => str_repeat('a', 101)])
            ->assertStatus(422)
            ->assertJsonPath('error.details.name', fn ($v) => !empty($v));
    }

    #[Test]
    public function update_category_name_cannot_exceed_100_chars(): void
    {
        $category = Category::factory()->create();

        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->putJson("/api/v1/admin/categories/{$category->hashid}", ['name' => str_repeat('b', 101)])
            ->assertStatus(422)
            ->assertJsonPath('error.details.name', fn ($v) => !empty($v));
    }

    #[Test]
    public function admin_cannot_update_soft_deleted_category(): void
    {
        $category = Category::factory()->create();
        $hashid   = $category->hashid;
        $category->delete();

        $this->withToken($this->admin()->createToken('t')->plainTextToken)
            ->putJson("/api/v1/admin/categories/{$hashid}", ['name' => 'Qualquer'])
            ->assertStatus(404);
    }
}