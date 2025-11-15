<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guest_can_list_categories()
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    /** @test */
    public function guest_can_filter_highlighted_categories()
    {
        Category::factory()->count(2)->create(['is_highlighted' => true]);
        Category::factory()->count(3)->create(['is_highlighted' => false]);

        $response = $this->getJson('/api/v1/categories?is_highlighted=true');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function guest_can_search_categories_by_name()
    {
        Category::factory()->create(['name' => 'Ração Premium']);
        Category::factory()->create(['name' => 'Brinquedos']);
        Category::factory()->create(['name' => 'Ração Econômica']);

        $response = $this->getJson('/api/v1/categories?search=ração');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function guest_can_view_category()
    {
        $category = Category::factory()->create(['name' => 'Test Category']);

        $response = $this->getJson('/api/v1/categories/' . $category->hashid);

        $response->assertStatus(200)
                 ->assertJson([
                     'data' => [
                         'id' => $category->hashid,
                         'name' => 'Test Category'
                     ]
                 ]);
    }

    /** @test */
    public function returns_404_for_nonexistent_category()
    {
        $response = $this->getJson('/api/v1/categories/nonexistent');

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'error' => [
                         'code' => 'RESOURCE_NOT_FOUND'
                     ]
                 ]);
    }

    /** @test */
    public function admin_can_create_category()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson('/api/v1/categories', [
                             'name' => 'New Category',
                             'is_highlighted' => true
                         ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'data' => [
                         'name' => 'New Category',
                         'is_highlighted' => true
                     ]
                 ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
            'slug' => 'new-category',
            'is_highlighted' => true
        ]);
    }

    /** @test */
    public function customer_cannot_create_category()
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')
                         ->postJson('/api/v1/categories', [
                             'name' => 'New Category'
                         ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'error' => [
                         'code' => 'FORBIDDEN'
                     ]
                 ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_create_category()
    {
        $response = $this->postJson('/api/v1/categories', [
            'name' => 'New Category'
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'success' => false,
                     'error' => [
                         'code' => 'UNAUTHENTICATED'
                     ]
                 ]);
    }

    /** @test */
    public function category_creation_requires_name()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson('/api/v1/categories', [
                             'is_highlighted' => true
                         ]);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'error' => [
                         'code' => 'VALIDATION_ERROR',
                         'details' => [
                             'name' => ['The name field is required.']
                         ]
                     ]
                 ]);
    }

    /** @test */
    public function category_name_must_be_unique()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Category::factory()->create(['name' => 'Existing Category']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->postJson('/api/v1/categories', [
                             'name' => 'Existing Category'
                         ]);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'error' => [
                         'code' => 'VALIDATION_ERROR',
                         'details' => [
                             'name' => ['The name has already been taken.']
                         ]
                     ]
                 ]);
    }

    /** @test */
    public function admin_can_update_category()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create([
            'name' => 'Old Name',
            'is_highlighted' => false
        ]);

        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson('/api/v1/categories/' . $category->hashid, [
                             'name' => 'Updated Name',
                             'is_highlighted' => true
                         ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'data' => [
                         'name' => 'Updated Name',
                         'is_highlighted' => true
                     ]
                 ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
            'slug' => 'updated-name',
            'is_highlighted' => true
        ]);
    }

    /** @test */
    public function customer_cannot_update_category()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $category = Category::factory()->create();

        $response = $this->actingAs($customer, 'sanctum')
                         ->putJson('/api/v1/categories/' . $category->hashid, [
                             'name' => 'Updated Name'
                         ]);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'error' => [
                         'code' => 'FORBIDDEN'
                     ]
                 ]);
    }

    /** @test */
    public function admin_can_delete_category()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
                         ->deleteJson('/api/v1/categories/' . $category->hashid);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'message' => 'Category deleted successfully'
                 ]);

        $this->assertSoftDeleted('categories', [
            'id' => $category->id
        ]);
    }

    /** @test */
    public function customer_cannot_delete_category()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $category = Category::factory()->create();

        $response = $this->actingAs($customer, 'sanctum')
                         ->deleteJson('/api/v1/categories/' . $category->hashid);

        $response->assertStatus(403)
                 ->assertJson([
                     'success' => false,
                     'error' => [
                         'code' => 'FORBIDDEN'
                     ]
                 ]);
    }

    /** @test */
    public function deleted_category_returns_404()
    {
        $category = Category::factory()->create();
        $categoryHashid = $category->hashid;
        $category->delete();

        $response = $this->getJson('/api/v1/categories/' . $categoryHashid);

        $response->assertStatus(404)
                 ->assertJson([
                     'success' => false,
                     'message' => 'Category not found',
                     'error' => [
                         'code' => 'RESOURCE_NOT_FOUND'
                     ]
                 ]);
    }

    /** @test */
    public function category_update_requires_unique_name()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Category::factory()->create(['name' => 'Existing Category']);
        $category = Category::factory()->create(['name' => 'Other Category']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson('/api/v1/categories/' . $category->hashid, [
                             'name' => 'Existing Category'
                         ]);

        $response->assertStatus(422)
                 ->assertJson([
                     'success' => false,
                     'error' => [
                         'code' => 'VALIDATION_ERROR',
                         'details' => [
                             'name' => ['The name has already been taken.']
                         ]
                     ]
                 ]);
    }

    /** @test */
    public function category_can_update_with_same_name()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create(['name' => 'Same Name']);

        $response = $this->actingAs($admin, 'sanctum')
                         ->putJson('/api/v1/categories/' . $category->hashid, [
                             'name' => 'Same Name',
                             'is_highlighted' => true
                         ]);

        $response->assertStatus(200);
    }
}
