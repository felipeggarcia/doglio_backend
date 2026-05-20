<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'is_active' => true]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    // =========================================================================
    // GET /api/v1/admin/users
    // =========================================================================

    #[Test]
    public function admin_can_list_users(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();

        $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/users')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'is_active']]]);
    }

    #[Test]
    public function customer_cannot_list_users(): void
    {
        $customer = $this->customer();

        $this->withToken($this->token($customer))
            ->getJson('/api/v1/admin/users')
            ->assertStatus(403);
    }

    #[Test]
    public function admin_can_filter_users_by_role(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create(['role' => 'customer']);
        User::factory()->count(2)->create(['role' => 'admin']);

        $data = $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/users?role=customer')
            ->assertStatus(200)
            ->json('data');

        foreach ($data as $user) {
            $this->assertEquals('customer', $user['role']);
        }
    }

    #[Test]
    public function admin_can_filter_users_by_is_active(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create(['is_active' => true]);
        User::factory()->count(2)->create(['is_active' => false]);

        $data = $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/users?is_active=1')
            ->assertStatus(200)
            ->json('data');

        foreach ($data as $user) {
            $this->assertTrue($user['is_active']);
        }
    }

    #[Test]
    public function admin_can_search_users(): void
    {
        $admin = $this->admin();
        $john  = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $data = $this->withToken($this->token($admin))
            ->getJson('/api/v1/admin/users?search=john')
            ->assertStatus(200)
            ->json('data');

        $this->assertNotEmpty($data);
        $this->assertTrue(collect($data)->contains(fn($u) => $u['id'] === $john->hashid));
    }

    // =========================================================================
    // POST /api/v1/admin/users
    // =========================================================================

    #[Test]
    public function admin_can_create_user(): void
    {
        $admin = $this->admin();

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/admin/users', [
                'name'      => 'New User',
                'email'     => 'newuser@example.com',
                'password'  => 'password123',
                'role'      => 'customer',
                'city'      => 'São Paulo',
                'state'     => 'SP',
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::USER_CREATED)
            ->assertJsonPath('data.name', 'New User')
            ->assertJsonPath('data.email', 'newuser@example.com');

        $this->assertDatabaseHas('users', [
            'email'     => 'newuser@example.com',
            'role'      => 'customer',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function admin_can_create_admin_user(): void
    {
        $admin = $this->admin();

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/admin/users', [
                'name'     => 'New Admin',
                'email'    => 'newadmin@example.com',
                'password' => 'password123',
                'role'     => 'admin',
                'city'     => 'Rio de Janeiro',
                'state'    => 'RJ',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@example.com',
            'role'  => 'admin',
        ]);
    }

    #[Test]
    public function customer_cannot_create_user(): void
    {
        $customer = $this->customer();

        $this->withToken($this->token($customer))
            ->postJson('/api/v1/admin/users', [
                'name'     => 'Test User',
                'email'    => 'test@example.com',
                'password' => 'password123',
                'role'     => 'customer',
                'city'     => 'São Paulo',
                'state'    => 'SP',
            ])
            ->assertStatus(403);
    }

    // =========================================================================
    // GET /api/v1/admin/users/{user}
    // =========================================================================

    #[Test]
    public function admin_can_view_user(): void
    {
        $admin = $this->admin();
        $user  = $this->customer();

        $this->withToken($this->token($admin))
            ->getJson("/api/v1/admin/users/{$user->hashid}")
            ->assertStatus(200)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'customer');
    }

    // =========================================================================
    // PUT /api/v1/admin/users/{user}
    // =========================================================================

    #[Test]
    public function admin_can_update_user(): void
    {
        $admin = $this->admin();
        $user  = $this->customer();

        $this->withToken($this->token($admin))
            ->putJson("/api/v1/admin/users/{$user->hashid}", [
                'name' => 'Updated Name',
                'city' => 'Curitiba',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::USER_UPDATED)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.city', 'Curitiba');

        $this->assertDatabaseHas('users', [
            'id'   => $user->id,
            'name' => 'Updated Name',
            'city' => 'Curitiba',
        ]);
    }

    #[Test]
    public function admin_can_deactivate_user(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['is_active' => true]);

        $this->withToken($this->token($admin))
            ->putJson("/api/v1/admin/users/{$user->hashid}", ['is_active' => false])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
    }

    #[Test]
    public function admin_can_activate_user(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['is_active' => false]);

        $this->withToken($this->token($admin))
            ->putJson("/api/v1/admin/users/{$user->hashid}", ['is_active' => true])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
    }

    #[Test]
    public function admin_can_change_user_role(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['role' => 'customer']);

        $this->withToken($this->token($admin))
            ->putJson("/api/v1/admin/users/{$user->hashid}", ['role' => 'admin'])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'admin']);
    }

    // =========================================================================
    // DELETE /api/v1/admin/users/{user}
    // =========================================================================

    #[Test]
    public function admin_can_soft_delete_user(): void
    {
        $admin = $this->admin();
        $user  = $this->customer();

        $this->withToken($this->token($admin))
            ->deleteJson("/api/v1/admin/users/{$user->hashid}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', ApiMessages::USER_DELETED);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    #[Test]
    public function customer_cannot_delete_user(): void
    {
        $customer  = $this->customer();
        $otherUser = $this->customer();

        $this->withToken($this->token($customer))
            ->deleteJson("/api/v1/admin/users/{$otherUser->hashid}")
            ->assertStatus(403);
    }

    // =========================================================================
    // Auth edge-cases (via /api/v1/login)
    // =========================================================================

    #[Test]
    public function inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email'     => 'inactive@example.com',
            'password'  => bcrypt('password123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/login', [
            'email'    => 'inactive@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', ApiMessages::AUTH_ACCOUNT_INACTIVE)
            ->assertJsonPath('error.code', 'ACCOUNT_INACTIVE');
    }

    #[Test]
    public function active_user_can_login(): void
    {
        User::factory()->create([
            'email'     => 'active@example.com',
            'password'  => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/login', [
            'email'    => 'active@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    #[Test]
    public function email_must_be_unique(): void
    {
        $admin        = $this->admin();
        $existingUser = $this->customer();

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/admin/users', [
                'name'     => 'Duplicate Email',
                'email'    => $existingUser->email,
                'password' => 'password123',
                'role'     => 'customer',
                'city'     => 'São Paulo',
                'state'    => 'SP',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.email', fn($errors) => !empty($errors));
    }

    #[Test]
    public function user_created_via_register_is_active_by_default(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Test User',
            'email'                 => 'register@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'city'                  => 'São Paulo',
            'state'                 => 'SP',
        ])
            ->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email'     => 'register@example.com',
            'is_active' => true,
        ]);
    }
}

