<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductImage;
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
            ]
        );

        $product2 = Product::updateOrCreate(
            ['name' => 'Coleira Anti-pulgas'],
            [
                'description' => 'Coleira eficaz contra pulgas e carrapatos.',
                'price' => 45.90,
                'stock_quantity' => 50,
                'is_highlighted' => false,
            ]
        );
        
        // 5. Liga Produtos às Categorias (MUITOS-PARA-MUITOS)
        $product1->categories()->sync([$catC->id, $catA->id]); // Produto 1 está em Alimentos e Promoções
        $product2->categories()->sync([$catB->id]); // Produto 2 está em Acessórios Pet

        // 6. Cria Imagens para os Produtos
        // Produto 1 - Ração (2 imagens)
        ProductImage::updateOrCreate(
            ['product_id' => $product1->id, 'order' => 0],
            [
                'path' => 'products/product_1_img_1_a1b2c3d4.jpg',
                'is_primary' => true,
            ]
        );
        ProductImage::updateOrCreate(
            ['product_id' => $product1->id, 'order' => 1],
            [
                'path' => 'products/product_1_img_2_e5f6g7h8.jpg',
                'is_primary' => false,
            ]
        );

        // Produto 2 - Coleira (1 imagem)
        ProductImage::updateOrCreate(
            ['product_id' => $product2->id, 'order' => 0],
            [
                'path' => 'products/product_2_img_1_i9j0k1l2.jpg',
                'is_primary' => true,
            ]
        );
    }
}
