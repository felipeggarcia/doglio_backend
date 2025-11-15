<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);
        
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 9999),
            'is_highlighted' => fake()->boolean(30), // 30% chance de ser destacado
        ];
    }

    /**
     * Indicate that the category is highlighted.
     */
    public function highlighted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_highlighted' => true,
        ]);
    }
}
