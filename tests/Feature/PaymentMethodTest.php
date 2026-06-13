<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // GET /api/v1/payment_methods
    // =========================================================================

    #[Test]
    public function index_returns_200_without_authentication(): void
    {
        $this->getJson('/api/v1/payment_methods')->assertStatus(200);
    }

    #[Test]
    public function index_returns_only_active_methods(): void
    {
        PaymentMethod::factory()->pix()->create(['name' => 'PIX Ativo']);
        PaymentMethod::factory()->boleto()->inactive()->create(['name' => 'Boleto Inativo']);

        $response = $this->getJson('/api/v1/payment_methods')->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('PIX Ativo', $names);
        $this->assertNotContains('Boleto Inativo', $names);
    }

    #[Test]
    public function index_returns_empty_data_when_no_active_methods_exist(): void
    {
        PaymentMethod::factory()->inactive()->create();

        $this->getJson('/api/v1/payment_methods')
            ->assertStatus(200)
            ->assertJson(['data' => []]);
    }

    #[Test]
    public function index_returns_correct_response_structure(): void
    {
        PaymentMethod::factory()->pix()->create();

        $this->getJson('/api/v1/payment_methods')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'type', 'is_active'],
                ],
            ]);
    }

    #[Test]
    public function index_id_is_a_string_hashid_not_an_integer(): void
    {
        PaymentMethod::factory()->pix()->create();

        $response = $this->getJson('/api/v1/payment_methods')->assertStatus(200);

        $id = $response->json('data.0.id');
        $this->assertIsString($id);
        $this->assertFalse(is_numeric($id));
    }

    #[Test]
    public function index_is_active_field_is_boolean_true(): void
    {
        PaymentMethod::factory()->pix()->create();

        $response = $this->getJson('/api/v1/payment_methods')->assertStatus(200);

        $this->assertTrue($response->json('data.0.is_active'));
    }

    #[Test]
    public function index_returns_all_supported_types(): void
    {
        PaymentMethod::factory()->pix()->create();
        PaymentMethod::factory()->creditCard()->create();
        PaymentMethod::factory()->boleto()->create();

        $response = $this->getJson('/api/v1/payment_methods')->assertStatus(200);

        $types = collect($response->json('data'))->pluck('type')->sort()->values()->toArray();
        $this->assertEquals(['boleto', 'credit_card', 'pix'], $types);
    }

    #[Test]
    public function index_works_the_same_for_authenticated_users(): void
    {
        PaymentMethod::factory()->pix()->create();

        $token = User::factory()->create()->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/payment_methods')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function index_returns_multiple_active_methods(): void
    {
        PaymentMethod::factory()->pix()->create();
        PaymentMethod::factory()->creditCard()->create();
        PaymentMethod::factory()->boleto()->inactive()->create();

        $this->getJson('/api/v1/payment_methods')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
