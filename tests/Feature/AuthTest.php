<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // POST /api/v1/register
    // =========================================================================

    #[Test]
    public function user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'city'                  => 'SÃ£o Paulo',
            'state'                 => 'SP',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'role', 'city', 'state'],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::AUTH_REGISTERED,
                'data'    => [
                    'user'       => ['email' => 'test@example.com', 'role' => 'customer'],
                    'token_type' => 'Bearer',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email'     => 'test@example.com',
            'role'      => 'customer',
            'city'      => 'SÃ£o Paulo',
            'state'     => 'SP',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function register_stores_optional_city_and_state(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'No City',
            'email'                 => 'nocity@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'nocity@example.com',
            'city'  => null,
            'state' => null,
        ]);
    }

    #[Test]
    public function register_fails_when_name_is_missing(): void
    {
        $this->postJson('/api/v1/register', [
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::HTTP_VALIDATION_ERROR,
                'error'   => ['code' => 'VALIDATION_ERROR'],
            ])
            ->assertJsonPath('error.details.name', fn ($v) => !empty($v));
    }

    #[Test]
    public function register_fails_with_invalid_email_format(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Test User',
            'email'                 => 'not-an-email',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.email', fn ($v) => !empty($v));
    }

    #[Test]
    public function register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->postJson('/api/v1/register', [
            'name'                  => 'Test User',
            'email'                 => 'existing@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::HTTP_VALIDATION_ERROR,
                'error'   => ['code' => 'VALIDATION_ERROR'],
            ])
            ->assertJsonPath('error.details.email', fn ($v) => !empty($v));
    }

    #[Test]
    public function register_fails_when_password_is_too_short(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => '1234567', // 7 chars e min is 8
            'password_confirmation' => '1234567',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.password', fn ($v) => !empty($v));
    }

    #[Test]
    public function register_fails_when_password_confirmation_does_not_match(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'different999',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.password', fn ($v) => !empty($v));
    }

    // =========================================================================
    // POST /api/v1/login
    // =========================================================================

    #[Test]
    public function user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email'     => 'user@example.com',
            'password'  => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/login', [
            'email'    => 'user@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user' => ['id', 'name', 'email', 'role'], 'token', 'token_type'],
            ])
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::AUTH_LOGIN,
                'data'    => ['token_type' => 'Bearer'],
            ]);

        $this->assertNotNull($user->fresh()->last_login);
    }

    #[Test]
    public function login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email'    => 'user@example.com',
            'password' => 'wrongpassword',
        ])
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::AUTH_INVALID_CREDENTIALS,
                'error'   => ['code' => 'INVALID_CREDENTIALS'],
            ]);
    }

    #[Test]
    public function login_fails_when_email_does_not_exist(): void
    {
        $this->postJson('/api/v1/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::AUTH_INVALID_CREDENTIALS,
                'error'   => ['code' => 'INVALID_CREDENTIALS'],
            ]);
    }

    #[Test]
    public function login_fails_when_fields_are_missing(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::HTTP_VALIDATION_ERROR,
                'error'   => ['code' => 'VALIDATION_ERROR'],
            ])
            ->assertJsonPath('error.details.email', fn ($v) => !empty($v))
            ->assertJsonPath('error.details.password', fn ($v) => !empty($v));
    }

    #[Test]
    public function inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email'     => 'inactive@example.com',
            'password'  => Hash::make('password123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/login', [
            'email'    => 'inactive@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::AUTH_ACCOUNT_INACTIVE,
                'error'   => ['code' => 'ACCOUNT_INACTIVE'],
            ]);
    }

    // =========================================================================
    // POST /api/v1/logout
    // =========================================================================

    #[Test]
    public function authenticated_user_can_logout(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/logout')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::AUTH_LOGOUT,
            ]);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    #[Test]
    public function logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/logout')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::HTTP_UNAUTHENTICATED,
                'error'   => ['code' => 'UNAUTHENTICATED'],
            ]);
    }

    // =========================================================================
    // GET /api/v1/user
    // =========================================================================

    #[Test]
    public function authenticated_user_can_get_own_profile(): void
    {
        $user  = User::factory()->create(['name' => 'John Doe', 'city' => 'Recife', 'state' => 'PE']);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'city', 'state']])
            ->assertJson([
                'data' => [
                    'name'  => 'John Doe',
                    'email' => $user->email,
                    'city'  => 'Recife',
                    'state' => 'PE',
                ],
            ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/v1/user')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => ApiMessages::HTTP_UNAUTHENTICATED,
                'error'   => ['code' => 'UNAUTHENTICATED'],
            ]);
    }

    // =========================================================================
    // CPF / CNPJ
    // =========================================================================

    #[Test]
    public function register_with_valid_cpf_saves_only_digits_and_returns_formatted(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name'                  => 'CPF User',
            'email'                 => 'cpf@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'cpf_cnpj'              => '529.982.247-25', // CPF com formataÃ§Ã£o
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.cpf_cnpj', '529.982.247-25'); // Retorna formatado

        $this->assertDatabaseHas('users', [
            'email'    => 'cpf@example.com',
            'cpf_cnpj' => '52998224725', // Salvo apenas dÃ­gitos
        ]);
    }

    #[Test]
    public function register_with_valid_cnpj_saves_only_digits(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name'                  => 'CNPJ User',
            'email'                 => 'cnpj@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'cpf_cnpj'              => '11.222.333/0001-81', // CNPJ com formataÃ§Ã£o
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.cpf_cnpj', '11.222.333/0001-81'); // Retorna formatado

        $this->assertDatabaseHas('users', [
            'email'    => 'cnpj@example.com',
            'cpf_cnpj' => '11222333000181', // Salvo apenas dÃ­gitos
        ]);
    }

    #[Test]
    public function register_fails_with_invalid_cpf_check_digits(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Invalid CPF',
            'email'                 => 'bad@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'cpf_cnpj'              => '123.456.789-00', // DÃ­gitos verificadores errados
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.cpf_cnpj', fn ($v) => !empty($v));
    }

    #[Test]
    public function register_fails_with_all_same_digit_cpf(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Same Digits',
            'email'                 => 'same@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'cpf_cnpj'              => '111.111.111-11',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.cpf_cnpj', fn ($v) => !empty($v));
    }

    #[Test]
    public function register_fails_with_duplicate_cpf(): void
    {
        User::factory()->create(['cpf_cnpj' => '52998224725']);

        $this->postJson('/api/v1/register', [
            'name'                  => 'Another User',
            'email'                 => 'another@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'cpf_cnpj'              => '529.982.247-25',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.cpf_cnpj', fn ($v) => !empty($v));
    }

    #[Test]
    public function authenticated_user_sees_formatted_cpf_in_own_profile(): void
    {
        $user  = User::factory()->create(['cpf_cnpj' => '52998224725']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertStatus(200)
            ->assertJsonPath('data.cpf_cnpj', '529.982.247-25');
    }

    #[Test]
    public function cpf_cnpj_is_not_visible_to_other_authenticated_users(): void
    {
        // A UserResource nÃ£o expÃµe cpf_cnpj de um usuÃ¡rio para outro usuÃ¡rio comum.
        // Testamos indiretamente: ao listar via admin, o campo existe;
        // aqui garantimos que o campo nÃ£o aparece em contextos nÃ£o-owner/nÃ£o-admin.
        $owner = User::factory()->create(['cpf_cnpj' => '52998224725']);
        $other = User::factory()->create();

        // Simula Resource renderizado sem contexto de owner/admin
        $resource = new \App\Http\Resources\UserResource($owner);
        $request  = \Illuminate\Http\Request::create('/api/v1/user');
        $request->setUserResolver(fn () => $other); // outro usuÃ¡rio autenticado

        $data = $resource->toArray($request);

        $this->assertArrayNotHasKey('cpf_cnpj', $data);
    }

    // =========================================================================
    // SeguranÃ§a / comportamento crÃ­tico
    // =========================================================================

    #[Test]
    public function register_cannot_set_role_to_admin(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Hacker',
            'email'                 => 'hacker@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'admin', // tentativa de privilege escalation
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'hacker@example.com',
            'role'  => 'customer', // sempre customer, ignorando o campo enviado
        ]);
    }

    #[Test]
    public function register_stores_password_as_hash(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Hash Test',
            'email'                 => 'hash@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $user = User::where('email', 'hash@example.com')->first();

        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    #[Test]
    public function register_token_is_usable_after_registration(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name'                  => 'Token Test',
            'email'                 => 'tokentest@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $token = $response->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/user')
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'tokentest@example.com');
    }

    // =========================================================================
    // birth_date
    // =========================================================================

    #[Test]
    public function register_with_valid_birth_date_saves_correctly(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Birth Date User',
            'email'                 => 'birthday@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'birth_date'            => '1990-06-15',
        ])->assertStatus(201);

        $user = User::where('email', 'birthday@example.com')->first();

        $this->assertNotNull($user->birth_date);
        $this->assertEquals('1990-06-15', $user->birth_date->toDateString());
    }

    #[Test]
    public function register_fails_with_future_birth_date(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Future User',
            'email'                 => 'future@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'birth_date'            => now()->addYear()->format('Y-m-d'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.birth_date', fn ($v) => !empty($v));
    }

    #[Test]
    public function register_fails_with_invalid_cnpj_check_digits(): void
    {
        $this->postJson('/api/v1/register', [
            'name'                  => 'Invalid CNPJ',
            'email'                 => 'badcnpj@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'cpf_cnpj'              => '11.222.333/0001-00', // dÃ­gitos verificadores errados
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.cpf_cnpj', fn ($v) => !empty($v));
    }
}
