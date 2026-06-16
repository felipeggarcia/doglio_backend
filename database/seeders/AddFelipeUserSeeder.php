<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserAddress;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AddFelipeUserSeeder extends Seeder
{
    public function run(): void
    {
        $felipe = User::updateOrCreate(
            ['email' => 'felipegogarcia@gmail.com'],
            [
                'name'     => 'Felipe Garcia',
                'password' => Hash::make('123456'),
                'role'     => 'customer',
                'city'     => 'Curitiba',
                'state'    => 'PR',
            ]
        );

        $addr1 = UserAddress::updateOrCreate(
            ['user_id' => $felipe->id, 'street' => 'Rua Voluntários da Pátria', 'number' => '400'],
            [
                'label'      => 'Casa',
                'complement' => 'Apto 82',
                'district'   => 'Centro',
                'city'       => 'Curitiba',
                'state'      => 'PR',
                'zip_code'   => '80020000',
                'is_primary' => true,
            ]
        );

        $addr2 = UserAddress::updateOrCreate(
            ['user_id' => $felipe->id, 'street' => 'Av. Sete de Setembro', 'number' => '1500'],
            [
                'label'      => 'Trabalho',
                'complement' => 'Sala 303',
                'district'   => 'Batel',
                'city'       => 'Curitiba',
                'state'      => 'PR',
                'zip_code'   => '80230000',
                'is_primary' => false,
            ]
        );

        $pix         = PaymentMethod::where('type', 'pix')->firstOrFail();
        $cartao      = PaymentMethod::where('type', 'credit_card')->firstOrFail();

        $p3  = Product::where('name', 'like', '%Trilho de Mesa Bordado Floral%')->firstOrFail();
        $p5  = Product::where('name', 'like', '%Flor e Trigo%')->firstOrFail();
        $p8  = Product::where('name', 'like', '%Árvore da Vida%')->firstOrFail();
        $p9  = Product::where('name', 'like', '%Galo e Trigo%')->firstOrFail();
        $m1  = Product::where('name', 'like', '%Matrioskas Clássicas%')->firstOrFail();
        $p6  = Product::where('name', 'like', '%Sagrada Família%')->firstOrFail();

        // Pedido 1 — entregue há 20 dias (2 itens, pago via PIX)
        $order1 = Order::create([
            'user_id'             => $felipe->id,
            'address_id'          => $addr1->id,
            'status'              => 'delivered',
            'total_amount'        => 219.00, // 2x Pêssanka R$65 + Trilho R$89
            'delivery_type'       => 'delivery',
            'shipping_street'     => $addr1->street,
            'shipping_number'     => $addr1->number,
            'shipping_complement' => $addr1->complement,
            'shipping_district'   => $addr1->district,
            'shipping_city'       => $addr1->city,
            'shipping_state'      => $addr1->state,
            'shipping_zip_code'   => $addr1->zip_code,
            'created_at'          => now()->subDays(20),
            'updated_at'          => now()->subDays(15),
        ]);

        OrderItem::create(['order_id' => $order1->id, 'product_id' => $p5->id, 'quantity' => 2, 'unit_price' => 65.00]);
        OrderItem::create(['order_id' => $order1->id, 'product_id' => $p3->id, 'quantity' => 1, 'unit_price' => 89.00]);

        foreach ([
            ['status' => 'pending',         'created_at' => now()->subDays(20)],
            ['status' => 'confirmed',        'created_at' => now()->subDays(19)],
            ['status' => 'preparing',        'created_at' => now()->subDays(18)],
            ['status' => 'out_for_delivery', 'created_at' => now()->subDays(17)],
            ['status' => 'delivered',        'created_at' => now()->subDays(15)],
        ] as $entry) {
            OrderStatusHistory::create(['order_id' => $order1->id, 'status' => $entry['status'], 'notes' => null, 'created_at' => $entry['created_at']]);
        }

        Payment::create([
            'order_id'          => $order1->id,
            'payment_method_id' => $pix->id,
            'status'            => 'paid',
            'amount'            => 219.00,
            'paid_at'           => now()->subDays(19),
        ]);

        // Pedido 2 — confirmado há 3 dias (1 item, pago via cartão)
        $order2 = Order::create([
            'user_id'             => $felipe->id,
            'address_id'          => $addr1->id,
            'status'              => 'confirmed',
            'total_amount'        => 145.00,
            'delivery_type'       => 'delivery',
            'shipping_street'     => $addr1->street,
            'shipping_number'     => $addr1->number,
            'shipping_complement' => $addr1->complement,
            'shipping_district'   => $addr1->district,
            'shipping_city'       => $addr1->city,
            'shipping_state'      => $addr1->state,
            'shipping_zip_code'   => $addr1->zip_code,
            'created_at'          => now()->subDays(3),
            'updated_at'          => now()->subDays(2),
        ]);

        OrderItem::create(['order_id' => $order2->id, 'product_id' => $m1->id, 'quantity' => 1, 'unit_price' => 145.00]);

        foreach ([
            ['status' => 'pending',   'created_at' => now()->subDays(3)],
            ['status' => 'confirmed', 'created_at' => now()->subDays(2)],
        ] as $entry) {
            OrderStatusHistory::create(['order_id' => $order2->id, 'status' => $entry['status'], 'notes' => null, 'created_at' => $entry['created_at']]);
        }

        Payment::create([
            'order_id'          => $order2->id,
            'payment_method_id' => $cartao->id,
            'status'            => 'paid',
            'amount'            => 145.00,
            'paid_at'           => now()->subDays(3),
        ]);

        // Pedido 3 — pending criado há 1 hora (2 itens, aguardando pagamento via PIX)
        $order3 = Order::create([
            'user_id'             => $felipe->id,
            'address_id'          => $addr2->id,
            'status'              => 'pending',
            'total_amount'        => 380.00, // Ovo Sagrada Família R$120 + Matrioskas R$145 + Pêssanka Árvore R$75 + Pêssanka Galo R$65 - 25 promo = na verdade só soma direta
            'delivery_type'       => 'delivery',
            'shipping_street'     => $addr2->street,
            'shipping_number'     => $addr2->number,
            'shipping_complement' => $addr2->complement,
            'shipping_district'   => $addr2->district,
            'shipping_city'       => $addr2->city,
            'shipping_state'      => $addr2->state,
            'shipping_zip_code'   => $addr2->zip_code,
            'created_at'          => now()->subHour(),
            'updated_at'          => now()->subHour(),
        ]);

        OrderItem::create(['order_id' => $order3->id, 'product_id' => $p6->id, 'quantity' => 1, 'unit_price' => 120.00]);
        OrderItem::create(['order_id' => $order3->id, 'product_id' => $p8->id, 'quantity' => 1, 'unit_price' => 75.00]);
        OrderItem::create(['order_id' => $order3->id, 'product_id' => $p9->id, 'quantity' => 2, 'unit_price' => 65.00]);

        // Recalcula o total real
        $order3->update(['total_amount' => $order3->orderItems()->sum(\Illuminate\Support\Facades\DB::raw('quantity * unit_price'))]);

        OrderStatusHistory::create(['order_id' => $order3->id, 'status' => 'pending', 'notes' => null, 'created_at' => now()->subHour()]);

        Payment::create([
            'order_id'          => $order3->id,
            'payment_method_id' => $pix->id,
            'status'            => 'pending',
            'amount'            => $order3->fresh()->total_amount,
            'paid_at'           => null,
        ]);

        // Pedido 4 — cancelado há 10 dias (retirada)
        $order4 = Order::create([
            'user_id'             => $felipe->id,
            'address_id'          => null,
            'status'              => 'cancelled',
            'total_amount'        => 75.00,
            'delivery_type'       => 'pickup',
            'shipping_street'     => null,
            'shipping_number'     => null,
            'shipping_complement' => null,
            'shipping_district'   => null,
            'shipping_city'       => null,
            'shipping_state'      => null,
            'shipping_zip_code'   => null,
            'created_at'          => now()->subDays(10),
            'updated_at'          => now()->subDays(9),
        ]);

        OrderItem::create(['order_id' => $order4->id, 'product_id' => $p8->id, 'quantity' => 1, 'unit_price' => 75.00]);

        foreach ([
            ['status' => 'pending',   'created_at' => now()->subDays(10), 'notes' => null],
            ['status' => 'cancelled', 'created_at' => now()->subDays(9),  'notes' => 'Cliente desistiu da retirada.'],
        ] as $entry) {
            OrderStatusHistory::create(['order_id' => $order4->id, 'status' => $entry['status'], 'notes' => $entry['notes'], 'created_at' => $entry['created_at']]);
        }

        Payment::create([
            'order_id'          => $order4->id,
            'payment_method_id' => $pix->id,
            'status'            => 'refunded',
            'amount'            => 75.00,
            'paid_at'           => now()->subDays(10),
        ]);

        // Movimentações de estoque dos pedidos criados
        $admin = User::where('email', 'nataliak@gmail.com')->first();

        StockMovement::create([
            'product_id' => $p5->id, 'type' => 'out', 'quantity' => 2,
            'stock_before' => $p5->stock_quantity + 1, 'reason' => 'sale',
            'reference_type' => 'order', 'reference_id' => $order1->id,
            'user_id' => null, 'created_at' => now()->subDays(20),
        ]);
        StockMovement::create([
            'product_id' => $p3->id, 'type' => 'out', 'quantity' => 1,
            'stock_before' => $p3->stock_quantity + 1, 'reason' => 'sale',
            'reference_type' => 'order', 'reference_id' => $order1->id,
            'user_id' => null, 'created_at' => now()->subDays(20),
        ]);
    }
}
