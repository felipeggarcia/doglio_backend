<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserAddress;
use App\Support\ApiMessages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserAddressTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function address(User $user, array $attrs = []): UserAddress
    {
        return UserAddress::create(array_merge([
            'user_id'    => $user->id,
            'street'     => 'Rua das Flores',
            'number'     => '100',
            'city'       => 'Recife',
            'state'      => 'PE',
            'zip'        => '52000000',
            'is_primary' => false,
        ], $attrs));
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    // =========================================================================
    // GET /api/v1/addresses
    // =========================================================================

    #[Test]
    public function index_returns_empty_list_for_new_user(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->getJson('/api/v1/addresses')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function index_requires_authentication(): void
    {
        $this->getJson('/api/v1/addresses')
            ->assertStatus(401);
    }

    #[Test]
    public function index_returns_only_own_addresses(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $this->address($user);
        $this->address($user);
        $this->address($other); // não deve aparecer

        $this->withToken($this->token($user))
            ->getJson('/api/v1/addresses')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function index_primary_address_comes_first(): void
    {
        $user = User::factory()->create();

        $secondary = $this->address($user, ['is_primary' => false, 'street' => 'Rua B']);
        $primary   = $this->address($user, ['is_primary' => true,  'street' => 'Rua A']);

        $response = $this->withToken($this->token($user))
            ->getJson('/api/v1/addresses')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $this->assertEquals($primary->hashid, $response->json('data.0.id'));
    }

    // =========================================================================
    // POST /api/v1/addresses
    // =========================================================================

    #[Test]
    public function store_creates_address_with_valid_data(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/addresses', [
                'street' => 'Av. Boa Viagem',
                'number' => '200',
                'city'   => 'Recife',
                'state'  => 'PE',
                'zip'    => '51020001',
            ])
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::ADDRESS_CREATED,
            ])
            ->assertJsonStructure([
                'data' => ['id', 'street', 'number', 'city', 'state', 'zip', 'is_primary'],
            ]);

        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $user->id,
            'street'  => 'Av. Boa Viagem',
            'city'    => 'Recife',
        ]);
    }

    #[Test]
    public function store_requires_authentication(): void
    {
        $this->postJson('/api/v1/addresses', [
            'street' => 'Rua X',
            'number' => '1',
            'city'   => 'SP',
            'state'  => 'SP',
            'zip'    => '01000000',
        ])->assertStatus(401);
    }

    #[Test]
    public function store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/addresses', [])
            ->assertStatus(422)
            ->assertJsonPath('error.details.street', fn ($v) => !empty($v))
            ->assertJsonPath('error.details.number', fn ($v) => !empty($v))
            ->assertJsonPath('error.details.city',   fn ($v) => !empty($v))
            ->assertJsonPath('error.details.state',  fn ($v) => !empty($v))
            ->assertJsonPath('error.details.zip',    fn ($v) => !empty($v));
    }

    #[Test]
    public function store_validates_state_must_be_exactly_2_chars(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/addresses', [
                'street' => 'Rua X',
                'number' => '1',
                'city'   => 'Recife',
                'state'  => 'PER', // 3 chars — inválido
                'zip'    => '52000000',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.state', fn ($v) => !empty($v));
    }

    #[Test]
    public function store_validates_zip_must_be_exactly_8_chars(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/addresses', [
                'street' => 'Rua X',
                'number' => '1',
                'city'   => 'Recife',
                'state'  => 'PE',
                'zip'    => '1234567', // 7 chars — inválido
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.zip', fn ($v) => !empty($v));
    }

    #[Test]
    public function store_uppercases_state(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/addresses', [
                'street' => 'Rua X',
                'number' => '1',
                'city'   => 'Recife',
                'state'  => 'pe',
                'zip'    => '52000000',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.state', 'PE');

        $this->assertDatabaseHas('user_addresses', ['user_id' => $user->id, 'state' => 'PE']);
    }

    #[Test]
    public function store_with_is_primary_demotes_existing_primary(): void
    {
        $user    = User::factory()->create();
        $current = $this->address($user, ['is_primary' => true]);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/addresses', [
                'street'     => 'Nova Rua',
                'number'     => '50',
                'city'       => 'Recife',
                'state'      => 'PE',
                'zip'        => '52000000',
                'is_primary' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('user_addresses', [
            'id'         => $current->id,
            'is_primary' => false,
        ]);
    }

    #[Test]
    public function store_saves_optional_label_and_complement(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/addresses', [
                'label'      => 'Casa',
                'street'     => 'Rua X',
                'number'     => '1',
                'complement' => 'Apto 302',
                'city'       => 'Recife',
                'state'      => 'PE',
                'zip'        => '52000000',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.label', 'Casa')
            ->assertJsonPath('data.complement', 'Apto 302');
    }

    // =========================================================================
    // PUT /api/v1/addresses/{address}
    // =========================================================================

    #[Test]
    public function update_owner_can_update_own_address(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user);

        $this->withToken($this->token($user))
            ->putJson("/api/v1/addresses/{$address->hashid}", [
                'street' => 'Rua Atualizada',
                'number' => '999',
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::ADDRESS_UPDATED,
            ])
            ->assertJsonPath('data.street', 'Rua Atualizada')
            ->assertJsonPath('data.number', '999');
    }

    #[Test]
    public function update_requires_authentication(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user);

        $this->putJson("/api/v1/addresses/{$address->hashid}", ['street' => 'X'])
            ->assertStatus(401);
    }

    #[Test]
    public function update_other_user_cannot_update_address(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $address = $this->address($owner);

        $this->withToken($this->token($other))
            ->putJson("/api/v1/addresses/{$address->hashid}", ['street' => 'Invasao'])
            ->assertStatus(403);
    }

    #[Test]
    public function update_uppercases_state(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user);

        $this->withToken($this->token($user))
            ->putJson("/api/v1/addresses/{$address->hashid}", ['state' => 'sp'])
            ->assertStatus(200)
            ->assertJsonPath('data.state', 'SP');
    }

    #[Test]
    public function update_accepts_partial_fields(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user, ['city' => 'Recife']);

        $this->withToken($this->token($user))
            ->putJson("/api/v1/addresses/{$address->hashid}", ['number' => '42'])
            ->assertStatus(200)
            ->assertJsonPath('data.number', '42')
            ->assertJsonPath('data.city', 'Recife'); // não mudou
    }

    // =========================================================================
    // DELETE /api/v1/addresses/{address}
    // =========================================================================

    #[Test]
    public function destroy_owner_can_delete_own_address(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user);

        $this->withToken($this->token($user))
            ->deleteJson("/api/v1/addresses/{$address->hashid}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::ADDRESS_DELETED,
            ]);

        $this->assertSoftDeleted('user_addresses', ['id' => $address->id]);
    }

    #[Test]
    public function destroy_requires_authentication(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user);

        $this->deleteJson("/api/v1/addresses/{$address->hashid}")
            ->assertStatus(401);
    }

    #[Test]
    public function destroy_other_user_cannot_delete_address(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $address = $this->address($owner);

        $this->withToken($this->token($other))
            ->deleteJson("/api/v1/addresses/{$address->hashid}")
            ->assertStatus(403);
    }

    #[Test]
    public function deleted_address_not_returned_in_index(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user);
        $address->delete();

        $this->withToken($this->token($user))
            ->getJson('/api/v1/addresses')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // =========================================================================
    // PATCH /api/v1/addresses/{address}/primary
    // =========================================================================

    #[Test]
    public function set_primary_marks_address_as_primary(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user, ['is_primary' => false]);

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/addresses/{$address->hashid}/primary")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => ApiMessages::ADDRESS_PRIMARY_SET,
            ])
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('user_addresses', ['id' => $address->id, 'is_primary' => true]);
    }

    #[Test]
    public function set_primary_demotes_previous_primary(): void
    {
        $user    = User::factory()->create();
        $old     = $this->address($user, ['is_primary' => true]);
        $new     = $this->address($user, ['is_primary' => false]);

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/addresses/{$new->hashid}/primary")
            ->assertStatus(200)
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('user_addresses', ['id' => $old->id, 'is_primary' => false]);
        $this->assertDatabaseHas('user_addresses', ['id' => $new->id, 'is_primary' => true]);
    }

    #[Test]
    public function set_primary_requires_authentication(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user);

        $this->patchJson("/api/v1/addresses/{$address->hashid}/primary")
            ->assertStatus(401);
    }

    #[Test]
    public function set_primary_other_user_cannot_set(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $address = $this->address($owner);

        $this->withToken($this->token($other))
            ->patchJson("/api/v1/addresses/{$address->hashid}/primary")
            ->assertStatus(403);
    }

    // =========================================================================
    // Edge cases / testes sênior
    // =========================================================================

    #[Test]
    public function soft_deleted_address_returns_404_on_update(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user);
        $hashid  = $address->hashid;
        $address->delete();

        $this->withToken($this->token($user))
            ->putJson("/api/v1/addresses/{$hashid}", ['street' => 'Fantasma'])
            ->assertStatus(404);
    }

    #[Test]
    public function soft_deleted_address_returns_404_on_set_primary(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user);
        $hashid  = $address->hashid;
        $address->delete();

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/addresses/{$hashid}/primary")
            ->assertStatus(404);
    }

    #[Test]
    public function update_ignores_is_primary_field(): void
    {
        // PUT /addresses/{id} não deve alterar is_primary — apenas PATCH /primary faz isso
        $user    = User::factory()->create();
        $address = $this->address($user, ['is_primary' => false]);

        $this->withToken($this->token($user))
            ->putJson("/api/v1/addresses/{$address->hashid}", [
                'street'     => 'Rua Atualizada',
                'is_primary' => true, // deve ser ignorado silenciosamente
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.is_primary', false); // continua false

        $this->assertDatabaseHas('user_addresses', ['id' => $address->id, 'is_primary' => false]);
    }

    #[Test]
    public function set_primary_does_not_demote_other_users_addresses(): void
    {
        // Isolamento multi-tenant: setPrimary só afeta endereços do usuário autenticado
        $userA  = User::factory()->create();
        $userB  = User::factory()->create();

        $addrA1 = $this->address($userA, ['is_primary' => false]);
        $addrB  = $this->address($userB, ['is_primary' => true]); // primary do user B

        $this->withToken($this->token($userA))
            ->patchJson("/api/v1/addresses/{$addrA1->hashid}/primary")
            ->assertStatus(200);

        // Primary do user B deve continuar intacto
        $this->assertDatabaseHas('user_addresses', ['id' => $addrB->id, 'is_primary' => true]);
    }

    #[Test]
    public function store_non_primary_address_does_not_demote_existing_primary(): void
    {
        $user    = User::factory()->create();
        $primary = $this->address($user, ['is_primary' => true]);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/addresses', [
                'street'     => 'Nova Rua',
                'number'     => '1',
                'city'       => 'Recife',
                'state'      => 'PE',
                'zip'        => '52000000',
                'is_primary' => false,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_primary', false);

        // Endereço primary original não deve ter sido demovido
        $this->assertDatabaseHas('user_addresses', ['id' => $primary->id, 'is_primary' => true]);
    }

    #[Test]
    public function set_primary_is_idempotent(): void
    {
        $user    = User::factory()->create();
        $address = $this->address($user, ['is_primary' => true]);

        // Chamar duas vezes deve manter is_primary=true sem erros
        $this->withToken($this->token($user))
            ->patchJson("/api/v1/addresses/{$address->hashid}/primary")
            ->assertStatus(200)
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('user_addresses', ['id' => $address->id, 'is_primary' => true]);
    }

    #[Test]
    public function store_validates_label_max_100_chars(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/addresses', [
                'label'  => str_repeat('x', 101),
                'street' => 'Rua X',
                'number' => '1',
                'city'   => 'Recife',
                'state'  => 'PE',
                'zip'    => '52000000',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.label', fn ($v) => !empty($v));
    }

    #[Test]
    public function set_primary_demotes_all_existing_primaries(): void
    {
        // Cenário: estado inconsistente no DB onde múltiplos endereços estão marcados
        // como is_primary=true. setPrimary deve zerar todos e setar apenas o alvo.
        $user   = User::factory()->create();
        $addr1  = $this->address($user, ['is_primary' => true]);
        $addr2  = $this->address($user, ['is_primary' => true]); // segundo primário indevido
        $addr3  = $this->address($user, ['is_primary' => true]); // terceiro primário indevido
        $target = $this->address($user, ['is_primary' => false]);

        $this->withToken($this->token($user))
            ->patchJson("/api/v1/addresses/{$target->hashid}/primary")
            ->assertStatus(200)
            ->assertJsonPath('data.is_primary', true);

        // Todos os anteriores devem ter sido demovidos
        $this->assertDatabaseHas('user_addresses', ['id' => $addr1->id,  'is_primary' => false]);
        $this->assertDatabaseHas('user_addresses', ['id' => $addr2->id,  'is_primary' => false]);
        $this->assertDatabaseHas('user_addresses', ['id' => $addr3->id,  'is_primary' => false]);
        $this->assertDatabaseHas('user_addresses', ['id' => $target->id, 'is_primary' => true]);

        // Garante que exatamente 1 endereço é primário no total
        $this->assertEquals(1, \App\Models\UserAddress::where('user_id', $user->id)
            ->where('is_primary', true)->count());
    }

    #[Test]
    public function store_rejects_non_alpha_state(): void
    {
        // 'state' deve ser somente letras — '12' não é um código de estado válido
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/addresses', [
                'street' => 'Rua X',
                'number' => '1',
                'city'   => 'Recife',
                'state'  => '12', // dígitos — deve falhar
                'zip'    => '52000000',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.state', fn ($v) => !empty($v));
    }

    #[Test]
    public function store_rejects_non_numeric_zip(): void
    {
        // 'zip' deve conter exatamente 8 dígitos numéricos
        $user = User::factory()->create();

        $this->withToken($this->token($user))
            ->postJson('/api/v1/addresses', [
                'street' => 'Rua X',
                'number' => '1',
                'city'   => 'Recife',
                'state'  => 'PE',
                'zip'    => 'ABCDEFGH', // letras com 8 chars — deve falhar
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.details.zip', fn ($v) => !empty($v));
    }

    #[Test]
    public function deleting_primary_address_does_not_auto_promote_another(): void
    {
        // Comportamento esperado: deletar o endereço primário NÃO promove
        // automaticamente outro — o usuário deve chamar PATCH /primary manualmente
        $user      = User::factory()->create();
        $primary   = $this->address($user, ['is_primary' => true]);
        $secondary = $this->address($user, ['is_primary' => false]);

        $this->withToken($this->token($user))
            ->deleteJson("/api/v1/addresses/{$primary->hashid}")
            ->assertStatus(200);

        $this->assertSoftDeleted('user_addresses', ['id' => $primary->id]);
        $this->assertDatabaseHas('user_addresses', [
            'id'         => $secondary->id,
            'is_primary' => false, // continua false, sem promoção automática
        ]);
    }
}