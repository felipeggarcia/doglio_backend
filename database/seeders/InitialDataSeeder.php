<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Product;
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
    public function run(): void
    {
        // 1. Administrador
        $admin = User::updateOrCreate(
            ['email' => 'nataliak@gmail.com'],
            [
                'name'     => 'Natalia Kovalenko',
                'password' => Hash::make('123456'),
                'role'     => 'admin',
                'city'     => 'Curitiba',
                'state'    => 'PR',
            ]
        );

        // 2. Cliente principal de testes
        User::updateOrCreate(
            ['email' => 'ana.tavares@hotmail.com'],
            [
                'name'     => 'Ana Tavares',
                'password' => Hash::make('123456'),
                'role'     => 'customer',
                'city'     => 'São Paulo',
                'state'    => 'SP',
            ]
        );

        // 3. Categorias
        $catDecor    = Category::updateOrCreate(['name' => 'Casa e Decoração'],     ['slug' => 'casa-e-decoracao',     'is_highlighted' => true,  'is_active' => true]);
        $catMesa     = Category::updateOrCreate(['name' => 'Mesa Posta'],            ['slug' => 'mesa-posta',            'is_highlighted' => true,  'is_active' => true]);
        $catPessanka = Category::updateOrCreate(['name' => 'Pêssankas'],             ['slug' => 'pessankas',             'is_highlighted' => true,  'is_active' => true]);
        $catSacra    = Category::updateOrCreate(['name' => 'Arte Sacra'],            ['slug' => 'arte-sacra',            'is_highlighted' => true,  'is_active' => true]);
        $catUkr      = Category::updateOrCreate(['name' => 'Artesanato Ucraniano'], ['slug' => 'artesanato-ucraniano', 'is_highlighted' => true,  'is_active' => true]);

        // 4. Produtos
        $p1 = Product::updateOrCreate(
            ['name' => 'Bandeja Retangular em Marchetaria Geométrica'],
            [
                'description'    => 'Sofisticação e arte para servir ou decorar. Esta bandeja exclusiva é confeccionada manualmente utilizando a técnica de marchetaria, unindo diferentes lâminas de madeiras nobres em um deslumbrante padrão geométrico de triângulos. Possui acabamento em verniz de alto brilho e alças modernas de metal cromado, garantindo durabilidade, ergonomia e um toque contemporâneo para a sua casa.',
                'price'          => 220.00,
                'stock_quantity' => 5,
                'is_highlighted' => true,
                'is_active'      => true,
            ]
        );

        $p2 = Product::updateOrCreate(
            ['name' => 'Balcão Aparador de Madeira com Marchetaria Estelar'],
            [
                'description'    => 'Uma verdadeira obra de arte em forma de móvel. Este aparador artesanal de madeira maciça destaca-se pelo trabalho minucioso de marchetaria em suas portas, criando um efeito óptico tridimensional com encaixes que formam estrelas e losangos em tons naturais. Ideal para salas de estar, jantar ou halls de entrada, conta com pés de metal estilo industrial e puxadores discretos.',
                'price'          => 1890.00,
                'stock_quantity' => 2,
                'is_highlighted' => true,
                'is_active'      => true,
            ]
        );

        $p3 = Product::updateOrCreate(
            ['name' => 'Trilho de Mesa Bordado Floral Ucraniano (Vermelho)'],
            [
                'description'    => 'Leve a tradição e a elegância do artesanato eslavo para a sua mesa posta. Este caminho de mesa é confeccionado em tecido de algodão branco de alta qualidade e apresenta um riquíssimo bordado simétrico em relevo na cor vermelha, representando a clássica "Árvore da Vida" com flores e arabescos. Finalizado com franjas artesanais desfiadas nas pontas.',
                'price'          => 89.00,
                'stock_quantity' => 12,
                'is_highlighted' => true,
                'is_active'      => true,
            ]
        );

        $p4 = Product::updateOrCreate(
            ['name' => 'Trilho de Mesa com Bordado Geométrico Eslavo (Ponto Cruz)'],
            [
                'description'    => 'Charme e história em cada ponto. Trilho de mesa com bordado geométrico tradicional inspirado nos motivos folclóricos ucranianos e eslavos. Trabalho feito com precisão em tons de vermelho e detalhes pretos sobre fundo branco, ideal para ocasiões especiais ou para trazer aconchego ao dia a dia. Acabamento impecável com franjas nas extremidades.',
                'price'          => 79.00,
                'stock_quantity' => 10,
                'is_highlighted' => false,
                'is_active'      => true,
            ]
        );

        $p5 = Product::updateOrCreate(
            ['name' => 'Pêssanka Ucraniana Tradicional — Flor e Trigo (Laranja e Vermelho)'],
            [
                'description'    => 'Arte milenar cheia de significados. Esta Pêssanka legítima é pintada à mão sobre casca de ovo natural usando a técnica de escrita com cera de abelha e tingimentos sucessivos. Apresenta padrões geométricos impecáveis com desenhos de flores (símbolo de amor e caridade) e ramos de trigo (símbolo de prosperidade e boa colheita).',
                'price'          => 65.00,
                'stock_quantity' => 18,
                'is_highlighted' => true,
                'is_active'      => true,
            ]
        );

        $p6 = Product::updateOrCreate(
            ['name' => 'Ovo Decorativo Religioso com Suporte — Ícone Sagrada Família'],
            [
                'description'    => 'Uma peça de devoção e extrema delicadeza. Ovo decorativo em base escura com aplicação de mini ícone bizantino da Sagrada Família (Jesus, Maria e José). A imagem central é contornada por uma rica moldura dourada e brilhante em relevo. Acompanha um elegante suporte de madeira torneada, perfeito para altares domésticos, oratórios ou presentes religiosos.',
                'price'          => 120.00,
                'stock_quantity' => 8,
                'is_highlighted' => true,
                'is_active'      => true,
            ]
        );

        $p7 = Product::updateOrCreate(
            ['name' => 'Ovo Decorativo Religioso com Suporte — Ícone Nossa Senhora'],
            [
                'description'    => 'Lindo ovo decorativo com pintura de inspiração bizantina representando a Virgem Maria com o Menino Jesus (Madona). Com fundo dourado que remete à luz divina e acabamento envernizado protetor, a peça traz serenidade e proteção ao ambiente. Inclui suporte de madeira maciça para exposição em mesas ou estantes.',
                'price'          => 120.00,
                'stock_quantity' => 8,
                'is_highlighted' => false,
                'is_active'      => true,
            ]
        );

        $p8 = Product::updateOrCreate(
            ['name' => 'Pêssanka Ucraniana Tradicional — Árvore da Vida e Cervos'],
            [
                'description'    => 'Pêssanka artesanal rica em detalhes e misticismo, feita sobre fundo preto para destacar os traços finos. Traz os símbolos tradicionais da Árvore da Vida (crescimento e conexão) e figuras de cervos na base (que simbolizam força e saúde), além de detalhes geométricos nas extremidades. Acompanha base de suporte discreta.',
                'price'          => 75.00,
                'stock_quantity' => 14,
                'is_highlighted' => true,
                'is_active'      => true,
            ]
        );

        $p9 = Product::updateOrCreate(
            ['name' => 'Pêssanka Ucraniana Tradicional — Galo e Trigo da Prosperidade'],
            [
                'description'    => 'Escrita à mão com precisão impressionante, esta Pêssanka destaca-se pelo desenho central de um Galo estilizado sobre fundo preto, que na tradição ucraniana simboliza o amanhecer, a vigilância e a boa sorte. Cercada por ramos de trigo dourados e faixas florais vermelhas, é uma peça única e cheia de boas energias para presentear.',
                'price'          => 65.00,
                'stock_quantity' => 11,
                'is_highlighted' => false,
                'is_active'      => true,
            ]
        );

        // Produto inativo — visível apenas para admin
        $pInativo = Product::updateOrCreate(
            ['name' => 'Almofada Bordada Ucraniana (Em Revisão)'],
            [
                'description'    => 'Almofada decorativa com bordado tradicional ucraniano em ponto cruz. Produto temporariamente indisponível para revisão de qualidade.',
                'price'          => 95.00,
                'stock_quantity' => 6,
                'is_highlighted' => false,
                'is_active'      => false,
            ]
        );

        // Produto deletado (soft delete) — invisível em todas as rotas
        $pDeleted = Product::updateOrCreate(
            ['name' => 'Porta-Joias Marchetaria (Descontinuado)'],
            [
                'description'    => 'Produto fora de linha.',
                'price'          => 140.00,
                'stock_quantity' => 0,
                'is_highlighted' => false,
                'is_active'      => false,
            ]
        );
        $pDeleted->delete();

        // 5. Associação Produto–Categoria
        $p1->categories()->sync([$catDecor->id, $catUkr->id]);
        $p2->categories()->sync([$catDecor->id, $catUkr->id]);
        $p3->categories()->sync([$catMesa->id,  $catUkr->id]);
        $p4->categories()->sync([$catMesa->id,  $catUkr->id]);
        $p5->categories()->sync([$catPessanka->id, $catUkr->id]);
        $p6->categories()->sync([$catSacra->id,    $catUkr->id]);
        $p7->categories()->sync([$catSacra->id,    $catUkr->id]);
        $p8->categories()->sync([$catPessanka->id, $catUkr->id]);
        $p9->categories()->sync([$catPessanka->id, $catUkr->id]);
        $pInativo->categories()->sync([$catUkr->id]);

        // 6. Métodos de Pagamento
        PaymentMethod::updateOrCreate(['type' => 'pix'],         ['name' => 'PIX',               'is_active' => true]);
        PaymentMethod::updateOrCreate(['type' => 'credit_card'], ['name' => 'Cartão de Crédito', 'is_active' => true]);
        PaymentMethod::updateOrCreate(['type' => 'boleto'],      ['name' => 'Boleto Bancário',    'is_active' => true]);

        // 7. Promoções

        // 7a. Semana das Pêssankas — 15% off (ativa, sem expiração)
        $promoPessanka = Promotion::updateOrCreate(
            ['name' => 'Semana das Pêssankas'],
            [
                'description'    => '15% de desconto em todas as Pêssankas Ucranianas Tradicionais.',
                'type'           => 'percentage',
                'discount_value' => 15.00,
                'starts_at'      => now()->subDay(),
                'ends_at'        => null,
                'is_active'      => true,
                'min_quantity'   => null,
            ]
        );
        $promoPessanka->products()->sync([
            $p5->id => ['use_limit' => 100],
            $p8->id => ['use_limit' => 100],
            $p9->id => ['use_limit' => 100],
        ]);

        // 7b. Enxoval de Mesa — R$10 off nos trilhos (ativa, expira em 30 dias)
        $promoMesa = Promotion::updateOrCreate(
            ['name' => 'Enxoval de Mesa'],
            [
                'description'    => 'R$ 10,00 de desconto nos Trilhos de Mesa Bordados.',
                'type'           => 'fixed',
                'discount_value' => 10.00,
                'starts_at'      => now()->subDays(3),
                'ends_at'        => now()->addDays(30),
                'is_active'      => true,
                'min_quantity'   => null,
            ]
        );
        $promoMesa->products()->sync([
            $p3->id => ['use_limit' => null],
            $p4->id => ['use_limit' => null],
        ]);

        // 7c. Black Friday 2025 — expirada (não aparece no index público)
        $promoExpired = Promotion::updateOrCreate(
            ['name' => 'Black Friday 2025'],
            [
                'description'    => 'R$ 30,00 de desconto na Bandeja Retangular. Promoção encerrada.',
                'type'           => 'fixed',
                'discount_value' => 30.00,
                'starts_at'      => now()->subDays(200),
                'ends_at'        => now()->subDays(170),
                'is_active'      => true,
                'min_quantity'   => null,
            ]
        );
        $promoExpired->products()->sync([$p1->id => ['use_limit' => null]]);

        // 7d. Desconto Arte Sacra — desativada (is_active = false)
        $promoInactive = Promotion::updateOrCreate(
            ['name' => 'Desconto Arte Sacra'],
            [
                'description'    => '10% de desconto nos Ovos Decorativos Religiosos. Promoção suspensa.',
                'type'           => 'percentage',
                'discount_value' => 10.00,
                'starts_at'      => now()->subDays(10),
                'ends_at'        => now()->addDays(20),
                'is_active'      => false,
                'min_quantity'   => null,
            ]
        );
        $promoInactive->products()->sync([
            $p6->id => ['use_limit' => null],
            $p7->id => ['use_limit' => null],
        ]);

        // 8. Endereços do cliente
        $client = User::where('email', 'ana.tavares@hotmail.com')->first();

        UserAddress::updateOrCreate(
            ['user_id' => $client->id, 'street' => 'Rua das Flores', 'number' => '142'],
            [
                'label'      => 'Casa',
                'complement' => 'Apto 31',
                'district'   => 'Bela Vista',
                'city'       => 'São Paulo',
                'state'      => 'SP',
                'zip_code'   => '01310100',
                'is_primary' => true,
            ]
        );

        UserAddress::updateOrCreate(
            ['user_id' => $client->id, 'street' => 'Av. Paulista', 'number' => '1000'],
            [
                'label'      => 'Trabalho',
                'complement' => 'Sala 504',
                'district'   => 'Bela Vista',
                'city'       => 'São Paulo',
                'state'      => 'SP',
                'zip_code'   => '01310900',
                'is_primary' => false,
            ]
        );

        UserAddress::updateOrCreate(
            ['user_id' => $client->id, 'street' => 'Rua XV de Novembro', 'number' => '73'],
            [
                'label'      => 'Casa da Mãe',
                'complement' => null,
                'district'   => 'Centro',
                'city'       => 'Curitiba',
                'state'      => 'PR',
                'zip_code'   => '80020310',
                'is_primary' => false,
            ]
        );

        // 9. Pedido 1 — entregue (permite avaliação do produto)
        // Pêssanka Flor e Trigo: R$65 com 15% off = R$55,25
        $pixMethod      = PaymentMethod::where('type', 'pix')->first();
        $primaryAddress = $client->addresses()->where('is_primary', true)->first();

        $pastOrder = Order::create([
            'user_id'             => $client->id,
            'address_id'          => $primaryAddress->id,
            'status'              => 'delivered',
            'total_amount'        => 55.25,
            'delivery_type'       => 'delivery',
            'shipping_street'     => $primaryAddress->street,
            'shipping_number'     => $primaryAddress->number,
            'shipping_complement' => $primaryAddress->complement,
            'shipping_district'   => $primaryAddress->district,
            'shipping_city'       => $primaryAddress->city,
            'shipping_state'      => $primaryAddress->state,
            'shipping_zip_code'   => $primaryAddress->zip_code,
            'created_at'          => now()->subDays(30),
            'updated_at'          => now()->subDays(25),
        ]);

        OrderItem::create([
            'order_id'   => $pastOrder->id,
            'product_id' => $p5->id,
            'quantity'   => 1,
            'unit_price' => 55.25,
        ]);

        foreach ([
            ['status' => 'pending',         'created_at' => now()->subDays(30)],
            ['status' => 'confirmed',        'created_at' => now()->subDays(29)],
            ['status' => 'preparing',        'created_at' => now()->subDays(28)],
            ['status' => 'out_for_delivery', 'created_at' => now()->subDays(27)],
            ['status' => 'delivered',        'created_at' => now()->subDays(25)],
        ] as $entry) {
            OrderStatusHistory::create([
                'order_id'   => $pastOrder->id,
                'status'     => $entry['status'],
                'notes'      => null,
                'created_at' => $entry['created_at'],
            ]);
        }

        Payment::create([
            'order_id'          => $pastOrder->id,
            'payment_method_id' => $pixMethod->id,
            'status'            => 'paid',
            'amount'            => 55.25,
            'paid_at'           => now()->subDays(29),
        ]);

        CartSnapshot::create([
            'user_id'      => $client->id,
            'trigger_type' => 'CHECKOUT',
            'total_value'  => 55.25,
            'content'      => [
                [
                    'product_id'     => $p5->id,
                    'product_name'   => $p5->name,
                    'quantity'       => 1,
                    'unit_price'     => 55.25,
                    'original_price' => 65.00,
                    'promotion_id'   => $promoPessanka->id,
                    'promotion_name' => $promoPessanka->name,
                    'discount_type'  => $promoPessanka->type,
                    'discount_value' => $promoPessanka->discount_value,
                ],
            ],
            'created_at' => now()->subDays(30),
        ]);

        // 10. Pedido 2 — pending, retirada
        // 2x Trilho de Mesa Bordado Floral com R$10 off = R$79 cada = R$158 total
        $pendingOrder = Order::create([
            'user_id'             => $client->id,
            'address_id'          => null,
            'status'              => 'pending',
            'total_amount'        => 158.00,
            'delivery_type'       => 'pickup',
            'shipping_street'     => null,
            'shipping_number'     => null,
            'shipping_complement' => null,
            'shipping_district'   => null,
            'shipping_city'       => null,
            'shipping_state'      => null,
            'shipping_zip_code'   => null,
            'created_at'          => now()->subHours(2),
            'updated_at'          => now()->subHours(2),
        ]);

        OrderItem::create([
            'order_id'   => $pendingOrder->id,
            'product_id' => $p3->id,
            'quantity'   => 2,
            'unit_price' => 79.00,
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
            'amount'            => 158.00,
            'paid_at'           => null,
        ]);

        // 11. Pedido 3 — cancelado
        // 1x Bandeja Retangular: R$220
        $cancelledOrder = Order::create([
            'user_id'             => $client->id,
            'address_id'          => $primaryAddress->id,
            'status'              => 'cancelled',
            'total_amount'        => 220.00,
            'delivery_type'       => 'delivery',
            'shipping_street'     => $primaryAddress->street,
            'shipping_number'     => $primaryAddress->number,
            'shipping_complement' => $primaryAddress->complement,
            'shipping_district'   => $primaryAddress->district,
            'shipping_city'       => $primaryAddress->city,
            'shipping_state'      => $primaryAddress->state,
            'shipping_zip_code'   => $primaryAddress->zip_code,
            'created_at'          => now()->subDays(5),
            'updated_at'          => now()->subDays(4),
        ]);

        OrderItem::create([
            'order_id'   => $cancelledOrder->id,
            'product_id' => $p1->id,
            'quantity'   => 1,
            'unit_price' => 220.00,
        ]);

        foreach ([
            ['status' => 'pending',   'created_at' => now()->subDays(5)],
            ['status' => 'confirmed', 'created_at' => now()->subDays(5)->addHours(1)],
            ['status' => 'cancelled', 'created_at' => now()->subDays(4), 'notes' => 'Cliente solicitou cancelamento.'],
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
            'amount'            => 220.00,
            'paid_at'           => now()->subDays(5)->addHours(1),
        ]);

        // 12. Movimentações de estoque iniciais
        $stockEntries = [
            [$p1, 5,  'Estoque inicial — Bandeja Retangular Marchetaria'],
            [$p2, 2,  'Estoque inicial — Balcão Aparador Marchetaria Estelar'],
            [$p3, 12, 'Estoque inicial — Trilho de Mesa Bordado Floral'],
            [$p4, 10, 'Estoque inicial — Trilho de Mesa Bordado Geométrico'],
            [$p5, 18, 'Estoque inicial — Pêssanka Flor e Trigo'],
            [$p6, 8,  'Estoque inicial — Ovo Decorativo Sagrada Família'],
            [$p7, 8,  'Estoque inicial — Ovo Decorativo Nossa Senhora'],
            [$p8, 14, 'Estoque inicial — Pêssanka Árvore da Vida e Cervos'],
            [$p9, 11, 'Estoque inicial — Pêssanka Galo e Trigo'],
        ];

        foreach ($stockEntries as [$product, $qty, $note]) {
            StockMovement::create([
                'product_id'   => $product->id,
                'type'         => 'in',
                'quantity'     => $qty,
                'stock_before' => 0,
                'reason'       => 'purchase',
                'user_id'      => $admin->id,
                'notes'        => $note,
                'created_at'   => now()->subDays(60),
            ]);
        }

        // Saída referente ao pedido 1 (pêssanka entregue)
        StockMovement::create([
            'product_id'     => $p5->id,
            'type'           => 'out',
            'quantity'       => 1,
            'stock_before'   => 18,
            'reason'         => 'sale',
            'reference_type' => 'order',
            'reference_id'   => $pastOrder->id,
            'user_id'        => null,
            'notes'          => null,
            'created_at'     => now()->subDays(30),
        ]);

        // Saída + estorno referente ao pedido 3 (bandeja cancelada)
        StockMovement::create([
            'product_id'     => $p1->id,
            'type'           => 'out',
            'quantity'       => 1,
            'stock_before'   => 5,
            'reason'         => 'sale',
            'reference_type' => 'order',
            'reference_id'   => $cancelledOrder->id,
            'user_id'        => null,
            'notes'          => null,
            'created_at'     => now()->subDays(5),
        ]);

        StockMovement::create([
            'product_id'     => $p1->id,
            'type'           => 'in',
            'quantity'       => 1,
            'stock_before'   => 4,
            'reason'         => 'return',
            'reference_type' => 'order',
            'reference_id'   => $cancelledOrder->id,
            'user_id'        => null,
            'notes'          => 'Estorno por cancelamento.',
            'created_at'     => now()->subDays(4),
        ]);

        // 13. Clientes adicionais gerados por factory
        User::factory()->count(15)->create();
        User::factory()->count(3)->inactive()->create();
        User::factory()->count(2)->unverified()->create();
    }
}
