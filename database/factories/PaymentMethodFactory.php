<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['pix', 'credit_card', 'boleto']);

        return [
            'name'      => ucfirst(str_replace('_', ' ', $type)),
            'type'      => $type,
            'is_active' => true,
        ];
    }

    public function pix(): static
    {
        return $this->state(['name' => 'PIX', 'type' => 'pix']);
    }

    public function creditCard(): static
    {
        return $this->state(['name' => 'Cartão de Crédito', 'type' => 'credit_card']);
    }

    public function boleto(): static
    {
        return $this->state(['name' => 'Boleto', 'type' => 'boleto']);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
