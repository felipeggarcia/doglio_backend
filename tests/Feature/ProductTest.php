<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Promotion;
use App\Models\User;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductTest extends TestCase
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

    // =========================================================================
    // GET /api/v1/products  (público)
    // =========================================================================

    #[Test]
    public function index_returns_only_active_products(): void
    {
        Product::factory()->create(['name' => 'Ativo', 'is_active' => true]);
        Product::factory()->create(['name' => 'Inativo', 'is_active' => false]);

        $response = $this->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals('Ativo', $response->json('data.0.name'));
    }

    #[Test]
    public function index_returns_paginated_structure(): void
    {
        Product::factory()->count(3)->create(['is_active' => true]);

        $this->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    #[Test]
    public function index_highlighted_products_come_first(): void
    {
        Product::factory()->create([
            'name'           => 'Normal',
            'is_active'      => true,
            'is_highlighted' => false,
            'stock_quantity' => 10,
        ]);
        Product::factory()->create([
            'name'           => 'Destaque',
            'is_active'      => true,
            'is_highlighted' => true,
            'stock_quantity' => 10,
        ]);

        $response = $this->getJson('/api/v1/products')->assertStatus(200);

        $this->assertEquals('Destaque', $response->json('data.0.name'));
    }

    #[Test]
    public function index_out_of_stock_comes_last(): void
    {
        Product::factory()->create([
            'name'           => 'Sem Estoque',
            'is_active'      => true,
            'is_highlighted' => false,
            'stock_quantity' => 0,
        ]);
        Product::factory()->create([
            'name'           => 'Com Estoque',
            'is_active'      => true,
            'is_highlighted' => false,
            'stock_quantity' => 5,
        ]);

        $response = $this->getJson('/api/v1/products')->assertStatus(200);

        $last = $response->json('data.' . (count($response->json('data')) - 1) . '.name');
        $this->assertEquals('Sem Estoque', $last);
    }

    #[Test]
    public function index_filters_by_search(): void
    {
        Product::factory()->create(['name' => 'Chocolate Especial', 'is_active' => true]);
        Product::factory()->create(['name' => 'Bolo de Mel', 'is_active' => true]);

        $response = $this->getJson('/api/v1/products?search=Chocolate')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals('Chocolate Especial', $response->json('data.0.name'));
    }

    #[Test]
    public function index_filters_by_price_min(): void
    {
        Product::factory()->create(['price' => 10.00, 'is_active' => true]);
        Product::factory()->create(['price' => 100.00, 'is_active' => true]);

        $this->getJson('/api/v1/products?price_min=50')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function index_filters_by_price_max(): void
    {
        Product::factory()->create(['price' => 10.00, 'is_active' => true]);
        Product::factory()->create(['price' => 100.00, 'is_active' => true]);

        $this->getJson('/api/v1/products?price_max=50')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function index_does_not_expose_stock_quantity_to_guests(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 42]);

        $response = $this->getJson('/api/v1/products')->assertStatus(200);

        // stock_quantity é omitido (not present) para não-admins via when()
        $this->assertArrayNotHasKey('stock_quantity', $response->json('data.0'));
    }

    #[Test]
    public function index_soft_deleted_products_not_returned(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $product->delete();

        $this->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // =========================================================================
    // GET /api/v1/products/{product}  (público)
    // =========================================================================

    #[Test]
    public function show_returns_product_with_details(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->getJson("/api/v1/products/{$product->hashid}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'description', 'price', 'in_stock', 'is_highlighted', 'is_active', 'images', 'categories'],
            ])
            ->assertJsonPath('data.name', $product->name);
    }

    #[Test]
    public function show_returns_404_for_soft_deleted_product(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $hashid  = $product->hashid;
        $product->delete();

        $this->getJson("/api/v1/products/{$hashid}")->assertStatus(404);
    }

    #[Test]
    public function show_returns_404_for_invalid_hashid(): void
    {
        $this->getJson('/api/v1/products/invalid-hashid-xyz')->assertStatus(404);
    }

    #[Test]
    public function show_does_not_expose_stock_quantity_to_guest(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 25]);

        $response = $this->getJson("/api/v1/products/{$product->hashid}")->assertStatus(200);

        $this->assertArrayNotHasKey('stock_quantity', $response->json('data'));
    }

    #[Test]
    public function admin_index_exposes_stock_quantity_to_admin(): void
    {
        // A rota pública /products não tem auth:sanctum, então stock_quantity
        // só é visível para admins via /admin/products (que tem auth:sanctum)
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 25]);

        $response = $this->withToken($this->token($this->admin()))
            ->getJson('/api/v1/admin/products')
            ->assertStatus(200);

        $this->assertEquals(25, $response->json('data.0.stock_quantity'));
    }

    // =========================================================================
    // GET /api/v1/admin/products  (admin)
    // =========================================================================

    #[Test]
    public function admin_index_returns_all_including_inactive(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $this->withToken($this->token($this->admin()))
            ->getJson('/api/v1/admin/products')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function admin_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/products')->assertStatus(401);
    }

    #[Test]
    public function admin_index_requires_admin_role(): void
    {
        $this->withToken($this->token($this->customer()))
            ->getJson('/api/v1/admin/products')
            ->assertStatus(403);
    }

    #[Test]
    public function admin_index_filters_by_is_active(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->create(['is_active' => false]);

        $this->withToken($this->token($this->admin()))
            ->getJson('/api/v1/admin/products?is_active=false')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', false);
    }

    // =========================================================================
    // POST /api/v1/admin/products  (admin store)
    // =========================================================================

    #[Test]
    public function store_creates_product_with_valid_data(): void
    {
        $this->withToken($this->token($this->admin()))
            ->postJson('/api/v1/admin/products', [
                'name'        => 'Produto Teste',
                'description' => 'Descrição do produto',
                'price'       => 49.90,
            ])
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::PRODUCT_CREATED,
            ])
            ->assertJsonPath('data.name', 'Produto Teste')
            ->assertJsonPath('data.is_active', false); // produtos nascem inativos (DB default=false)

        $this->assertDatabaseHas('products', [
            'name'  => 'Produto Teste',
            'price' => 49.90,
        ]);
    }

    #[Test]
    public function store_requires_authentication(): void
    {
        $this->postJson('/api/v1/admin/products', [
            'name'        => 'X',
            'description' => 'X',
            'price'       => 1,
        ])->assertStatus(401);
    }

    #[Test]
    public function store_requires_admin_role(): void
    {
        $this->withToken($this->token($this->customer()))
            ->postJson('/api/v1/admin/products', [
                'name'        => 'X',
                'description' => 'X',
                'price'       => 1,
            ])->assertStatus(403);
    }

    #[Test]
    public function store_validates_required_fields(): void
    {
        $this->withToken($this->token($this->admin()))
            ->postJson('/api/v1/admin/products', [])
            ->assertStatus(422)
            ->assertJsonPath('error.details.name',        fn ($v) => !empty($v))
            ->assertJsonPath('error.details.description', fn ($v) => !empty($v))
            ->assertJsonPath('error.details.price',       fn ($v) => !empty($v));
    }

    #[Test]
    public function store_rejects_negative_price(): void
    {
        $this->withToken($this->token($this->admin()))
            ->postJson('/api/v1/admin/products', [
                'name'        => 'Produto',
                'description' => 'Desc',
                'price'       => -1,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.price', fn ($v) => !empty($v));
    }

    #[Test]
    public function store_new_product_always_starts_with_zero_stock(): void
    {
        $this->withToken($this->token($this->admin()))
            ->postJson('/api/v1/admin/products', [
                'name'        => 'Produto Zero',
                'description' => 'Desc',
                'price'       => 10,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('products', [
            'name'           => 'Produto Zero',
            'stock_quantity' => 0,
        ]);
    }

    #[Test]
    public function store_assigns_categories_via_hashids(): void
    {
        $category = Category::factory()->create();

        $this->withToken($this->token($this->admin()))
            ->postJson('/api/v1/admin/products', [
                'name'         => 'Com Categoria',
                'description'  => 'Desc',
                'price'        => 10,
                'category_ids' => [$category->hashid],
            ])
            ->assertStatus(201)
            ->assertJsonCount(1, 'data.categories');

        $product = Product::where('name', 'Com Categoria')->first();
        $this->assertTrue($product->categories->contains($category));
    }

    // =========================================================================
    // PUT /api/v1/admin/products/{product}  (admin update)
    // =========================================================================

    #[Test]
    public function update_admin_can_update_product_fields(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", [
                'name'  => 'Nome Atualizado',
                'price' => 99.99,
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::PRODUCT_UPDATED,
            ])
            ->assertJsonPath('data.name', 'Nome Atualizado');
    }

    #[Test]
    public function update_requires_admin_role(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->token($this->customer()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", ['name' => 'X'])
            ->assertStatus(403);
    }

    #[Test]
    public function update_can_deactivate_product(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", ['is_active' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
    }

    #[Test]
    public function update_can_toggle_is_highlighted(): void
    {
        $product = Product::factory()->create(['is_highlighted' => false]);

        $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", ['is_highlighted' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.is_highlighted', true);
    }

    #[Test]
    public function update_rejects_negative_price(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", ['price' => -5])
            ->assertStatus(422)
            ->assertJsonPath('error.details.price', fn ($v) => !empty($v));
    }

    #[Test]
    public function update_soft_deleted_product_returns_404(): void
    {
        $product = Product::factory()->create();
        $hashid  = $product->hashid;
        $product->delete();

        $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$hashid}", ['name' => 'Fantasma'])
            ->assertStatus(404);
    }

    // =========================================================================
    // DELETE /api/v1/admin/products/{product}  (admin destroy)
    // =========================================================================

    #[Test]
    public function destroy_admin_can_soft_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->token($this->admin()))
            ->deleteJson("/api/v1/admin/products/{$product->hashid}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::PRODUCT_DELETED,
            ]);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    #[Test]
    public function destroy_requires_admin_role(): void
    {
        $product = Product::factory()->create();

        $this->withToken($this->token($this->customer()))
            ->deleteJson("/api/v1/admin/products/{$product->hashid}")
            ->assertStatus(403);
    }

    #[Test]
    public function soft_deleted_product_disappears_from_public_index(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->withToken($this->token($this->admin()))
            ->deleteJson("/api/v1/admin/products/{$product->hashid}")
            ->assertStatus(200);

        $this->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // =========================================================================
    // Testes especialista / comportamentos críticos
    // =========================================================================

    #[Test]
    public function show_returns_inactive_product(): void
    {
        // show() não filtra por is_active — produto inativo é acessível via show
        // (comportamento intencional: frontend pode exibir mensagem "indisponível")
        $product = Product::factory()->create(['is_active' => false]);

        $this->getJson("/api/v1/products/{$product->hashid}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);
    }

    #[Test]
    public function store_ignores_is_active_field_product_always_born_inactive(): void
    {
        // store() não aceita is_active — produto sempre nasce com is_active=false
        // independente do que for enviado no request
        $this->withToken($this->token($this->admin()))
            ->postJson('/api/v1/admin/products', [
                'name'        => 'Tentativa Ativa',
                'description' => 'Desc',
                'price'       => 10,
                'is_active'   => true, // enviado mas deve ser ignorado
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('products', [
            'name'      => 'Tentativa Ativa',
            'is_active' => false,
        ]);
    }

    #[Test]
    public function update_can_reactivate_inactive_product(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", ['is_active' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => true]);
    }

    #[Test]
    public function update_with_empty_category_ids_removes_all_categories(): void
    {
        // category_ids usa sync() — enviar array vazio remove todas as categorias
        $category = Category::factory()->create();
        $product  = Product::factory()->create();
        $product->categories()->attach($category);

        $this->assertCount(1, $product->fresh()->categories);

        $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", [
                'category_ids' => [],
            ])
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.categories');

        $this->assertCount(0, $product->fresh()->categories);
    }

    // =========================================================================
    // Upload / imagens de produto
    // =========================================================================

    #[Test]
    public function store_uploads_images_and_first_is_primary(): void
    {
        Storage::fake('public');

        $this->withToken($this->token($this->admin()))
            ->postJson('/api/v1/admin/products', [
                'name'        => 'Com Fotos',
                'description' => 'Desc',
                'price'       => 10,
                'images'      => [
                    UploadedFile::fake()->image('foto1.jpg'),
                    UploadedFile::fake()->image('foto2.jpg'),
                ],
            ])
            ->assertStatus(201)
            ->assertJsonCount(2, 'data.images')
            ->assertJsonPath('data.images.0.is_primary', true)
            ->assertJsonPath('data.images.1.is_primary', false)
            ->assertJsonPath('data.images.0.order', 0)
            ->assertJsonPath('data.images.1.order', 1);

        $product = Product::where('name', 'Com Fotos')->first();
        $this->assertDatabaseHas('product_images', ['product_id' => $product->id, 'is_primary' => true]);
        $this->assertEquals(2, $product->images()->count());
    }

    #[Test]
    public function store_image_is_persisted_on_public_disk(): void
    {
        Storage::fake('public');

        $this->withToken($this->token($this->admin()))
            ->postJson('/api/v1/admin/products', [
                'name'        => 'Produto Disco',
                'description' => 'Desc',
                'price'       => 10,
                'images'      => [UploadedFile::fake()->image('capa.jpg')],
            ])
            ->assertStatus(201);

        $image = ProductImage::first();
        Storage::disk('public')->assertExists($image->path);
    }

    #[Test]
    public function store_images_response_contains_url_order_and_is_primary(): void
    {
        Storage::fake('public');

        $response = $this->withToken($this->token($this->admin()))
            ->postJson('/api/v1/admin/products', [
                'name'        => 'Struct Test',
                'description' => 'Desc',
                'price'       => 10,
                'images'      => [UploadedFile::fake()->image('img.png')],
            ])
            ->assertStatus(201);

        $img = $response->json('data.images.0');
        $this->assertArrayHasKey('id',         $img);
        $this->assertArrayHasKey('url',        $img);
        $this->assertArrayHasKey('order',      $img);
        $this->assertArrayHasKey('is_primary', $img);
        $this->assertStringContainsString('storage', $img['url']);
    }

    #[Test]
    public function store_rejects_non_image_file(): void
    {
        Storage::fake('public');

        $details = $this->withToken($this->token($this->admin()))
            ->postJson('/api/v1/admin/products', [
                'name'        => 'Ruim',
                'description' => 'Desc',
                'price'       => 10,
                'images'      => [UploadedFile::fake()->create('malware.txt', 10, 'text/plain')],
            ])
            ->assertStatus(422)
            ->json('error.details');

        // A chave tem ponto literal ("images.0") — data_get() não suporta escape, por isso acessa direto
        $this->assertTrue(!empty($details['images.0']));
    }

    #[Test]
    public function store_rejects_more_than_six_images(): void
    {
        Storage::fake('public');

        $images = array_fill(0, 7, UploadedFile::fake()->image('x.jpg'));

        $this->withToken($this->token($this->admin()))
            ->postJson('/api/v1/admin/products', [
                'name'        => 'Demais Fotos',
                'description' => 'Desc',
                'price'       => 10,
                'images'      => $images,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.images', fn ($v) => !empty($v));
    }

    #[Test]
    public function update_adds_images_to_product_without_images(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", [
                'images' => [
                    UploadedFile::fake()->image('a.jpg'),
                    UploadedFile::fake()->image('b.jpg'),
                ],
            ])
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.images')
            ->assertJsonPath('data.images.0.is_primary', true);
    }

    #[Test]
    public function update_removes_image_by_hashid_and_deletes_file(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        // Cria imagem real no disco falso
        $fake = UploadedFile::fake()->image('del.jpg');
        $path = $fake->storeAs('products', 'del.jpg', 'public');
        $image = ProductImage::create([
            'product_id' => $product->id,
            'path'       => $path,
            'order'      => 0,
            'is_primary' => true,
        ]);

        Storage::disk('public')->assertExists($path);

        $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", [
                'remove_images' => [$image->hashid],
            ])
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.images');

        Storage::disk('public')->assertMissing($path);
        $this->assertSoftDeleted('product_images', ['id' => $image->id]);
    }

    #[Test]
    public function update_rejects_upload_that_would_exceed_six_images(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        // Cria 5 registros de imagens diretamente no banco
        for ($i = 0; $i < 5; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'path'       => "products/img_{$i}.jpg",
                'order'      => $i,
                'is_primary' => $i === 0,
            ]);
        }

        // Tenta adicionar 2 novas → 5 + 2 = 7 → deve falhar com IMAGE_LIMIT_EXCEEDED
        $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", [
                'images' => [
                    UploadedFile::fake()->image('extra1.jpg'),
                    UploadedFile::fake()->image('extra2.jpg'),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'IMAGE_LIMIT_EXCEEDED');
    }

    #[Test]
    public function update_remove_and_add_within_limit_succeeds(): void
    {
        Storage::fake('public');
        $product = Product::factory()->create();

        // Cria 6 imagens (limite cheio)
        $images = [];
        for ($i = 0; $i < 6; $i++) {
            $path = "products/img_{$i}.jpg";
            Storage::disk('public')->put($path, 'fake');
            $images[] = ProductImage::create([
                'product_id' => $product->id,
                'path'       => $path,
                'order'      => $i,
                'is_primary' => $i === 0,
            ]);
        }

        // Remove 1 e adiciona 1 → resultado = 6 (dentro do limite)
        $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", [
                'remove_images' => [$images[5]->hashid],
                'images'        => [UploadedFile::fake()->image('nova.jpg')],
            ])
            ->assertStatus(200)
            ->assertJsonCount(6, 'data.images');
    }

    // =========================================================================
    // Filtros — index público
    // =========================================================================

    #[Test]
    public function index_filters_by_category_id(): void
    {
        $cat = Category::factory()->create();
        $inCat  = Product::factory()->create(['is_active' => true]);
        $outCat = Product::factory()->create(['is_active' => true]);
        $inCat->categories()->attach($cat);

        $response = $this->getJson("/api/v1/products?category_id={$cat->hashid}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals($inCat->hashid, $response->json('data.0.id'));
    }

    #[Test]
    public function index_filters_by_is_highlighted(): void
    {
        Product::factory()->create(['is_active' => true, 'is_highlighted' => true,  'name' => 'Destaque']);
        Product::factory()->create(['is_active' => true, 'is_highlighted' => false, 'name' => 'Normal']);

        $this->getJson('/api/v1/products?is_highlighted=true')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Destaque');
    }

    #[Test]
    public function index_filters_in_stock_only(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 10, 'name' => 'Disponível']);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 0,  'name' => 'Esgotado']);

        $this->getJson('/api/v1/products?in_stock=true')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Disponível');
    }

    #[Test]
    public function index_filters_out_of_stock_only(): void
    {
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 10, 'name' => 'Disponível']);
        Product::factory()->create(['is_active' => true, 'stock_quantity' => 0,  'name' => 'Esgotado']);

        $this->getJson('/api/v1/products?out_of_stock=true')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Esgotado');
    }

    #[Test]
    public function index_sorts_by_price_ascending(): void
    {
        Product::factory()->create(['is_active' => true, 'price' => 100, 'stock_quantity' => 5, 'name' => 'Caro']);
        Product::factory()->create(['is_active' => true, 'price' => 10,  'stock_quantity' => 5, 'name' => 'Barato']);

        $response = $this->getJson('/api/v1/products?sort_by=price&sort_order=asc')
            ->assertStatus(200);

        $this->assertEquals('Barato', $response->json('data.0.name'));
        $this->assertEquals('Caro',   $response->json('data.1.name'));
    }

    #[Test]
    public function index_filters_by_price_range(): void
    {
        // price_from + price_to usa whereBetween (distinto de price_min/price_max)
        Product::factory()->create(['is_active' => true, 'price' => 5,   'name' => 'Muito Barato']);
        Product::factory()->create(['is_active' => true, 'price' => 50,  'name' => 'Médio']);
        Product::factory()->create(['is_active' => true, 'price' => 200, 'name' => 'Caro']);

        $this->getJson('/api/v1/products?price_from=10&price_to=100')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Médio');
    }

    #[Test]
    public function index_filters_on_promotion(): void
    {
        $withPromo    = Product::factory()->create(['is_active' => true, 'name' => 'Em Promoção']);
        $withoutPromo = Product::factory()->create(['is_active' => true, 'name' => 'Sem Promoção']);

        $promo = Promotion::create([
            'name'           => 'Promo Teste',
            'type'           => 'percentage',
            'discount_value' => 10,
            'starts_at'      => now()->subDay(),
            'ends_at'        => now()->addDay(),
            'is_active'      => true,
        ]);
        $withPromo->promotions()->attach($promo);

        $this->getJson('/api/v1/products?on_promotion=true')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Em Promoção');
    }

    #[Test]
    public function index_respects_per_page_parameter(): void
    {
        Product::factory()->count(10)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/products?per_page=3')
            ->assertStatus(200);

        $this->assertCount(3, $response->json('data'));
        $this->assertEquals(10, $response->json('meta.total'));
    }

    // =========================================================================
    // Filtros — admin index
    // =========================================================================

    #[Test]
    public function admin_index_searches_in_name_and_description(): void
    {
        Product::factory()->create(['name' => 'Brigadeiro Gourmet',   'description' => 'Sobremesa']);
        Product::factory()->create(['name' => 'Bolo',                 'description' => 'Com recheio especial']);
        Product::factory()->create(['name' => 'Trufa',                'description' => 'Outro doce']);

        // Busca por nome
        $this->withToken($this->token($this->admin()))
            ->getJson('/api/v1/admin/products?search=Brigadeiro')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Brigadeiro Gourmet');

        // Busca por descrição
        $this->withToken($this->token($this->admin()))
            ->getJson('/api/v1/admin/products?search=especial')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bolo');
    }

    #[Test]
    public function admin_index_filters_by_is_highlighted(): void
    {
        Product::factory()->create(['is_highlighted' => true,  'name' => 'Destaque']);
        Product::factory()->create(['is_highlighted' => false, 'name' => 'Normal']);

        $this->withToken($this->token($this->admin()))
            ->getJson('/api/v1/admin/products?is_highlighted=true')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Destaque');
    }

    #[Test]
    public function admin_index_filters_by_multiple_category_ids(): void
    {
        $catA = Category::factory()->create(['name' => 'A']);
        $catB = Category::factory()->create(['name' => 'B']);

        $inBoth = Product::factory()->create(['name' => 'Nos Dois']);
        $inBoth->categories()->attach([$catA->id, $catB->id]);

        $onlyA = Product::factory()->create(['name' => 'Só A']);
        $onlyA->categories()->attach($catA);

        // Filtrar por catA E catB → só "Nos Dois" tem ambas
        $this->withToken($this->token($this->admin()))
            ->getJson("/api/v1/admin/products?category_ids[]={$catA->hashid}&category_ids[]={$catB->hashid}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Nos Dois');
    }

    #[Test]
    public function admin_index_sorts_by_price_descending(): void
    {
        Product::factory()->create(['price' => 10,  'name' => 'Barato']);
        Product::factory()->create(['price' => 100, 'name' => 'Caro']);

        $response = $this->withToken($this->token($this->admin()))
            ->getJson('/api/v1/admin/products?sort_by=price&sort_order=desc')
            ->assertStatus(200);

        $this->assertEquals('Caro',   $response->json('data.0.name'));
        $this->assertEquals('Barato', $response->json('data.1.name'));
    }
}
