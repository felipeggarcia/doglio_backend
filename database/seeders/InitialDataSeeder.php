<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Category;
use App\Models\PaymentMethod;
use App\Models\Promotion;
use App\Models\UserAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\CartSnapshot;
use App\Models\StockMovement;
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
        $catA = Category::updateOrCreate(['name' => 'Promoções'], ['slug' => 'promocoes', 'is_highlighted' => true, 'is_active' => true]);
        $catB = Category::updateOrCreate(['name' => 'Acessórios Pet'], ['slug' => 'acessorios-pet', 'is_highlighted' => true, 'is_active' => true]);
        $catC = Category::updateOrCreate(['name' => 'Alimentos'], ['slug' => 'alimentos', 'is_highlighted' => false, 'is_active' => true]);
        
        // 4. Cria Produtos
        $product1 = Product::updateOrCreate(
            ['name' => 'Ração Super Premium'],
            [
                'description' => 'A melhor ração para o seu cão, rica em proteínas.',
                'price' => 150.00,
                'stock_quantity' => 25,
                'is_highlighted' => true,
                'is_active' => true,
            ]
        );

        $product2 = Product::updateOrCreate(
            ['name' => 'Coleira Anti-pulgas'],
            [
                'description' => 'Coleira eficaz contra pulgas e carrapatos.',
                'price' => 45.90,
                'stock_quantity' => 50,
                'is_highlighted' => false,
                'is_active' => true,
            ]
        );

        $product3 = Product::updateOrCreate(
            ['name' => 'Cama Pet Ortopédica'],
            [
                'description' => 'Cama com espuma ortopédica para cães e gatos de todas as raças.',
                'price' => 189.90,
                'stock_quantity' => 0,
                'is_highlighted' => false,
                'is_active' => true,
            ]
        );

        // Produto desativado (is_active = false) — visível só para admin
        $product4 = Product::updateOrCreate(
            ['name' => 'Brinquedo Interativo para Gatos'],
            [
                'description' => 'Brinquedo com penas e luz LED para entreter gatos.',
                'price' => 39.90,
                'stock_quantity' => 15,
                'is_highlighted' => false,
                'is_active' => false,
            ]
        );

        // Produto deletado (soft delete) — invisível em todas as rotas
        $product5 = Product::updateOrCreate(
            ['name' => 'Shampoo Pet Antiquado'],
            [
                'description' => 'Produto fora de linha.',
                'price' => 19.90,
                'stock_quantity' => 0,
                'is_highlighted' => false,
                'is_active' => false,
            ]
        );
        $product5->delete();

        // 5. Liga Produtos às Categorias (MUITOS-PARA-MUITOS)
        $product1->categories()->sync([$catC->id, $catA->id]);
        $product2->categories()->sync([$catB->id]);
        $product3->categories()->sync([$catB->id]);
        $product4->categories()->sync([$catB->id]);
        // product5 está deletado, sem categoria necessária

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
            ]
        );

        // Vincula a promoção ao produto 1 com limite de 50 usos
        $promo->products()->sync([$product1->id => ['use_limit' => 50]]);

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
            ]
        );

        $promoExpired->products()->sync([$product2->id => ['use_limit' => null]]);

        // Promoção com múltiplos produtos — ativa, sem expiração
        $promoMulti = Promotion::updateOrCreate(
            ['name' => 'Kit Pet Completo'],
            [
                'description' => 'Desconto de R$ 20,00 na Ração Super Premium e na Cama Pet Ortopédica.',
                'type' => 'fixed',
                'discount_value' => 20.00,
                'starts_at' => now()->subDays(3),
                'ends_at' => now()->addDays(30),
                'is_active' => false,
                'min_quantity' => null,
            ]
        );
        $promoMulti->products()->sync([
            $product1->id => ['use_limit' => 30],
            $product3->id => ['use_limit' => 20],
        ]);

        // Promoção desativada (is_active = false) — não aparece no index público
        $promoInactive = Promotion::updateOrCreate(
            ['name' => 'Desconto Coleira VIP'],
            [
                'description' => 'Promoção suspensa temporariamente. 25% de desconto na Coleira Anti-pulgas.',
                'type' => 'percentage',
                'discount_value' => 25.00,
                'starts_at' => now()->subDays(10),
                'ends_at' => now()->addDays(20),
                'is_active' => false,
                'min_quantity' => null,
            ]
        );
        $promoInactive->products()->sync([
            $product2->id => ['use_limit' => null],
        ]);

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

        // 10. Pedido antigo entregue — permite que client@doglio.com avalie o produto 1
        $pixMethod = PaymentMethod::where('type', 'pix')->first();
        $primaryAddress = $client->addresses()->where('is_primary', true)->first();

        $pastOrder = Order::create([
            'user_id'           => $client->id,
            'address_id'        => $primaryAddress->id,
            'status'            => 'delivered',
            'total_amount'      => 135.00, // R$150 com 10% promo = R$135
            'delivery_type'     => 'delivery',
            'shipping_street'   => $primaryAddress->street,
            'shipping_number'   => $primaryAddress->number,
            'shipping_complement' => $primaryAddress->complement,
            'shipping_city'     => $primaryAddress->city,
            'shipping_state'    => $primaryAddress->state,
            'shipping_zip'      => $primaryAddress->zip,
            'created_at'        => now()->subDays(30),
            'updated_at'        => now()->subDays(25),
        ]);

        OrderItem::create([
            'order_id'   => $pastOrder->id,
            'product_id' => $product1->id,
            'quantity'   => 1,
            'unit_price' => 135.00,
        ]);

        // Histórico completo de status do pedido
        $statusFlow = [
            ['status' => 'pending',          'created_at' => now()->subDays(30)],
            ['status' => 'confirmed',         'created_at' => now()->subDays(29)],
            ['status' => 'preparing',         'created_at' => now()->subDays(28)],
            ['status' => 'out_for_delivery',  'created_at' => now()->subDays(27)],
            ['status' => 'delivered',         'created_at' => now()->subDays(25)],
        ];

        foreach ($statusFlow as $entry) {
            OrderStatusHistory::create([
                'order_id'   => $pastOrder->id,
                'status'     => $entry['status'],
                'notes'      => null,
                'created_at' => $entry['created_at'],
            ]);
        }

        // Pagamento do pedido (pago via PIX)
        Payment::create([
            'order_id'          => $pastOrder->id,
            'payment_method_id' => $pixMethod->id,
            'status'            => 'paid',
            'amount'            => 135.00,
            'paid_at'           => now()->subDays(29),
        ]);

        // CartSnapshot registrado no momento do checkout (carrinho zerado após isso)
        CartSnapshot::create([
            'user_id'      => $client->id,
            'trigger_type' => 'CHECKOUT',
            'total_value'  => 135.00,
            'content'      => [
                [
                    'product_id'    => $product1->id,
                    'product_name'  => $product1->name,
                    'quantity'      => 1,
                    'unit_price'    => 135.00,
                    'original_price' => 150.00,
                    'promotion_id'  => $promo->id,
                    'promotion_name' => $promo->name,
                    'discount_type' => $promo->type,
                    'discount_value' => $promo->discount_value,
                ],
            ],
            'created_at' => now()->subDays(30),
        ]);

        // 11. Movimentações de estoque iniciais (entrada por compra — baseline do sistema)
        $admin = User::where('email', 'admin@doglio.com')->first();

        StockMovement::create([
            'product_id'   => $product1->id,
            'type'         => 'in',
            'quantity'     => 25,
            'stock_before' => 0,
            'reason'       => 'purchase',
            'user_id'      => $admin->id,
            'notes'        => 'Estoque inicial — Ração Super Premium',
            'created_at'   => now()->subDays(60),
        ]);

        StockMovement::create([
            'product_id'   => $product2->id,
            'type'         => 'in',
            'quantity'     => 50,
            'stock_before' => 0,
            'reason'       => 'purchase',
            'user_id'      => $admin->id,
            'notes'        => 'Estoque inicial — Coleira Anti-pulgas',
            'created_at'   => now()->subDays(60),
        ]);

        // Saída de estoque referente ao pedido histórico
        StockMovement::create([
            'product_id'     => $product1->id,
            'type'           => 'out',
            'quantity'       => 1,
            'stock_before'   => 25,
            'reason'         => 'sale',
            'reference_type' => 'order',
            'reference_id'   => $pastOrder->id,
            'user_id'        => null,
            'notes'          => null,
            'created_at'     => now()->subDays(30),
        ]);

        // Pedido 2 — pending (aguardando confirmação admin)
        $pendingOrder = Order::create([
            'user_id'           => $client->id,
            'address_id'        => null,
            'status'            => 'pending',
            'total_amount'      => 91.80, // 2x Coleira R$45,90
            'delivery_type'     => 'pickup',
            'shipping_street'   => null,
            'shipping_number'   => null,
            'shipping_complement' => null,
            'shipping_city'     => null,
            'shipping_state'    => null,
            'shipping_zip'      => null,
            'created_at'        => now()->subHours(2),
            'updated_at'        => now()->subHours(2),
        ]);

        OrderItem::create([
            'order_id'   => $pendingOrder->id,
            'product_id' => $product2->id,
            'quantity'   => 2,
            'unit_price' => 45.90,
        ]);

        OrderStatusHistory::create([
            'order_id'   => $pendingOrder->id,
            'status'     => 'pending',
            'notes'      => null,
            'created_at' => now()->subHours(2),
        ]);

        Payment::create([
            'order_id'          => $pendingOrder->id,
            'payment_method_id' => $pixMethod->id,
            'status'            => 'pending',
            'amount'            => 91.80,
            'paid_at'           => null,
        ]);

        // Pedido 3 — cancelled (com estorno de estoque registrado)
        $cancelledOrder = Order::create([
            'user_id'           => $client->id,
            'address_id'        => $primaryAddress->id,
            'status'            => 'cancelled',
            'total_amount'      => 189.90,
            'delivery_type'     => 'delivery',
            'shipping_street'   => $primaryAddress->street,
            'shipping_number'   => $primaryAddress->number,
            'shipping_complement' => $primaryAddress->complement,
            'shipping_city'     => $primaryAddress->city,
            'shipping_state'    => $primaryAddress->state,
            'shipping_zip'      => $primaryAddress->zip,
            'created_at'        => now()->subDays(5),
            'updated_at'        => now()->subDays(4),
        ]);

        OrderItem::create([
            'order_id'   => $cancelledOrder->id,
            'product_id' => $product3->id,
            'quantity'   => 1,
            'unit_price' => 189.90,
        ]);

        foreach ([
            ['status' => 'pending',    'created_at' => now()->subDays(5)],
            ['status' => 'confirmed',  'created_at' => now()->subDays(5)->addHours(1)],
            ['status' => 'cancelled',  'created_at' => now()->subDays(4), 'notes' => 'Cliente solicitou cancelamento.'],
        ] as $entry) {
            OrderStatusHistory::create([
                'order_id'   => $cancelledOrder->id,
                'status'     => $entry['status'],
                'notes'      => $entry['notes'] ?? null,
                'created_at' => $entry['created_at'],
            ]);
        }

        Payment::create([
            'order_id'          => $cancelledOrder->id,
            'payment_method_id' => $pixMethod->id,
            'status'            => 'refunded',
            'amount'            => 189.90,
            'paid_at'           => now()->subDays(5)->addHours(1),
        ]);
    }
}
