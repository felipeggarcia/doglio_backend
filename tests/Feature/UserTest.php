<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin()
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    protected function createCustomer()
    {
        return User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function admin_can_list_users()
    {
        $admin = $this->createAdmin();
        User::factory()->count(5)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'city', 'state', 'is_active']
                ]
            ]);
    }

    /** @test */
    public function customer_cannot_list_users()
    {
        $customer = $this->createCustomer();

        $response = $this->actingAs($customer)
            ->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_filter_users_by_role()
    {
        $admin = $this->createAdmin();
        User::factory()->count(3)->create(['role' => 'customer']);
        User::factory()->count(2)->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/users?role=customer');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $user) {
            $this->assertEquals('customer', $user['role']);
        }
    }

    /** @test */
    public function admin_can_filter_users_by_is_active()
    {
        $admin = $this->createAdmin();
        User::factory()->count(3)->create(['is_active' => true]);
        User::factory()->count(2)->create(['is_active' => false]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/users?is_active=true');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $user) {
            $this->assertTrue($user['is_active']);
        }
    }

    /** @test */
    public function admin_can_search_users()
    {
        $admin = $this->createAdmin();
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/users?search=john');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertStringContainsString('john', strtolower($data[0]['name'] ?? $data[0]['email']));
    }

    /** @test */
    public function admin_can_create_user()
    {
        $admin = $this->createAdmin();

        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'customer',
            'city' => 'São Paulo',
            'state' => 'SP',
            'is_active' => true,
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/users', $userData);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'New User',
                'email' => 'newuser@example.com',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role' => 'customer',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function admin_can_create_admin_user()
    {
        $admin = $this->createAdmin();

        $userData = [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'city' => 'Rio de Janeiro',
            'state' => 'RJ',
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/users', $userData);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@example.com',
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function customer_cannot_create_user()
    {
        $customer = $this->createCustomer();

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role' => 'customer',
            'city' => 'São Paulo',
            'state' => 'SP',
        ];

        $response = $this->actingAs($customer)
            ->postJson('/api/v1/users', $userData);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_view_user()
    {
        $admin = $this->createAdmin();
        $user = $this->createCustomer();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/users/{$user->hashid}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'email' => $user->email,
            ]);
    }

    /** @test */
    public function admin_can_update_user()
    {
        $admin = $this->createAdmin();
        $user = $this->createCustomer();

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$user->hashid}", [
                'name' => 'Updated Name',
                'city' => 'Curitiba',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Name',
                'city' => 'Curitiba',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'city' => 'Curitiba',
        ]);
    }

    /** @test */
    public function admin_can_deactivate_user()
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$user->hashid}", [
                'is_active' => false,
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function admin_can_activate_user()
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$user->hashid}", [
                'is_active' => true,
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function admin_can_change_user_role()
    {
        $admin = $this->createAdmin();
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$user->hashid}", [
                'role' => 'admin',
            ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function admin_can_soft_delete_user()
    {
        $admin = $this->createAdmin();
        $user = $this->createCustomer();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/users/{$user->hashid}");

        $response->assertStatus(200);
        
        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    /** @test */
    public function customer_cannot_delete_user()
    {
        $customer = $this->createCustomer();
        $otherUser = $this->createCustomer();

        $response = $this->actingAs($customer)
            ->deleteJson("/api/v1/users/{$otherUser->hashid}");

        $response->assertStatus(403);
    }

    /** @test */
    public function inactive_user_cannot_login()
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Your account has been deactivated. Please contact support.',
            ]);
    }

    /** @test */
    public function active_user_can_login()
    {
        $user = User::factory()->create([
            'email' => 'active@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'active@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user',
                    'token',
                ]
            ]);
    }

    /** @test */
    public function user_created_via_register_is_active_by_default()
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('users', [
            'email' => 'register@example.com',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function email_must_be_unique()
    {
        $admin = $this->createAdmin();
        $existingUser = $this->createCustomer();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/users', [
                'name' => 'Duplicate Email',
                'email' => $existingUser->email,
                'password' => 'password123',
                'role' => 'customer',
                'city' => 'São Paulo',
                'state' => 'SP',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
