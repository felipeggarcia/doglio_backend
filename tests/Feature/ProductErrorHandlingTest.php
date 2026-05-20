<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /** @test */
    public function returns_404_for_deleted_or_nonexistent_product()
    {
        // Testa com um produto que existe para garantir que a rota está funcionando
        $product = Product::factory()->create();
        $productHashid = $product->hashid;
        $response = $this->getJson("/api/v1/products/{$productHashid}");
        $response->assertStatus(200);
        
        // Agora deleta e tenta acessar (deve retornar 404 com mensagem de recurso não encontrado)
        $product->delete();
        $response = $this->getJson("/api/v1/products/{$productHashid}");
        
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => sprintf(ApiMessages::HTTP_RESOURCE_NOT_FOUND, 'Product'),
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'details' => sprintf(ApiMessages::HTTP_RESOURCE_NOT_FOUND_DETAILS, 'product')
                ]
            ]);
    }

    /** @test */
    public function returns_404_when_endpoint_does_not_exist()
    {
        $response = $this->getJson('/api/v1/nonexistent-endpoint');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::HTTP_ENDPOINT_NOT_FOUND,
                'error' => [
                    'code' => 'ENDPOINT_NOT_FOUND'
                ]
            ]);
    }

    /** @test */
    public function returns_401_when_accessing_protected_route_without_auth()
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/v1/admin/products/{$product->hashid}");

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::HTTP_UNAUTHENTICATED,
                'error' => [
                    'code' => 'UNAUTHENTICATED'
                ]
            ]);
    }

    /** @test */
    public function returns_422_on_validation_errors()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->withToken($this->token($admin))
            ->postJson('/api/v1/admin/products', [
                'name' => '', // Nome vazio (inválido)
                'price' => -10, // Preço negativo (inválido)
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::HTTP_VALIDATION_ERROR,
                'error' => [
                    'code' => 'VALIDATION_ERROR'
                ]
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'error' => [
                    'code',
                    'details' => ['name', 'description', 'price']
                ]
            ]);
    }

    /** @test */
    public function soft_deleted_products_are_excluded_from_listing()
    {
        $product1 = Product::factory()->create(['name' => 'Active Product']);
        $product2 = Product::factory()->create(['name' => 'Deleted Product']);
        
        $product2->delete();

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Active Product'])
            ->assertJsonMissing(['name' => 'Deleted Product']);
    }

    /** @test */
    public function admin_can_permanently_see_active_products_only()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product1 = Product::factory()->create(['name' => 'Active Product']);
        $product2 = Product::factory()->create(['name' => 'Deleted Product']);
        
        $product2->delete();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Active Product'])
            ->assertJsonMissing(['name' => 'Deleted Product']);
    }

    /** @test */
    public function returns_422_when_exceeding_image_limit()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();
        
        // Cria 4 imagens para o produto
        for ($i = 0; $i < 4; $i++) {
            $product->images()->create([
                'path' => "products/test_{$i}.jpg",
                'order' => $i,
                'is_primary' => $i === 0
            ]);
        }

        \Illuminate\Support\Facades\Storage::fake('public');
        
        // Tenta adicionar 3 imagens (total seria 7, excede o limite de 6)
        $response = $this->withToken($this->token($this->admin()))
            ->putJson("/api/v1/admin/products/{$product->hashid}", [
                'images' => [
                    \Illuminate\Http\UploadedFile::fake()->image('image1.jpg'),
                    \Illuminate\Http\UploadedFile::fake()->image('image2.jpg'),
                    \Illuminate\Http\UploadedFile::fake()->image('image3.jpg'),
                ]
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::PRODUCT_IMAGE_LIMIT,
                'error' => [
                    'code' => 'IMAGE_LIMIT_EXCEEDED',
                    'current_count' => 4,
                    'max_allowed' => 6
                ]
            ]);
    }
}
