<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin()
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** @test */
    public function can_filter_products_by_category()
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $product1 = Product::factory()->create();
        $product1->categories()->attach($category1->id);

        $product2 = Product::factory()->create();
        $product2->categories()->attach($category2->id);

        $response = $this->getJson("/api/v1/products?category_id={$category1->hashid}");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
    }

    /** @test */
    public function can_filter_products_by_name()
    {
        Product::factory()->create(['name' => 'Ração Premium', 'stock_quantity' => 10]);
        Product::factory()->create(['name' => 'Brinquedo Cão', 'stock_quantity' => 10]);

        $response = $this->getJson('/api/v1/products?name=ração');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertStringContainsString('Ração', $data[0]['name']);
    }

    /** @test */
    public function can_filter_products_by_description()
    {
        Product::factory()->create([
            'name' => 'Produto A',
            'description' => 'Descrição com palavra especial',
            'stock_quantity' => 10
        ]);
        Product::factory()->create([
            'name' => 'Produto B',
            'description' => 'Descrição comum',
            'stock_quantity' => 10
        ]);

        $response = $this->getJson('/api/v1/products?description=especial');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertStringContainsString('especial', $data[0]['description']);
    }

    /** @test */
    public function can_search_products_in_name_or_description()
    {
        Product::factory()->create([
            'name' => 'Produto com termo importante',
            'description' => 'Descrição normal',
            'stock_quantity' => 10
        ]);
        Product::factory()->create([
            'name' => 'Produto comum',
            'description' => 'Descrição com termo importante',
            'stock_quantity' => 10
        ]);
        Product::factory()->create([
            'name' => 'Produto X',
            'description' => 'Descrição Y',
            'stock_quantity' => 10
        ]);

        $response = $this->getJson('/api/v1/products?search=importante');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(2, $data);
    }

    /** @test */
    public function can_filter_products_by_minimum_price()
    {
        Product::factory()->create(['name' => 'Produto Barato', 'price' => 10.00, 'stock_quantity' => 10]);
        Product::factory()->create(['name' => 'Produto Caro', 'price' => 100.00, 'stock_quantity' => 10]);

        $response = $this->getJson('/api/v1/products?price_min=50');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals('100.00', $data[0]['price']);
    }

    /** @test */
    public function can_filter_products_by_maximum_price()
    {
        Product::factory()->create(['name' => 'Produto Barato', 'price' => 10.00, 'stock_quantity' => 10]);
        Product::factory()->create(['name' => 'Produto Caro', 'price' => 100.00, 'stock_quantity' => 10]);

        $response = $this->getJson('/api/v1/products?price_max=50');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals('10.00', $data[0]['price']);
    }

    /** @test */
    public function can_filter_products_by_price_range()
    {
        Product::factory()->create(['price' => 10.00, 'stock_quantity' => 10]);
        Product::factory()->create(['price' => 50.00, 'stock_quantity' => 10]);
        Product::factory()->create(['price' => 100.00, 'stock_quantity' => 10]);

        $response = $this->getJson('/api/v1/products?price_from=25&price_to=75');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals('50.00', $data[0]['price']);
    }

    /** @test */
    public function can_filter_products_by_minimum_stock()
    {
        Product::factory()->create(['stock_quantity' => 5]);
        Product::factory()->create(['stock_quantity' => 50]);

        $response = $this->getJson('/api/v1/products?stock_min=10');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals(50, $data[0]['stock_quantity']);
    }

    /** @test */
    public function can_filter_products_by_maximum_stock()
    {
        Product::factory()->create(['stock_quantity' => 5]);
        Product::factory()->create(['stock_quantity' => 50]);

        $response = $this->getJson('/api/v1/products?stock_max=10');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals(5, $data[0]['stock_quantity']);
    }

    /** @test */
    public function can_filter_products_in_stock()
    {
        Product::factory()->create(['stock_quantity' => 0]);
        Product::factory()->create(['stock_quantity' => 10]);

        $response = $this->getJson('/api/v1/products?in_stock=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertGreaterThan(0, $data[0]['stock_quantity']);
    }

    /** @test */
    public function hides_out_of_stock_products_by_default()
    {
        Product::factory()->create(['name' => 'Produto sem estoque', 'stock_quantity' => 0]);
        Product::factory()->create(['name' => 'Produto com estoque', 'stock_quantity' => 10]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Deve retornar apenas produtos com estoque
        foreach ($data as $product) {
            $this->assertGreaterThan(0, $product['stock_quantity']);
        }
    }

    /** @test */
    public function can_show_out_of_stock_products_explicitly()
    {
        Product::factory()->create(['name' => 'Produto sem estoque', 'stock_quantity' => 0]);
        Product::factory()->create(['name' => 'Produto com estoque', 'stock_quantity' => 10]);

        $response = $this->getJson('/api/v1/products?out_of_stock=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Deve incluir produtos sem estoque
        $hasOutOfStock = false;
        foreach ($data as $product) {
            if ($product['stock_quantity'] === 0) {
                $hasOutOfStock = true;
                break;
            }
        }
        
        $this->assertTrue($hasOutOfStock);
    }

    /** @test */
    public function can_filter_only_out_of_stock_products()
    {
        Product::factory()->create(['stock_quantity' => 0]);
        Product::factory()->create(['stock_quantity' => 0]);
        Product::factory()->create(['stock_quantity' => 10]);

        $response = $this->getJson('/api/v1/products?out_of_stock=true&stock_max=0');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $product) {
            $this->assertEquals(0, $product['stock_quantity']);
        }
    }

    /** @test */
    public function can_filter_highlighted_products()
    {
        Product::factory()->create(['is_highlighted' => true, 'stock_quantity' => 10]);
        Product::factory()->create(['is_highlighted' => false, 'stock_quantity' => 10]);

        $response = $this->getJson('/api/v1/products?is_highlighted=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $product) {
            $this->assertTrue($product['is_highlighted']);
        }
    }

    /** @test */
    public function can_sort_products_by_name()
    {
        Product::factory()->create(['name' => 'Zebra', 'stock_quantity' => 10]);
        Product::factory()->create(['name' => 'Alpha', 'stock_quantity' => 10]);
        Product::factory()->create(['name' => 'Beta', 'stock_quantity' => 10]);

        $response = $this->getJson('/api/v1/products?sort_by=name&sort_order=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals('Alpha', $data[0]['name']);
        $this->assertEquals('Beta', $data[1]['name']);
        $this->assertEquals('Zebra', $data[2]['name']);
    }

    /** @test */
    public function can_sort_products_by_price()
    {
        Product::factory()->create(['price' => 100.00, 'stock_quantity' => 10]);
        Product::factory()->create(['price' => 10.00, 'stock_quantity' => 10]);
        Product::factory()->create(['price' => 50.00, 'stock_quantity' => 10]);

        $response = $this->getJson('/api/v1/products?sort_by=price&sort_order=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals('10.00', $data[0]['price']);
        $this->assertEquals('50.00', $data[1]['price']);
        $this->assertEquals('100.00', $data[2]['price']);
    }

    /** @test */
    public function can_sort_products_by_stock_quantity()
    {
        Product::factory()->create(['stock_quantity' => 50]);
        Product::factory()->create(['stock_quantity' => 5]);
        Product::factory()->create(['stock_quantity' => 25]);

        $response = $this->getJson('/api/v1/products?sort_by=stock_quantity&sort_order=desc');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals(50, $data[0]['stock_quantity']);
        $this->assertEquals(25, $data[1]['stock_quantity']);
        $this->assertEquals(5, $data[2]['stock_quantity']);
    }

    /** @test */
    public function default_sorting_orders_highlighted_first()
    {
        $normal1 = Product::factory()->create([
            'name' => 'Normal 1',
            'is_highlighted' => false,
            'stock_quantity' => 100
        ]);
        $highlighted = Product::factory()->create([
            'name' => 'Highlighted',
            'is_highlighted' => true,
            'stock_quantity' => 10
        ]);
        $normal2 = Product::factory()->create([
            'name' => 'Normal 2',
            'is_highlighted' => false,
            'stock_quantity' => 50
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Primeiro produto deve ser o destacado
        $this->assertTrue($data[0]['is_highlighted']);
    }

    /** @test */
    public function default_sorting_orders_by_stock_within_highlighted_groups()
    {
        $highlighted1 = Product::factory()->create([
            'is_highlighted' => true,
            'stock_quantity' => 10
        ]);
        $highlighted2 = Product::factory()->create([
            'is_highlighted' => true,
            'stock_quantity' => 50
        ]);
        $normal1 = Product::factory()->create([
            'is_highlighted' => false,
            'stock_quantity' => 100
        ]);
        $normal2 = Product::factory()->create([
            'is_highlighted' => false,
            'stock_quantity' => 5
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Primeiro deve ser highlighted com maior estoque
        $this->assertTrue($data[0]['is_highlighted']);
        $this->assertEquals(50, $data[0]['stock_quantity']);
        
        // Segundo deve ser highlighted com menor estoque
        $this->assertTrue($data[1]['is_highlighted']);
        $this->assertEquals(10, $data[1]['stock_quantity']);
        
        // Terceiro deve ser normal com maior estoque
        $this->assertFalse($data[2]['is_highlighted']);
        $this->assertEquals(100, $data[2]['stock_quantity']);
    }

    /** @test */
    public function can_combine_multiple_filters()
    {
        $category = Category::factory()->create();
        
        $product1 = Product::factory()->create([
            'name' => 'Ração Premium',
            'price' => 150.00,
            'stock_quantity' => 20,
            'is_highlighted' => true
        ]);
        $product1->categories()->attach($category->id);
        
        $product2 = Product::factory()->create([
            'name' => 'Ração Comum',
            'price' => 50.00,
            'stock_quantity' => 100,
            'is_highlighted' => false
        ]);
        $product2->categories()->attach($category->id);
        
        Product::factory()->create([
            'name' => 'Brinquedo',
            'price' => 80.00,
            'stock_quantity' => 10,
            'is_highlighted' => true
        ]);

        $response = $this->getJson("/api/v1/products?category_id={$category->hashid}&is_highlighted=true&price_min=100");

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals('Ração Premium', $data[0]['name']);
    }
}
