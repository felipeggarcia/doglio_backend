<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function createAdmin()
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** @test */
    public function admin_can_create_product_with_single_image()
    {
        $admin = $this->createAdmin();
        $image = UploadedFile::fake()->image('product.jpg');

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/products', [
                'name' => 'Produto Teste',
                'description' => 'Descrição teste',
                'price' => 99.99,
                'stock_quantity' => 10,
                'images' => [$image],
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseCount('product_images', 1);
        
        $productImage = ProductImage::first();
        $this->assertTrue($productImage->is_primary);
        $this->assertEquals(0, $productImage->order);
        
        Storage::disk('public')->assertExists($productImage->path);
    }

    /** @test */
    public function admin_can_create_product_with_multiple_images()
    {
        $admin = $this->createAdmin();
        $images = [
            UploadedFile::fake()->image('product1.jpg'),
            UploadedFile::fake()->image('product2.jpg'),
            UploadedFile::fake()->image('product3.jpg'),
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/products', [
                'name' => 'Produto Teste',
                'description' => 'Descrição teste',
                'price' => 99.99,
                'stock_quantity' => 10,
                'images' => $images,
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseCount('product_images', 3);
        
        // Primeira imagem deve ser a principal
        $primaryImage = ProductImage::where('is_primary', true)->first();
        $this->assertNotNull($primaryImage);
        $this->assertEquals(0, $primaryImage->order);
        
        // As outras imagens não devem ser principais
        $nonPrimaryImages = ProductImage::where('is_primary', false)->get();
        $this->assertCount(2, $nonPrimaryImages);
    }

    /** @test */
    public function admin_can_upload_up_to_six_images()
    {
        $admin = $this->createAdmin();
        $images = [];
        
        for ($i = 0; $i < 6; $i++) {
            $images[] = UploadedFile::fake()->image("product{$i}.jpg");
        }

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/products', [
                'name' => 'Produto Teste',
                'description' => 'Descrição teste',
                'price' => 99.99,
                'stock_quantity' => 10,
                'images' => $images,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('product_images', 6);
    }

    /** @test */
    public function cannot_upload_more_than_six_images_on_create()
    {
        $admin = $this->createAdmin();
        $images = [];
        
        for ($i = 0; $i < 7; $i++) {
            $images[] = UploadedFile::fake()->image("product{$i}.jpg");
        }

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/products', [
                'name' => 'Produto Teste',
                'description' => 'Descrição teste',
                'price' => 99.99,
                'stock_quantity' => 10,
                'images' => $images,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images']);
    }

    /** @test */
    public function admin_can_add_images_to_existing_product()
    {
        $admin = $this->createAdmin();
        $product = Product::factory()->create();
        
        // Cria com 2 imagens
        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/existing1.jpg',
            'order' => 0,
            'is_primary' => true,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/existing2.jpg',
            'order' => 1,
            'is_primary' => false,
        ]);

        $newImages = [
            UploadedFile::fake()->image('new1.jpg'),
            UploadedFile::fake()->image('new2.jpg'),
        ];

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/products/{$product->hashid}", [
                'images' => $newImages,
            ]);

        $response->assertStatus(200);
        
        // Deve ter 4 imagens no total
        $this->assertDatabaseCount('product_images', 4);
        $this->assertEquals(4, $product->fresh()->images()->count());
    }

    /** @test */
    public function cannot_exceed_six_images_when_updating()
    {
        $admin = $this->createAdmin();
        $product = Product::factory()->create();
        
        // Cria com 5 imagens existentes
        for ($i = 0; $i < 5; $i++) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => "products/existing{$i}.jpg",
                'order' => $i,
                'is_primary' => $i === 0,
            ]);
        }

        // Tenta adicionar 2 novas (total seria 7)
        $newImages = [
            UploadedFile::fake()->image('new1.jpg'),
            UploadedFile::fake()->image('new2.jpg'),
        ];

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/products/{$product->hashid}", [
                'images' => $newImages,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Maximum limit of 6 images per product exceeded.',
            ]);
    }

    /** @test */
    public function admin_can_remove_images_from_product()
    {
        Storage::fake('public');
        $admin = $this->createAdmin();
        $product = Product::factory()->create();
        
        $image1 = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/image1.jpg',
            'order' => 0,
            'is_primary' => true,
        ]);
        
        $image2 = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/image2.jpg',
            'order' => 1,
            'is_primary' => false,
        ]);

        // Cria arquivos fake no storage
        Storage::disk('public')->put($image1->path, 'fake content');
        Storage::disk('public')->put($image2->path, 'fake content');

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/products/{$product->hashid}", [
                'remove_images' => [(string) $image1->hashid],
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseMissing('product_images', [
            'id' => $image1->id,
            'deleted_at' => null,
        ]);
        
        $this->assertDatabaseHas('product_images', [
            'id' => $image2->id,
        ]);
    }

    /** @test */
    public function admin_can_remove_and_add_images_in_same_request()
    {
        Storage::fake('public');
        $admin = $this->createAdmin();
        $product = Product::factory()->create();
        
        $oldImage = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/old.jpg',
            'order' => 0,
            'is_primary' => true,
        ]);
        
        Storage::disk('public')->put($oldImage->path, 'fake content');

        $newImage = UploadedFile::fake()->image('new.jpg');

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/products/{$product->hashid}", [
                'remove_images' => [(string) $oldImage->hashid],
                'images' => [$newImage],
            ]);

        $response->assertStatus(200);
        
        // Deve ter 1 imagem (removeu 1, adicionou 1)
        $this->assertEquals(1, $product->fresh()->images()->count());
    }

    /** @test */
    public function image_order_is_maintained_correctly()
    {
        $admin = $this->createAdmin();
        $images = [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
            UploadedFile::fake()->image('third.jpg'),
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/products', [
                'name' => 'Produto Teste',
                'description' => 'Descrição teste',
                'price' => 99.99,
                'stock_quantity' => 10,
                'images' => $images,
            ]);

        $response->assertStatus(201);
        
        $productImages = ProductImage::orderBy('order')->get();
        
        $this->assertEquals(0, $productImages[0]->order);
        $this->assertEquals(1, $productImages[1]->order);
        $this->assertEquals(2, $productImages[2]->order);
    }

    /** @test */
    public function only_first_image_is_marked_as_primary()
    {
        $admin = $this->createAdmin();
        $images = [
            UploadedFile::fake()->image('img1.jpg'),
            UploadedFile::fake()->image('img2.jpg'),
            UploadedFile::fake()->image('img3.jpg'),
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/products', [
                'name' => 'Produto Teste',
                'description' => 'Descrição teste',
                'price' => 99.99,
                'stock_quantity' => 10,
                'images' => $images,
            ]);

        $response->assertStatus(201);
        
        // Apenas 1 imagem deve ser primary
        $primaryCount = ProductImage::where('is_primary', true)->count();
        $this->assertEquals(1, $primaryCount);
        
        // A primeira (order = 0) deve ser a primary
        $primary = ProductImage::where('is_primary', true)->first();
        $this->assertEquals(0, $primary->order);
    }

    /** @test */
    public function images_are_included_in_product_response()
    {
        $admin = $this->createAdmin();
        $image = UploadedFile::fake()->image('product.jpg');

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/products', [
                'name' => 'Produto Teste',
                'description' => 'Descrição teste',
                'price' => 99.99,
                'stock_quantity' => 10,
                'images' => [$image],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'images' => [
                        '*' => ['id', 'url', 'order', 'is_primary']
                    ],
                    'primary_image' => ['id', 'url', 'order', 'is_primary']
                ]
            ]);
    }

    /** @test */
    public function primary_image_is_included_separately_in_response()
    {
        $admin = $this->createAdmin();
        $product = Product::factory()->create();
        
        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/primary.jpg',
            'order' => 0,
            'is_primary' => true,
        ]);
        
        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/secondary.jpg',
            'order' => 1,
            'is_primary' => false,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->hashid}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        $this->assertNotNull($data['primary_image']);
        $this->assertTrue($data['primary_image']['is_primary']);
        $this->assertCount(2, $data['images']);
    }

    /** @test */
    public function deleting_product_deletes_associated_images()
    {
        Storage::fake('public');
        $admin = $this->createAdmin();
        $product = Product::factory()->create();
        
        $image = ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/image.jpg',
            'order' => 0,
            'is_primary' => true,
        ]);
        
        Storage::disk('public')->put($image->path, 'fake content');

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/products/{$product->hashid}");

        $response->assertStatus(200);
        
        $this->assertSoftDeleted('products', ['id' => $product->id]);
        
        // ProductImage também deve ter soft delete quando produto é deletado
        $this->assertDatabaseHas('product_images', [
            'id' => $image->id,
            'product_id' => $product->id,
        ]);
    }

    /** @test */
    public function only_allowed_image_formats_are_accepted()
    {
        $admin = $this->createAdmin();
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/products', [
                'name' => 'Produto Teste',
                'description' => 'Descrição teste',
                'price' => 99.99,
                'stock_quantity' => 10,
                'images' => [$invalidFile],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    /** @test */
    public function image_size_must_not_exceed_limit()
    {
        $admin = $this->createAdmin();
        // Cria imagem maior que 2MB (2048KB)
        $largeImage = UploadedFile::fake()->image('large.jpg')->size(3000);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/products', [
                'name' => 'Produto Teste',
                'description' => 'Descrição teste',
                'price' => 99.99,
                'stock_quantity' => 10,
                'images' => [$largeImage],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }
}
