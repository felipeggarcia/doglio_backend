<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Promotion;
use App\Models\UserAddress;
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

        // 7. Métodos de Pagamento
        PaymentMethod::updateOrCreate(
            ['type' => 'pix'],
            ['name' => 'PIX', 'is_active' => true]
        );

        // 8. Promoção — 10% de desconto na Ração Super Premium
        $promo = Promotion::updateOrCreate(
            ['name' => 'Lançamento Ração Premium'],
            [
                'description' => '10% de desconto especial de lançamento na Ração Super Premium.',
                'type' => 'percentage',
                'discount_value' => 10.00,
                'starts_at' => now()->subDay(),
                'ends_at' => null,  // sem expiração
                'is_active' => true,
                'min_quantity' => null,
                'max_uses' => null,
            ]
        );

        // Vincula a promoção ao produto 1
        $promo->products()->sync([$product1->id]);

        // Promoção expirada (para testar que NÃO aparece no index público)
        $promoExpired = Promotion::updateOrCreate(
            ['name' => 'Black Friday 2025'],
            [
                'description' => 'Desconto de R$ 15,00 na Coleira Anti-pulgas. Promoção encerrada.',
                'type' => 'fixed',
                'discount_value' => 15.00,
                'starts_at' => now()->subDays(180),
                'ends_at' => now()->subDays(150),   // expirou há 150 dias
                'is_active' => true,
                'min_quantity' => null,
                'max_uses' => null,
            ]
        );

        $promoExpired->products()->sync([$product2->id]);

        // 9. Endereços do Cliente Teste
        $client = User::where('email', 'client@doglio.com')->first();

        UserAddress::updateOrCreate(
            ['user_id' => $client->id, 'street' => 'Rua das Flores', 'number' => '142'],
            [
                'label' => 'Casa',
                'complement' => 'Apto 31',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip' => '01310100',
                'is_primary' => true,
            ]
        );

        UserAddress::updateOrCreate(
            ['user_id' => $client->id, 'street' => 'Av. Paulista', 'number' => '1000'],
            [
                'label' => 'Trabalho',
                'complement' => 'Sala 504',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip' => '01310900',
                'is_primary' => false,
            ]
        );

        UserAddress::updateOrCreate(
            ['user_id' => $client->id, 'street' => 'Rua XV de Novembro', 'number' => '73'],
            [
                'label' => 'Casa da Mãe',
                'complement' => null,
                'city' => 'Curitiba',
                'state' => 'PR',
                'zip' => '80020310',
                'is_primary' => false,
            ]
        );
    }
}
