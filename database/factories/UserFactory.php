<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    private static function generateCpf(): string
    {
        $n = array_map(fn() => rand(0, 9), range(1, 9));

        $sum = 0;
        for ($i = 0; $i < 9; $i++) $sum += $n[$i] * (10 - $i);
        $r = $sum % 11;
        $n[] = $r < 2 ? 0 : 11 - $r;

        $sum = 0;
        for ($i = 0; $i < 10; $i++) $sum += $n[$i] * (11 - $i);
        $r = $sum % 11;
        $n[] = $r < 2 ? 0 : 11 - $r;

        return implode('', $n);
    }

    protected static array $brazilianStates = [
        'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA',
        'MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN',
        'RS','RO','RR','SC','SP','SE','TO',
    ];

    private static array $prefixes = ['Dr.', 'Dra.', 'Sr.', 'Sra.', 'Srta.', 'Prof.', 'Profa.'];

    private static function emailFromName(string $name): string
    {
        $parts  = array_values(array_filter(
            explode(' ', $name),
            fn($p) => !in_array($p, self::$prefixes) && strlen(preg_replace('/[^a-z]/i', '', Str::ascii($p))) > 0
        ));
        $first  = strtolower(preg_replace('/[^a-z]/i', '', Str::ascii($parts[0] ?? 'usuario')));
        $last   = strtolower(preg_replace('/[^a-z]/i', '', Str::ascii(end($parts) ?: 'usuario')));
        $domains = ['gmail.com', 'hotmail.com', 'yahoo.com', 'outlook.com', 'icloud.com', 'uol.com.br'];
        $domain  = $domains[array_rand($domains)];
        $suffix  = fake()->unique()->numberBetween(1, 9999);

        return $first . '.' . $last . $suffix . '@' . $domain;
    }

    public function definition(): array
    {
        $name = fake('pt_BR')->name();

        return [
            'name'               => $name,
            'email'              => self::emailFromName($name),
            'email_verified_at'  => now(),
            'password'           => static::$password ??= Hash::make('123456'),
            'remember_token'     => Str::random(10),
            'role'               => 'customer',
            'city'               => fake('pt_BR')->city(),
            'state'              => fake()->randomElement(self::$brazilianStates),
            'is_active'          => true,
            'cpf_cnpj'           => self::generateCpf(),
            'birth_date'         => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }
}
