<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Cria o Administrador
        User::updateOrCreate(
            ['email' => 'admin@doglio.com'],
            [
                'name' => 'Admin Doglio',
                'password' => Hash::make('password'), // Senha fácil para DEV
                'role' => 'admin',
                'city' => 'Curitiba',
                'state' => 'PR',
            ]
        );

        // 2. Cria o Cliente Padrão
        User::updateOrCreate(
            ['email' => 'client@doglio.com'],
            [
                'name' => 'Cliente Teste',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'city' => 'São Paulo',
                'state' => 'SP',
            ]
        );

        // 3. Cria Categorias
        $catA = Category::updateOrCreate(['name' => 'Promoções'], ['slug' => 'promocoes', 'is_highlighted' => true]);
        $catB = Category::updateOrCreate(['name' => 'Acessórios Pet'], ['slug' => 'acessorios-pet', 'is_highlighted' => true]);
        $catC = Category::updateOrCreate(['name' => 'Alimentos'], ['slug' => 'alimentos', 'is_highlighted' => false]);
        
        // 4. Cria Produtos
        $product1 = Product::updateOrCreate(
            ['name' => 'Ração Super Premium'],
            [
                'description' => 'A melhor ração para o seu cão, rica em proteínas.',
                'price' => 150.00,
                'stock_quantity' => 25,
                'is_highlighted' => true,
                'image_url' => 'products/racaosuper.jpg',
            ]
        );

        $product2 = Product::updateOrCreate(
            ['name' => 'Coleira Anti-pulgas'],
            [
                'description' => 'Coleira eficaz contra pulgas e carrapatos.',
                'price' => 45.90,
                'stock_quantity' => 50,
                'is_highlighted' => false,
                'image_url' => 'products/coleira.jpg',
            ]
        );
        
        // 5. Liga Produtos às Categorias (MUITOS-PARA-MUITOS)
        $product1->categories()->sync([$catC->id, $catA->id]); // Produto 1 está em Alimentos e Promoções
        $product2->categories()->sync([$catB->id]); // Produto 2 está em Acessórios Pet
    }
}
